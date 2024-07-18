<?php declare(strict_types=1);

namespace Go2FlowHeidiPayPayment\Subscriber;

use Go2FlowHeidiPayPayment\Handler\PaymentHandler;
use Go2FlowHeidiPayPayment\Installer\Modules\PaymentMethodInstaller;
use Go2FlowHeidiPayPayment\Service\HeidiPayApiService;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderDeliverySubscriber implements EventSubscriberInterface
{

    private EntityRepository $orderDeliveryRepository;
    private HeidiPayApiService $heidiPayApiService;

    public function __construct(
        EntityRepository  $orderDeliveryRepository,
        HeidiPayApiService $heidiPayApiService
    )
    {
        $this->orderDeliveryRepository = $orderDeliveryRepository;
        $this->heidiPayApiService = $heidiPayApiService;
    }

    /**
     * @return array<mixed>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'state_machine.order_delivery.state_changed' => 'onOrderDeliveryChanged',
        ];
    }

    /**
     * @param StateMachineStateChangeEvent $event
     */
    public function onOrderDeliveryChanged(StateMachineStateChangeEvent $event): void
    {
        if (
            $event->getTransitionSide() !== StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER
            || $event->getStateName() !== OrderDeliveryStates::STATE_SHIPPED
        ) {
            return;
        }
        $order = $this->getOrderByDelivery($event->getTransition()->getEntityId(), $event->getContext());
        if ($order) {
            $externalId = $this->getExternalIdFromOrder($order);
            if ($externalId) {
                $this->heidiPayApiService->confirmDelivery($externalId, $order->getSalesChannelId());
            }
        }
    }

    private function getOrderByDelivery(string $deliveryId, Context $context): ?OrderEntity
    {
        $criteria = new Criteria([$deliveryId]);
        $criteria->addAssociation('order.transactions.paymentMethod');
        $criteria->addFilter(
            new EqualsAnyFilter(
                'order.transactions.paymentMethod.technicalName',
                [
                    (PaymentHandler::PAYMENT_METHOD_PREFIX . PaymentMethodInstaller::HEIDIPAY_METHOD),
                    (PaymentHandler::PAYMENT_METHOD_PREFIX . PaymentMethodInstaller::HEIDIPAY_CREDIT_METHOD)
                ]
            )
        );
        $criteria->getAssociation('order.transactions')
            ->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        /** @var OrderDeliveryEntity $delivery */
        $delivery = $this->orderDeliveryRepository->search($criteria, $context)->first();
        return $delivery?->getOrder();
    }
    private function getExternalIdFromOrder(OrderEntity $order): ?string
    {
        $transaction = $order->getTransactions()->first();
        if (!$transaction || !array_key_exists('external_contract_uuid', $transaction->getCustomFields())) {
            return null;
        }
        return $transaction->getCustomFields()['external_contract_uuid'];
    }
}
