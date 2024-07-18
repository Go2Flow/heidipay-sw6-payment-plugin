<?php declare(strict_types=1);

namespace Go2FlowHeidiPayPayment\Service\ScheduledTask;

use Go2FlowHeidiPayPayment\Handler\TransactionHandler;
use Go2FlowHeidiPayPayment\Service\OrderService;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
#[AsMessageHandler(handles: OrderTask::class)]
class OrderTaskHandler extends ScheduledTaskHandler
{

    /**
     * @var Logger
     */
    private Logger $logger;
    private OrderService $orderService;

    /**
     * @param EntityRepository $scheduledTaskRepository
     * @param OrderService $orderService
     * @param string|null $name
     */
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        OrderService $orderService,
        string $name = null
    )
    {
        $this->scheduledTaskRepository = $scheduledTaskRepository;
        $this->orderService = $orderService;

        $logger = new Logger('heidipay-status-cronjob');
        $logger->pushHandler(new StreamHandler(__DIR__ . '/heidipay-status-cronjob.log'));
        $this->logger = $logger;
    }

    public function run(): void
    {
        $iterator = $this->orderService->getOrdersIterator();
        while (($result = $iterator->fetch()) !== null) {
            $this->logger->info('Heidipay: working on '.$result->getTotal().' Orders');
            $this->orderService->workOrders($result->getEntities());
        }
    }
}
