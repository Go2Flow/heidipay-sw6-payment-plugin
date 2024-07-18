<?php

declare(strict_types=1);

namespace Go2FlowHeidiPayPayment\Service\Command;

use DateInterval;
use DateTime;
use Go2FlowHeidiPayPayment\Service\OrderService;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Attribute\AsCommand;
#[AsCommand(
    name: 'heidipay:orders-check-status',
    description: 'Sync Order Status with Heidipay',
)]
class HeidipayCheckOrderStatus extends Command
{
    private OrderService $orderService;

    /**
     * @param OrderService $orderService
     * @param string|null $name
     */
    public function __construct(
        OrderService $orderService,
        string $name = null
    )
    {
        $this->orderService = $orderService;

        parent::__construct($name);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
//        $orders = $this->orderService->getOrders();
//        foreach ($orders as $order) {
//            $status = $order->getTransactions()->first()->getStateMachineState()->getTechnicalName();
//        }
        $iterator = $this->orderService->getOrdersIterator();
        while (($result = $iterator->fetch()) !== null) {
            $this->orderService->workOrders($result->getEntities());
        }

        return 0;
    }

}