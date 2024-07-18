<?php

declare(strict_types=1);

namespace Go2FlowHeidiPayPayment\Controller;

use Go2FlowHeidiPayPayment\Handler\TransactionHandler;
use Go2FlowHeidiPayPayment\Helper\Transaction;
use Go2FlowHeidiPayPayment\Service\WebhookService;
use Shopware\Core\Checkout\Payment\PaymentException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class WebhookController extends AbstractController
{
    private EntityRepository $orderRepository;
    private TransactionHandler $transactionHandler;
    private WebhookService $webhookService;

    public function __construct(
        ContainerInterface $container,
        EntityRepository $orderRepository,
        TransactionHandler $transactionHandler,
        WebhookService $webhookService,
    ) {
        $this->orderRepository = $orderRepository;
        $this->transactionHandler = $transactionHandler;
        $this->webhookService = $webhookService;
        $this->setContainer($container);
    }

    /**
     * @deprecated remove in version 2.0.0
     * @param Request $request
     * @param Context $context
     * @return JsonResponse
     */
    #[Route(path: '/heidipay/webhook/status', name: 'frontend.heidipay.webhook.create', methods: ['POST'])]
    public function statusOld(Request $request, Context $context)
    {
        return $this->status($request->get('token', ''), $request, $context);
    }

    /**
     * @param string $orderId
     * @param Request $request
     * @param Context $context
     * @return JsonResponse
     */
    #[Route(path: '/heidipay/webhook/{orderId}/status', name: 'frontend.heidipay.webhook.status', methods: ['POST'])]
    public function status(string $orderId, Request $request, Context $context)
    {
        $token = $request->get('token', null);
        $status = $request->get('status', null);
        if ($orderId && $status && $this->webhookService->validateToken($token, $orderId, $context)) {
            /** @var OrderEntity $order */
            $criteria = new Criteria([$orderId]);
            $criteria->addAssociation('transactions');
            $criteria->addAssociation('lineItems');
            $criteria->getAssociation('transactions')->addSorting(new FieldSorting('createdAt'));
            /** @var OrderEntity|null $order */
            $order = $this->orderRepository->search($criteria, $context)->first();

            if ($order === null) {
                throw OrderException::orderNotFound($orderId);
            }

            $transactionCollection = $order->getTransactions();
            if ($transactionCollection === null) {
                throw PaymentException::invalidOrder($orderId);
            }

            $transaction = $transactionCollection->last();
            if ($transaction === null) {
                throw PaymentException::invalidOrder($orderId);
            }
            $status = Transaction::mapStatus($status);
            $this->transactionHandler->handleTransactionStatus($transaction, $status, $context);
        } else {
            throw PaymentException::invalidOrder($orderId);
        }
        return new JsonResponse(['success' => true]);
    }
}