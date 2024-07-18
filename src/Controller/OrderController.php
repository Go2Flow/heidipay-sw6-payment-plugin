<?php

declare(strict_types=1);

namespace Go2FlowHeidiPayPayment\Controller;

use Go2FlowHeidiPayPayment\Service\OrderService;
use Symfony\Component\Routing\Attribute\Route;

use Shopware\Core\Framework\Context;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

#[Route(defaults: ['_routeScope' => ['api']])]
class OrderController extends AbstractController
{
    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;
    private OrderService $orderService;

    /**
     * @param ContainerInterface $container
     * @param OrderService $orderService
     * @param LoggerInterface $logger
     */
    public function __construct(
        ContainerInterface $container,
        OrderService $orderService,
        LoggerInterface    $logger
    )
    {
        $this->setContainer($container);
        $this->logger = $logger;
        $this->orderService = $orderService;
    }

    /**
     * @param Request $request
     * @param Context $context
     * @return JsonResponse
     */
    #[Route(path: '/api/_action/heidipay_order_service/refund', name: 'api.action.heidipay_order_service.refund', methods: ['POST'])]
    public function fullRefund(Request $request, Context $context): JsonResponse
    {
        $response = $this->orderService->fullRefund($request->get('transaction'), $context);
        return new JsonResponse(['success' => $response]);
    }

}
