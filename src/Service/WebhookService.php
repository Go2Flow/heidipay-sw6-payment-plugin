<?php declare(strict_types=1);

namespace Go2FlowHeidiPayPayment\Service;

use Go2FlowHeidiPayPayment\Core\Content\WebhookTokens\WebhookTokenEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

class WebhookService
{
    const ACTION_STATUS = 'status';
    private EntityRepository $webhookTokenRepository;

    /**
     *
     */
    public function __construct(
        EntityRepository $webhookTokenRepository,
    )
    {
        $this->webhookTokenRepository = $webhookTokenRepository;
    }

    public function validateToken(string $token, string $orderId, Context $context, string $action = self::ACTION_STATUS): bool
    {
        if ($token === $orderId) {
            return true;
        }
        $random = $this->loadTokenRandom($orderId, $action, $context);
        return ($random && $this->createToken($orderId, $context, $action, $random) === $token);
    }

    public function storeToken(string $orderId, string $action, string $random, Context $context): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'orderId' => $orderId,
            'action' => $action,
            'random' => $random,
        ];
        $this->webhookTokenRepository->create([$data], $context);
    }

    public function loadTokenRandom(string $orderId, string $action, Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(
            new EqualsFilter('orderId', $orderId)
        );
        $criteria->addFilter(
            new EqualsFilter('action', $action)
        );
        /** @var WebhookTokenEntity $webhookTokenEntity */
        $webhookTokenEntity = $this->webhookTokenRepository->search($criteria, $context)->first();
        return $webhookTokenEntity?->getRandom();
    }

    public function createToken(string $orderId, Context $context, string $action = self::ACTION_STATUS, ?string $random = null): string
    {
        if (!$random) {
            $random = self::generateRandomString();
            $this->storeToken($orderId, $action, $random, $context);
        }
        return hash('sha256', ($orderId.$random.$action));
    }

    public static function generateRandomString(int $length = 26): string
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}
