<?php

namespace Go2FlowHeidiPayPayment\Helper;

use Go2FlowHeidiPayPayment\Service\WebhookService;
use Go2FlowHeidiPayPayment\Templating\TwigExtension;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Newsletter\Exception\SalesChannelDomainNotFoundException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class OrderHelper {

    /**
     * @param OrderEntity $order
     * @param string $url
     * @param array $terms
     * @param string $productType
     * @param string $webhookToken
     * @param SalesChannelContext $salesChannelContext
     * @param SystemConfigService $configService
     * @return array
     */
	public static function prepareOrderData(
        OrderEntity $order,
        string $url,
        array $terms,
        string $productType,
        string $webhookToken,
        SalesChannelContext $salesChannelContext,
        SystemConfigService $configService
    ): array
    {

        $templateFunctions = new TwigExtension($configService);

		$requestData = array(
			'amount_format'       => 'DECIMAL',
			'amount'              => [
				'amount'   => self::getFormattedPrice( $templateFunctions->heidiAddFee( $order->getAmountTotal() ) ),
				'currency' => $order->getCurrency()->getIsoCode(),
            ],
			'redirect_urls'       => [
				'success_url'     => $url.'&status=heidipay_success',
				'failure_url'     => $url.'&status=heidipay_fail',
            ],
            'webhooks'            => [
                'mapping_scheme'  => 'SHOPWARE',
                'status_url'      => self::getStorefrontUrl($salesChannelContext).'/heidipay/webhook/'.$order->getId().'/status',
                'token'           => $webhookToken,
            ],
			'origination_channel' => 'ecommerce',
			'financial_product_type' => $productType,
			'order_reference'     => $order->getOrderNumber(),
			'customer_details'    => [
				'email_address'   => $order->getOrderCustomer()->getEmail(),
				'first_name'      => $order->getBillingAddress()->getFirstName(),
				'last_name'       => $order->getBillingAddress()->getLastName(),
            ],
			'billing_address'     => [
				'zip_code'        => $order->getBillingAddress()->getZipcode(),
				'city'            => $order->getBillingAddress()->getCity(),
				'address_line_1'  => $order->getBillingAddress()->getStreet(),
				'country_code'    => $order->getBillingAddress()->getCountry()->getIso3(),
            ],
			'products'            => self::getProducts( $order, $terms, $salesChannelContext, $configService ),
		);

		if ( $order->getBillingAddress()->getAdditionalAddressLine2() ) {
			$requestData['billing_address']['address_line_2'] = $order->getBillingAddress()->getAdditionalAddressLine2();
		}

		return $requestData;
	}

    /**
     * @param OrderEntity $order
     * @param array $terms
     * @param SalesChannelContext $salesChannelContext
     * @param SystemConfigService $configService
     * @return array
     */
	public static function getProducts( OrderEntity $order, array $terms, SalesChannelContext $salesChannelContext, SystemConfigService $configService ): array
    {
        $templateFunctions = new TwigExtension($configService);
		$items = [];
		foreach ( $order->getLineItems()->getElements() as $item ) {
            $widget_min_instalment = $configService->get('Go2FlowHeidiPayPayment.settings.heidiPromotionWidgetMinInstalment', $salesChannelContext->getSalesChannelId());
            $minimumInstalmentPrice = (! empty($widget_min_instalment) ? (float) $widget_min_instalment : 1);
            $availableTerms = $templateFunctions->getAvailableTerms($terms, $templateFunctions->heidiAddFee( $order->getAmountTotal() ), $minimumInstalmentPrice);

			$tmp = [
				'sku'           => $item->getPayload()['productNumber'] ?? '',
				'quantity'      => $item->getQuantity(),
				'name'          => $item->getLabel(),
				'price'         => self::getFormattedPrice( $item->getTotalPrice() ),
                'allowed_terms' => $availableTerms
			];

            $items[] = $tmp;
		}

		return $items;
	}

	/**
	 * Convert price to a valid format 123456.78
	 *
	 * @param float|int|string $price
	 *
	 * @return string
	 */
	public static function getFormattedPrice(float|int|string $price ): string
    {
		return number_format( $price, 2, '.', '' );
	}

    /**
     * @param SalesChannelContext $salesChannelContext
     * @return string
     */
    public static function getStorefrontUrl(SalesChannelContext $salesChannelContext): string
    {
        $salesChannel = $salesChannelContext->getSalesChannel();

        $domains = $salesChannel->getDomains();
        if ($domains === null) {
            throw new SalesChannelDomainNotFoundException($salesChannel);
        }

        $domain = $domains->first();
        if ($domain === null) {
            throw new SalesChannelDomainNotFoundException($salesChannel);
        }

        return $domain->getUrl();
    }
}
