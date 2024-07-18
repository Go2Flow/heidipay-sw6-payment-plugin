<?php

declare(strict_types=1);

namespace Go2FlowHeidiPayPayment\Controller;

use Go2FlowHeidiPayPayment\Service\HeidiPayApiService;
use Symfony\Component\Routing\Attribute\Route;

use Shopware\Core\Framework\Context;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

#[Route(defaults: ['_routeScope' => ['api']])]
class SettingsController extends AbstractController
{
    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * @var HeidiPayApiService|null
     */
    protected ?HeidiPayApiService $heidiPayApiService;

    /**
     * @param ContainerInterface $container
     * @param HeidiPayApiService $heidiPayApiService
     * @param LoggerInterface $logger
     */
    public function __construct(
        ContainerInterface $container,
        HeidiPayApiService $heidiPayApiService,
        LoggerInterface    $logger
    )
    {
        $this->setContainer($container);
        $this->heidiPayApiService = $heidiPayApiService;
        $this->logger = $logger;
    }

    /**
     * @param Request $request
     * @param Context $context
     * @return JsonResponse
     */
    #[Route(path: '/api/_action/heidipay_settings_service/validate-api-credentials', name: 'api.action.heidipay_settings_service.validate.api.credentials', methods: ['POST'])]
    public function validateApiCredentials(Request $request, Context $context): JsonResponse
    {
        $error = false;

        $merchant_key = $request->get('merchant_key', []);

        try {

            $token = $this->heidiPayApiService->testAuthTransactionToken($merchant_key);

            if(!$token) {
                $error = true;
            }

        } catch (\Exception $e) {
            $error = $e->getMessage();
        }

        return new JsonResponse(['credentialsValid' => !$error, 'error' => $error]);

    }

}
