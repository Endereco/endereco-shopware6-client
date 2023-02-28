<?php

namespace Endereco\Shopware6Client\Controller\Api;

use Endereco\Shopware6Client\Service\EnderecoService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * @RouteScope(scopes={"administration"})
 */
class ApiTestController extends AbstractController
{
    private EnderecoService $enderecoService;

    public function __construct(EnderecoService $enderecoService)
    {
        $this->enderecoService = $enderecoService;
    }

    /**
     * @Route("/api/_action/endereco-shopware6-client/verify", name="api.api-test.check")
     */
    public function check(Request $request, Context  $context): JsonResponse
    {
        return $this->checkAPICredentials($request, $context);
    }

    /**
     * @Route("/api/v{version}/_action/endereco-shopware6-client/verify", name="api.api-test.checkOld")
     */
    public function checkOld(Request $request, Context  $context): JsonResponse
    {
        return $this->checkAPICredentials($request, $context);
    }

    private function checkAPICredentials(Request $request, Context  $context): JsonResponse
    {
        $apiKey = $request->get('EnderecoShopware6Client.config.enderecoApiKey', '');
        $endpointUrl = $request->get('EnderecoShopware6Client.config.enderecoRemoteUrl', '');
        if (empty(trim($apiKey)) || empty(trim($endpointUrl))) {
            return new JsonResponse(['success' => false]);
        }
        return  new JsonResponse([
            'success' => $this->enderecoService->checkApiCredentials($endpointUrl, $apiKey, $context)
        ]);
    }
}
