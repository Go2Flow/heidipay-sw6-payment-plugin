<?php

declare(strict_types=1);

namespace Go2FlowHeidiPayPayment\Handler;

use Go2FlowHeidiPayPayment\Helper\Transaction;
use Go2FlowHeidiPayPayment\Service\HeidiPayApiService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Payment\PaymentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;

class PaymentHandler implements AsynchronousPaymentHandlerInterface
{

    const PAYMENT_METHOD_PREFIX = 'heidipay_';
    const BASE_URL = 'https://origination.heidipay.com';
    const SANDBOX_BASE_URL = 'https://sandbox-origination.heidipay.com';

    /**
     * @var OrderTransactionStateHandler
     */
    protected OrderTransactionStateHandler $transactionStateHandler;

    /**
     * @var ContainerInterface
     */
    protected ContainerInterface $container;

    /**
     * @var HeidiPayApiService
     */
    protected HeidiPayApiService $heidiPayApiService;

    /**
     * @var TransactionHandler
     */
    protected TransactionHandler $transactionHandler;

    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * @param OrderTransactionStateHandler $transactionStateHandler
     * @param ContainerInterface $container
     * @param HeidiPayApiService $heidiPayApiService
     * @param TransactionHandler $transactionHandler
     * @param $logger
     */
    public function __construct(
        OrderTransactionStateHandler $transactionStateHandler,
        ContainerInterface $container,
        HeidiPayApiService $heidiPayApiService,
        TransactionHandler $transactionHandler,
        $logger
    ) {
        $this->transactionStateHandler = $transactionStateHandler;
        $this->container = $container;
        $this->heidiPayApiService = $heidiPayApiService;
        $this->transactionHandler = $transactionHandler;
        $this->logger = $logger;
    }

    /**
     * Redirects to the payment page
     *
     * @param AsyncPaymentTransactionStruct $transaction
     * @param RequestDataBag $dataBag
     * @param SalesChannelContext $salesChannelContext
     * @return RedirectResponse
     */
    public function pay(AsyncPaymentTransactionStruct $transaction, RequestDataBag $dataBag, SalesChannelContext $salesChannelContext): RedirectResponse
    {
        $orderTransaction = $transaction->getOrderTransaction();
        $order = $transaction->getOrder();
        $totalAmount = $orderTransaction->getAmount()->getTotalPrice();
        $transactionId = $orderTransaction->getId();
        $productRepository = $this->container->get('product.repository');

        // Workaround if amount is 0
        if ($totalAmount <= 0) {
            $redirectUrl = $transaction->getReturnUrl();
            return new RedirectResponse($redirectUrl);
        }

        // Create HeidiPay Link for checkout and redirect user
        try {
            $heidiPayGateway = $this->heidiPayApiService->processPayment(
                $order,
                $transaction->getReturnUrl(),
                $productRepository,
                $salesChannelContext
            );

            $this->transactionHandler->saveTransactionCustomFields(
                $salesChannelContext,
                $transactionId,
                [ 'external_contract_uuid' => $heidiPayGateway['external_contract_uuid'] ]
            );

            $redirectUrl = $heidiPayGateway['redirect_url'] ;
        } catch (\Exception $e) {
            throw PaymentException::asyncProcessInterrupted(
                $transactionId,
                'An error occurred during the communication with external payment gateway' . PHP_EOL . $e->getMessage()
            );
        }

        return new RedirectResponse($redirectUrl);
    }

