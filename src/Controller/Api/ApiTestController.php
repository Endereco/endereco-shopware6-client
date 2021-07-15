<?php

namespace Endereco\Shopware6Client\Controller\Api;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use GuzzleHttp\Client;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * @RouteScope(scopes={"administration"})
 */
class ApiTestController extends AbstractController
{
    /** @var LoggerInterface */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @Route("/api/_action/endereco-shopware6-client/verify", name="api.api-test.check")
     */
    public function check(Request $request): JsonResponse
    {
        return $this->checkAPICredetials($request);
    }

    /**
     * @Route("/api/v{version}/_action/endereco-shopware6-client/verify", name="api.api-test.checkOld")
     */
    public function checkOld(Request $request): JsonResponse
    {
        return $this->checkAPICredetials($request);
    }

    private function checkAPICredetials(Request $request): JsonResponse
    {
        $apiKey = $request->get('EnderecoShopware6Client.config.enderecoApiKey');
        $endpointUrl = $request->get('EnderecoShopware6Client.config.enderecoRemoteUrl');

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

        $enderecoAgentInfo = 'Endereco Shopware6 Client v1.0.0';
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
