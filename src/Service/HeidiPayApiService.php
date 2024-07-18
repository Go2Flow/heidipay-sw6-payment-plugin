<?php

namespace Go2FlowHeidiPayPayment\Service;

use Go2FlowHeidiPayPayment\Go2FlowHeidiPayPayment;
use Go2FlowHeidiPayPayment\Handler\PaymentHandler;
use Go2FlowHeidiPayPayment\Helper\HeidiPayRequester;
use Go2FlowHeidiPayPayment\Helper\OrderHelper;
use Go2FlowHeidiPayPayment\Installer\Modules\PaymentMethodInstaller;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class HeidiPayApiService
{
    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * @var SystemConfigService
     */
    protected SystemConfigService $configService;

    /**
     * @var string
     */
    protected string $sandbox;

    /**
     * @var string
     */
    protected string $auth_url = 'auth/generate/';

    /**
     * @var string
     */
    protected string $init_trans_url = 'api/checkout/v1/init/';

    /**
     * @var string
     */
    protected string $contract_status_url = 'api/checkout/v1/status/';

    /**
     * @var string
     */
    protected string $refund_url = 'api/checkout/v1/refund/';

    /**
     * @var array
     */
    public array $supports = array( 'products', 'refunds' );

    const STATUS_APPROVED = 'performing';
    const STATUS_DECLINED = 'abandoned';
    const STATUS_PENDING = 'pending';
    const STATUS_AWAITING = 'awaiting_confirmation';
    const STATUS_SUCCESS = 'success';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_ACTIVE = 'active';
    private WebhookService $webhookService;

    /**
     * Constructor
     *
     * @param LoggerInterface $logger
     * @param SystemConfigService $configService
     */
    public function __construct(
        LoggerInterface $logger,
        SystemConfigService $configService,
        WebhookService $webhookService,
    )
    {
        $this->logger = $logger;
        $this->configService = $configService;
        $this->webhookService = $webhookService;
    }

    /**
     * @param string $option
     * @param SalesChannelContext|null $salesChannelContext
     * @return mixed
     */
    private function getConfigValue(string $option, SalesChannelContext $salesChannelContext = null)
    {
        $salesChannelId = $salesChannelContext?->getSalesChannelId();
        return $this->getConfigValueByChannelId($option, $salesChannelId);
    }

    /**
     * @param string $option
     * @param string|null $salesChannelId
     * @return bool|float|int|mixed[]|string|null
     */
    public function getConfigValueByChannelId(string $option, ?string $salesChannelId = null)
    {
        return $this->configService->get('Go2FlowHeidiPayPayment.settings.'.$option, $salesChannelId);
    }

    private function getAuthTransactionToken(string $salesChannelContextId = null) {
        $merchant_key = $this->getConfigValueByChannelId( 'heidiSecretKey' , $salesChannelContextId );

        if ( ! $merchant_key ) return false;

        $body = [ 'merchant_key' => $merchant_key ];

        $url =  $this->getApiUrl($salesChannelContextId).$this->auth_url;

        $request_util = new HeidiPayRequester();
        $request_util->setUrl( $url );
        $response = $request_util->post( $body );

        $responseData = json_decode( $response['contents'] ?? '', true );

        if ( empty( $responseData['status'] ) || $responseData['status'] !== 'success' || empty( $responseData['data']['token'] ) ) {
            return false;
        }

        return $responseData['data']['token'];
    }

    public function testAuthTransactionToken($token, SalesChannelContext $salesChannelContext = null) {

        if ( ! $token ) return false;

        $body = [ 'merchant_key' => $token ];

        $url =  $this->getQualifiedApiUrl($salesChannelContext).$this->auth_url;

        $request_util = new HeidiPayRequester();
        $request_util->setUrl( $url );
        $response = $request_util->post( $body );

        $responseData = json_decode( $response['contents'] ?? '', true );

        if ( empty( $responseData['status'] ) || $responseData['status'] !== 'success' || empty( $responseData['data']['token'] ) ) {
            return false;
        }

        return $responseData['data']['token'];
    }

    /**
     * @deprecated use getApiUrl
     * @param SalesChannelContext|null $salesChannelContext
     * @return string
     */
    private function getQualifiedApiUrl(SalesChannelContext $salesChannelContext = null): string
    {
        return $this->getApiUrl($salesChannelContext?->getSalesChannelId());
    }

    private function getApiUrl(string $salesChannelId = null): string
    {
        $mode = $this->getConfigValueByChannelId( 'heidiMode' , $salesChannelId );
        if($mode != '1') {
            return PaymentHandler::SANDBOX_BASE_URL.'/';
        }
        return PaymentHandler::BASE_URL.'/';
    }

    public function processPayment(OrderEntity $order, string $url, $productRepository, SalesChannelContext $salesChannelContext): ?array
    {
        try {
            $token = $this->getAuthTransactionToken($salesChannelContext->getSalesChannelId());

            $paymentMethod = $order->getTransactions()->last()->getPaymentMethod()->getTechnicalName();
            if ($paymentMethod === (PaymentHandler::PAYMENT_METHOD_PREFIX.PaymentMethodInstaller::HEIDIPAY_CREDIT_METHOD)) {
                $productType = 'CREDIT';
                $terms = $this->getConfigValue( 'heidiPromotionTermsCredit' , $salesChannelContext );
            } else {
                $productType = 'BNPL';
                $terms = $this->getConfigValue( 'heidiPromotionTerms' , $salesChannelContext );
            }
            $webhookToken = $this->webhookService->createToken($order->getId(), $salesChannelContext->getContext(), WebhookService::ACTION_STATUS);

            $body = OrderHelper::prepareOrderData( $order, $url, $terms, $productType, $webhookToken, $salesChannelContext, $this->configService );
            $url = $this->getApiUrl($salesChannelContext->getSalesChannelId()).$this->init_trans_url;

            $request_util = new HeidiPayRequester();
            $request_util->addHeader( 'Authorization', 'Token ' . $token );
            $request_util->addHeader( 'X-Client-Module-Version', Go2FlowHeidiPayPayment::HEIDIPAY_CURRENT_VERSION );
            $request_util->setUrl( $url );
            $response = $request_util->post( $body );
            if (!empty($response['code']) && $response['code'] >= 300) {
                throw new \Exception($response['contents'], 500);
            }
            $responseData = json_decode( $response['contents'] ?? '', true );
            if (!empty( $responseData['status_code'] ) && $responseData['status_code'] === 500) {
                throw new \Exception(($responseData['title'] . ': ' . $responseData['detail']), 500);
            }
            if ( empty( $responseData['redirect_url'] ) || empty( $responseData['external_contract_uuid'] ) ) {
                return null;
            }

            return $responseData;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), [$e]);
        }

        return null;
    }

    /**
     * @param string $external_contract_uuid
     * @param SalesChannelContext $salesChannelContext
     * @return bool
     */
    public function checkOrderStatus(string $external_contract_uuid , SalesChannelContext $salesChannelContext)
    {

        $token = $this->getAuthTransactionToken($salesChannelContext->getSalesChannelId());

        $url = $this->getQualifiedApiUrl($salesChannelContext).$this->contract_status_url.$external_contract_uuid.'/';

        $request_util = new HeidiPayRequester();
        $request_util->addHeader( 'Authorization', 'Token ' . $token );
        $request_util->addHeader( 'X-Client-Module-Version', Go2FlowHeidiPayPayment::HEIDIPAY_CURRENT_VERSION );
        $request_util->setUrl( $url );
        $response = $request_util->get();

        if ( $response['code'] >= 300 ) {
            return false; // Must be 2XX status
        }

        $content = json_decode( $response['contents'], true );

        if (
            isset( $content['status'] )
            && in_array(strtolower( $content['status'] ), [self::STATUS_APPROVED, self::STATUS_ACTIVE])
        ) {
            return true;
        }

        return false;
    }

    /**
     * @param array $external_contract_uuids
     * @param string $salesChannelId
     * @return array
     */
    public function getOrderStatus(array $external_contract_uuids , string $salesChannelId): array
    {

        $token = $this->getAuthTransactionToken($salesChannelId);

        $url = $this->getApiUrl($salesChannelId).'api/checkout/v2/status/';

        $request_util = new HeidiPayRequester();
        $request_util->addHeader( 'Authorization', 'Token ' . $token );
        $request_util->addHeader( 'X-Client-Module-Version', Go2FlowHeidiPayPayment::HEIDIPAY_CURRENT_VERSION );
        $request_util->setUrl( $url );
        $response = $request_util->post([
            'external_contract_uuids' => $external_contract_uuids
        ]);

        if ( $response['code'] !== 200 ) {
            return [];
        }

        return json_decode( $response['contents'], true )['statuses'];
    }

    /**
     * @param string $externalId
     * @param float $amount
     * @param string $currency
     * @param string $salesChannelId
     * @return bool
     */
    public function refund(string $externalId, float $amount, string $currency, string $salesChannelId): bool
    {
        $token = $this->getAuthTransactionToken($salesChannelId);

        $url = $this->getApiUrl($salesChannelId).'api/checkout/v1/refund/';

        $request_util = new HeidiPayRequester();
        $request_util->addHeader( 'Authorization', 'Token ' . $token );
        $request_util->addHeader( 'X-Client-Module-Version', Go2FlowHeidiPayPayment::HEIDIPAY_CURRENT_VERSION );
        $request_util->setUrl( $url );
        $response = $request_util->post([
            'external_uuid' => $externalId,
            'amount' => $amount,
            'currency' => $currency,
        ]);

        if ( $response['code'] !== 200 ) {
            return false;
        }

        return true;
    }

    /**
     * @param string $externalId
     * @param string $salesChannelId
     * @return bool
     */
    public function confirmDelivery(string $externalId, string $salesChannelId):bool
    {
        $token = $this->getAuthTransactionToken($salesChannelId);

        $url = $this->getApiUrl($salesChannelId).'api/checkout/v1/confirm/';

        $request_util = new HeidiPayRequester();
        $request_util->addHeader( 'Authorization', 'Token ' . $token );
        $request_util->addHeader( 'X-Client-Module-Version', Go2FlowHeidiPayPayment::HEIDIPAY_CURRENT_VERSION );
        $request_util->setUrl( $url );
        $response = $request_util->post([
            'external_uuid' => $externalId
        ]);

        if ( $response['code'] !== 200 ) {
            return false;
        }

        return true;
    }


}