    /**
     * @param AsyncPaymentTransactionStruct $transaction
     * @param Request $request
     * @param SalesChannelContext $salesChannelContext
     */
    public function finalize(AsyncPaymentTransactionStruct $transaction, Request $request, SalesChannelContext $salesChannelContext): void
    {
        $context = $salesChannelContext->getContext();

        $heidiPayTransactionStatus = OrderTransactionStates::STATE_OPEN;

        $orderTransaction = $transaction->getOrderTransaction();
        $stateMachineState = $orderTransaction->getStateMachineState();
        if (!$stateMachineState) {
            $stateMachineState = $this->transactionHandler->getStateMachineState($orderTransaction->getStateId(), $salesChannelContext->getContext());
        }


        $customFields = $orderTransaction->getCustomFields();
        $externalContractUuid = $customFields['external_contract_uuid'];
        $transactionId = $orderTransaction->getId();
        $totalAmount = $orderTransaction->getAmount()->getTotalPrice();

        if ($totalAmount <= 0) {
            if (OrderTransactionStates::STATE_PAID === $stateMachineState->getTechnicalName()) return;
            $this->transactionStateHandler->paid($orderTransaction->getId(), $context);
            return;
        }

        if (empty($transactionId)) {
            throw PaymentException::customerCanceled(
                $transactionId,
                'Customer canceled the payment on the HeidiPay page'
            );
        }

        $orderStatus = $this->heidiPayApiService->checkOrderStatus( $externalContractUuid, $salesChannelContext );



        if ( ( !$externalContractUuid || !$orderStatus ) && $totalAmount > 0) {
            throw PaymentException::customerCanceled(
                $transactionId,
                'Customer canceled the payment on the HeidiPay page'
            );
        }

        if ($totalAmount <= 0 || $orderStatus == true) {
            $heidiPayTransactionStatus = Transaction::CONFIRMED;
        }

        $this->transactionHandler->handleTransactionStatus($orderTransaction, $heidiPayTransactionStatus, $context);

        if (!in_array($heidiPayTransactionStatus, [Transaction::CANCELLED, Transaction::DECLINED, Transaction::EXPIRED, Transaction::ERROR])){
            return;
        }
        throw PaymentException::customerCanceled(
            $transactionId,
            'Customer canceled the payment on the HeidiPay page'
        );
    }

    /**
     * @param OrderEntity $order
     * @param $totalAmount
     * @param $salesChannelContext
     * @return array
     */
    private function collectBasketData(OrderEntity $order, $totalAmount, $salesChannelContext):array
    {
        // Collect basket data
        $basketTotal = 0;
        $basket = [];

        $lineItemElements = [];
        if ($order->getLineItems()) {
            $lineItemElements = $order->getLineItems()->getElements();
        }
        foreach ($lineItemElements as $item) {
            $unitPrice = $item->getUnitPrice();
            $quantity = $item->getQuantity();

            $basket[] = [
                'name' => $item->getLabel(),
                'description' => $item->getDescription(),
                'quantity' => $item->getQuantity(),
                'amount' => $unitPrice * 100,
                'sku' => $item->getPayload()['productNumber'] ?? '',
            ];
            $basketTotal += $unitPrice * $quantity;
        }

        $shippingMethodRepo = $this->container->get('shipping_method.repository');

        $deliveryElements = [];
        if ($order->getDeliveries()) {
            $deliveryElements = $order->getDeliveries()->getElements();
        }
        foreach ($deliveryElements as $delivery) {
            $shippingCriteria = (new Criteria())->addFilter(
                new EqualsFilter('id', $delivery->getShippingMethodId())
            );
            $shippingMethod = $shippingMethodRepo->search($shippingCriteria, $salesChannelContext->getContext())->first();

            $unitPrice = $delivery->getShippingCosts()->getUnitPrice() ;
            $quantity = $delivery->getShippingCosts()->getQuantity();

            $basket[] = [
                'name' => $shippingMethod->getTranslated()['name'] ?: $shippingMethod->getName(),
                'description' => $shippingMethod->getTranslated()['description'] ?: $shippingMethod->getDescription(),
                'quantity' => $quantity,
                'amount' => $unitPrice * 100,
                'sku' => $shippingMethod->getId(),
            ];
            $basketTotal += $unitPrice * $quantity;
        }

        $taxElements = [];
        if ($order->getPrice() && $order->getPrice()->getCalculatedTaxes()) {
            $taxElements = $order->getPrice()->getCalculatedTaxes();
        }
        if ($order->getTaxStatus() === CartPrice::TAX_STATE_NET) {
            foreach ($taxElements as $tax) {
                $unitPrice = $tax->getTax();
                $quantity = 1;
                $basket[] = [
                    'name' => 'Tax ' . $tax->getTaxRate() . '%',
                    'quantity' => $quantity,
                    'amount' => $unitPrice * 100,
                ];
                $basketTotal += $unitPrice;
            }
        }

        if ($totalAmount !== $basketTotal) {
            return [];
        }

        return $basket;
    }

    /**
     * @param OrderEntity $order
     * @return float
     */
    private function getAverageTaxRate(OrderEntity $order): float
    {
        if (!$order->getPrice() || !$order->getPrice()->getCalculatedTaxes()) {
            return 0;
        }

        $taxRate = 0;
        $finalTaxRate = 0;
        $taxElements = $order->getPrice()->getCalculatedTaxes();

        if (!count($taxElements)) return $finalTaxRate;
        foreach ($taxElements as $tax) {
            $taxRate += $tax->getTaxRate();
        }

        return ($taxRate / count($taxElements));
    }
}
