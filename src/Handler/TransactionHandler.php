<?php

declare(strict_types=1);

namespace Go2FlowHeidiPayPayment\Handler;

use Go2FlowHeidiPayPayment\Helper\Transaction;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;

class TransactionHandler
{
    /**
     * @var OrderTransactionStateHandler
     */
    protected OrderTransactionStateHandler $transactionStateHandler;

    /**
     * @var ContainerInterface
     */
    protected ContainerInterface $container;

    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * @param OrderTransactionStateHandler $transactionStateHandler
     * @param ContainerInterface $container
     * @param $logger
     */
    public function __construct(
        OrderTransactionStateHandler $transactionStateHandler,
        ContainerInterface $container,
        $logger
    ) {
        $this->transactionStateHandler = $transactionStateHandler;
        $this->container = $container;
        $this->logger = $logger;
    }

    /**
     * @param $salesChannelContext
     * @param $transactionId
     * @param $details
     */
    public function saveTransactionCustomFields($salesChannelContext, $transactionId, $details): void
    {
        $transactionRepo = $this->container->get('order_transaction.repository');
        $transactionRepo->upsert([[
            'id' => $transactionId,
            'customFields' => $details
        ]], $salesChannelContext->getContext());
    }
    public function getStateMachineState(string $stateId, $context): ?StateMachineStateEntity
    {
        $repo = $this->container->get('state_machine_state.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $stateId));

        return $repo->search($criteria, $context)->first();
    }

    /**
     * @param OrderTransactionEntity $orderTransaction
     * @param string $heidiPayTransactionStatus
     * @param Context $context
     */
    public function handleTransactionStatus(
        OrderTransactionEntity $orderTransaction,
        string $heidiPayTransactionStatus,
        Context $context
    ): void
    {
        $stateMachineState = $orderTransaction->getStateMachineState();
        if (!$stateMachineState) {
            $stateMachineState = $this->getStateMachineState($orderTransaction->getStateId(), $context);
        }
        $stateName = $stateMachineState->getTechnicalName();
        switch ($heidiPayTransactionStatus) {
            case Transaction::CONFIRMED:
                if (OrderTransactionStates::STATE_PAID === $stateName) break;
                $this->transactionStateHandler->paid($orderTransaction->getId(), $context);
                break;
            case Transaction::WAITING:
                if (in_array($stateName, [OrderTransactionStates::STATE_IN_PROGRESS, OrderTransactionStates::STATE_PAID])) break;
                $this->transactionStateHandler->process($orderTransaction->getId(), $context);
                break;
            case Transaction::REFUNDED:
                if (OrderTransactionStates::STATE_REFUNDED === $stateName) break;
                $this->transactionStateHandler->refund($orderTransaction->getId(), $context);
                break;
            case Transaction::PARTIALLY_REFUNDED:
                if (OrderTransactionStates::STATE_PARTIALLY_REFUNDED === $stateName) break;
                $this->transactionStateHandler->refundPartially($orderTransaction->getId(), $context);
                break;
            case Transaction::CANCELLED:
            case Transaction::DECLINED:
            case Transaction::EXPIRED:
                if (in_array($stateName, [OrderTransactionStates::STATE_CANCELLED, OrderTransactionStates::STATE_PAID])) break;
                $this->transactionStateHandler->cancel($orderTransaction->getId(), $context);
                break;
            case Transaction::ERROR:
                if (in_array($stateName, [OrderTransactionStates::STATE_FAILED, OrderTransactionStates::STATE_PAID])) break;
                $this->transactionStateHandler->fail($orderTransaction->getId(), $context);
                break;
        }
    }
}
