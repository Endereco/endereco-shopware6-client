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
 * This one is for SW Version < 6.4.11.0
 * @RouteScope(scopes={"api"})
 *
 * This one is for SW Version >= 6.4.11.0
 * @Route(defaults={"_routeScope"={"api"}})
 */
class ApiTestController extends AbstractController
{
    private EnderecoService $enderecoService;

    public function __construct(EnderecoService $enderecoService)
    {
        $this->enderecoService = $enderecoService;
    }

    public function checkAPICredentials(Request $request): JsonResponse
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
