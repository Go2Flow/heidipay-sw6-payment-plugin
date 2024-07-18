<?php declare(strict_types=1);
namespace Go2FlowHeidiPayPayment\Core\Content\WebhookTokens;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class WebhookTokenDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'g2f_heidipay_webhook_token';
    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return WebhookTokenEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new StringField('random', 'random'))->addFlags(new Required()),
            (new StringField('action', 'action'))->addFlags(new Required()),
            (new FkField('order_id', 'orderId', OrderDefinition::class))->addFlags(new Required()),
        ]);
    }
}