<?php declare(strict_types=1);

namespace Go2FlowHeidiPayPayment\Core\Content\WebhookTokens;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void               add(WebhookTokenEntity $entity)
 * @method void               set(string $key, WebhookTokenEntity $entity)
 * @method WebhookTokenEntity[]    getIterator()
 * @method WebhookTokenEntity[]    getElements()
 * @method WebhookTokenEntity|null get(string $key)
 * @method WebhookTokenEntity|null first()
 * @method WebhookTokenEntity|null last()
 */
class WebhookTokenCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return WebhookTokenEntity::class;
    }
}