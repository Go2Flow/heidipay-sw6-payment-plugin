<?php declare(strict_types=1);

namespace Go2FlowHeidiPayPayment\Service;

use Go2FlowHeidiPayPayment\Handler\PaymentHandler;
use Go2FlowHeidiPayPayment\Handler\TransactionHandler;
use Go2FlowHeidiPayPayment\Helper\Transaction;
use Go2FlowHeidiPayPayment\Installer\Modules\PaymentMethodInstaller;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

class OrderService
{

    private EntityRepository $orderRepository;
    private HeidiPayApiService $heidiPayApiService;
    private TransactionHandler $transactionHandler;
    private EntityRepository $transactionRepository;

    /**
     * @param EntityRepository $orderRepository
     * @param EntityRepository $transactionRepository
     * @param HeidiPayApiService $heidiPayApiService
     * @param TransactionHandler $transactionHandler
     */
    public function __construct(
        EntityRepository $orderRepository,
        EntityRepository $transactionRepository,
        HeidiPayApiService $heidiPayApiService,
        TransactionHandler $transactionHandler,
    )
    {
        $this->orderRepository = $orderRepository;
        $this->heidiPayApiService = $heidiPayApiService;
        $this->transactionHandler = $transactionHandler;
        $this->transactionRepository = $transactionRepository;
    }

    /**
     * @return RepositoryIterator
     */
    public function getOrdersIterator() :RepositoryIterator
    {
        $criteria = $this->getOrderCriteria();

        return new RepositoryIterator($this->orderRepository, Context::createDefaultContext(), $criteria);
    }

    /**
     * @return OrderCollection
     */
    public function getOrders() :OrderCollection
    {
        $criteria = $this->getOrderCriteria();

        return $this->orderRepository->search($criteria, Context::createDefaultContext())->getEntities();
    }

    /**
     * @return Criteria
     */
    private function getOrderCriteria(): Criteria
    {
        $criteria = new Criteria();
        $criteria->setLimit(10);
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        $criteria->addSorting(new FieldSorting('id', FieldSorting::DESCENDING));
        $criteria->addFilter(
            new EqualsFilter('order.transactions.stateMachineState.technicalName', OrderTransactionStates::STATE_OPEN)
        );
        $criteria->addFilter(
            new EqualsAnyFilter(
                'order.transactions.paymentMethod.technicalName',
                [
                    (PaymentHandler::PAYMENT_METHOD_PREFIX . PaymentMethodInstaller::HEIDIPAY_METHOD),
                    (PaymentHandler::PAYMENT_METHOD_PREFIX . PaymentMethodInstaller::HEIDIPAY_CREDIT_METHOD)
                ]
            )
        );

        $criteria->addAssociations([
            'salesChannel', 'transactions'
        ]);
        $criteria->getAssociation('transactions')->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        return $criteria;
    }

    /**
     * @param OrderCollection $orders
     * @return void
     */
    public function workOrders(OrderCollection $orders): void
    {
        $channels = [];
        foreach ($orders as $order) {
            $channelId = $order->getSalesChannelId();
            if (!in_array($channelId, $channels)) {
                $channels[$channelId] = [];
            }
            $transaction = $order->getTransactions()->first();
            if (
                $transaction
                && $transaction->getCustomFields()
                && array_key_exists('external_contract_uuid', $transaction->getCustomFields())
            ) {
                $channels[$channelId][] = $transaction->getCustomFields()['external_contract_uuid'];
            }
        }
        $newStatusResult = [];
        foreach ($channels as $channelId => $externalIds) {
            foreach ($this->heidiPayApiService->getOrderStatus($externalIds, $channelId) as $statusItem) {
                $newStatusResult[$statusItem['external_contract_uuid']] = $statusItem['status'];
            }
        }
        foreach ($orders as $order) {
            $transaction = $order->getTransactions()->first();
            $externalId = $transaction->getCustomFields()['external_contract_uuid'];
            if (in_array($externalId, $newStatusResult)) {
                $newStatus = $newStatusResult[$externalId];
            }
            if (isset($newStatus)) {
                $this->transactionHandler->handleTransactionStatus(
                    $transaction,
                    Transaction::mapStatus($newStatus),
                    Context::createDefaultContext()
                );
            }
        }
    }

    /**
     * @param string $transactionId
     * @param Context $context
     * @return bool
     */
    public function fullRefund(string $transactionId, Context $context): bool
    {
        $criteria = new Criteria([$transactionId]);
        $criteria->addAssociations([
            'order', 'order.currency'
        ]);
        /** @var OrderTransactionEntity $transaction */
        $transaction = $this->transactionRepository->search($criteria, $context)->first();
        $success = $this->heidiPayApiService->refund(
            $transaction->getCustomFields()['external_contract_uuid'],
            $transaction->getAmount()->getTotalPrice(),
            $transaction->getOrder()->getCurrency()->getIsoCode(),
            $transaction->getOrder()->getSalesChannelId()
        );
        if ($success) {
            $this->transactionHandler->handleTransactionStatus($transaction, Transaction::REFUNDED, $context);
        }
        return $success;
    }
}
