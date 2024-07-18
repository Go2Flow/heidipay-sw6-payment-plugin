<?php declare(strict_types=1);

namespace Go2FlowHeidiPayPayment\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1708527993 extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1708527993;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `g2f_heidipay_webhook_token` (
    `id` BINARY(16) NOT NULL,
    `order_id` BINARY(16) NOT NULL,
    `random` VARCHAR(255) NOT NULL,
    `action` VARCHAR(255) NOT NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3),
    PRIMARY KEY (`id`),
    KEY `fk.g2f_heidipay_webhook_token.order_id` (`order_id`),
    CONSTRAINT `fk.g2f_heidipay_webhook_token.order_id` FOREIGN KEY (`order_id`)
        REFERENCES `order` (`id`) 
        ON DELETE CASCADE
)
    ENGINE = InnoDB
    DEFAULT CHARSET = utf8mb4
    COLLATE = utf8mb4_unicode_ci;
SQL;
        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
