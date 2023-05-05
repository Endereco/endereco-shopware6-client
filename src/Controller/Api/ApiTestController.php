<?php

namespace Endereco\Shopware6Client\Controller\Api;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use GuzzleHttp\Client;

/**
 * This one is for SW Version < 6.4.11.0
 * @RouteScope(scopes={"api"})
 *
 * This one is for SW Version >= 6.4.11.0
 * @Route(defaults={"_routeScope"={"api"}})
 */
class ApiTestController extends AbstractController
{
    /** @var LoggerInterface */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function checkAPICredentials(Request $request): JsonResponse
    {
        $apiKey = $request->get('EnderecoShopware6Client.config.enderecoApiKey', '');
        $endpointUrl = $request->get('EnderecoShopware6Client.config.enderecoRemoteUrl', '');
        if (empty(trim($apiKey)) || empty(trim($endpointUrl))) {
            return new JsonResponse(['success' => false]);
        }

        $success = false;

        // Check the connection.
        $readinessCheckRequest = array(
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'readinessCheck',
        );
        $dataString = json_encode($readinessCheckRequest);

        if (empty(trim($apiKey))) {
            return new JsonResponse(['success' => $success]);
        }

        if (empty(trim($endpointUrl))) {
            return new JsonResponse(['success' => $success]);
        }

        // I dont want to refactor this into services at this point. In 1.4.0 this logic
        // moved to EnderecoService.
        $enderecoAgentInfo = 'Endereco Shopware6 Client v1.3.3';
        $guzzleClient = new Client(['timeout' => 3.0, 'connection_timeout' => 2.0]);
        try {
            $response = $guzzleClient->post(
                $endpointUrl,
                array(
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'X-Auth-Key' => $apiKey,
                        'X-Transaction-Id' => 'not_required',
                        'X-Transaction-Referer' => $_SERVER['HTTP_REFERER'],
                        'X-Agent' => $enderecoAgentInfo,
                    ],
                    'body' => $dataString
                )
            );
            $status = json_decode($response->getBody(), true);
            if ('ready' === $status['result']['status']) {
                $success = true;
            } else {
                $this->logger->warning("Credentials test failed", ['responseFromEndereco' => json_encode($status)]);
            }
        } catch (\Exception $e) {
            $success = false;
            $this->logger->warning("Credentials test failed", ['error' => $e->getMessage()]);
        }

        return new JsonResponse(['success' => $success]);
    }
}
