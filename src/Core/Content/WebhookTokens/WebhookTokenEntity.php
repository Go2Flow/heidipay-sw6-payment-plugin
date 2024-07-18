<?php declare(strict_types=1);

namespace Go2FlowHeidiPayPayment\Core\Content\WebhookTokens;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class WebhookTokenEntity extends Entity
{
    use EntityIdTrait;

    protected string $random;
    protected string $action;
    protected string $orderId;

    public function getRandom(): string
    {
        return $this->random;
    }

    public function setRandom(string $random): void
    {
        $this->random = $random;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function setOrderId(string $orderId): void
    {
        $this->orderId = $orderId;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): void
    {
        $this->action = $action;
    }
}