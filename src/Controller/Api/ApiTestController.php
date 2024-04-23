<?php

namespace Endereco\Shopware6Client\Controller\Api;

use Endereco\Shopware6Client\Service\EnderecoService;
use Shopware\Core\Framework\Context;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use GuzzleHttp\Client;

/**
 * ApiTestController handles API requests for testing API credentials.
 *
 * @author Ilja Weber
 * @package Endereco\Shopware6Client\Controller\Api
 *
 * @Route(defaults={"_routeScope"={"api"}})
 */
class ApiTestController extends AbstractController
{
    /**
     * @var EnderecoService
     */
    private EnderecoService $enderecoService;

    /**
     * ApiTestController constructor.
     *
     * @param EnderecoService $enderecoService
     */
    public function __construct(EnderecoService $enderecoService)
    {
        $this->enderecoService = $enderecoService;
    }

    /**
     * Check API credentials by making a request using provided credentials.
     *
     * @param Request $request The incoming HTTP request.
     * @param Context $context Context data of the sales channel/user.
     *
     * @return JsonResponse Returns a JsonResponse with a 'success' key indicating whether the credentials are valid.
     */
    public function checkAPICredentials(Request $request, Context $context): JsonResponse
    {
        $apiKey = (string) $request->request->get('EnderecoShopware6Client.config.enderecoApiKey', '');
        $endpointUrl = (string) $request->request->get('EnderecoShopware6Client.config.enderecoRemoteUrl', '');

        // Check if the API key and endpoint URL are not empty
        if (empty(trim($apiKey)) || empty(trim($endpointUrl))) {
            return new JsonResponse(['success' => false]);
        }

        // Call the checkApiCredentials method of the EnderecoService
        $result = $this->enderecoService->checkApiCredentials($endpointUrl, $apiKey, $context);

        return new JsonResponse(['success' => $result]);
    }
}
