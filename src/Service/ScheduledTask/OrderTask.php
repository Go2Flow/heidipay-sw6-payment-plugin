<?php declare(strict_types=1);

namespace Go2FlowHeidiPayPayment\Service\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class OrderTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'heidipay.order_task';
    }

    public static function getDefaultInterval(): int
    {
        return (60*15); // 15 minutes in seconds
    }
}
