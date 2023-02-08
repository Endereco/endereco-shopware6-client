<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Service;

use Endereco\Shopware6Client\Misc\EnderecoConstants;
use Exception;
use GuzzleHttp\Client;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use GuzzleHttp\Exception\RequestException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Psr\Log\LoggerInterface;
use Throwable;

class EnderecoService
{
    private string $apiKey;

    private string $serviceUrl;

    private Client $httpClient;

    private EntityRepository $pluginRepository;

    private LoggerInterface $logger;

    public function __construct(
        SystemConfigService $systemConfigService,
        EntityRepository    $pluginRepository,
        LoggerInterface     $logger
    ) {
        $this->httpClient = new Client(['timeout' => 3.0, 'connection_timeout' => 2.0]);
        $this->apiKey = $systemConfigService->getString('EnderecoShopware6Client.config.enderecoApiKey') ?? '';
        $this->serviceUrl = $systemConfigService->getString('EnderecoShopware6Client.config.enderecoRemoteUrl') ?? '';
        $this->pluginRepository = $pluginRepository;
        $this->logger = $logger;
    }

    /**
     * @throws Exception
     */
    public function generateTid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * @param array<int, string> $sessionIds
     */
    public function closeSessions(array $sessionIds, Context $context): void
    {
        $anyDoAccounting = false;

        $enderecoAgentInfo = $this->getEnderecoAgentInfo($context);

        foreach ($sessionIds as $sessionId) {
            try {
                $this->httpClient->post(
                    $this->serviceUrl,
                    [
                        'headers' => $this->prepareHeaders($enderecoAgentInfo, $sessionId),
                        'body' => json_encode($this->preparePayload('doAccounting', ['sessionId' => $sessionId]))
                    ]
                );
                $anyDoAccounting = true;
            } catch (RequestException $e) {
                if ($e->hasResponse()) {
                    $response = $e->getResponse();
                    if ($response && 500 <= $response->getStatusCode()) {
                        $this->logger->error('Serverside doAccounting failed', ['error' => $e->getMessage()]);
                    }
                }
            } catch (Throwable $e) {
                $this->logger->error('Serverside doAccounting failed', ['error' => $e->getMessage()]);
            }
        }

        if ($anyDoAccounting) {
            try {
                $this->httpClient->post(
                    $this->serviceUrl,
                    [
                        'headers' => $this->prepareHeaders($enderecoAgentInfo),
                        'body' => json_encode($this->preparePayload('doConversion'))
                    ]
                );
            } catch (RequestException $e) {
                if ($e->hasResponse()) {
                    $response = $e->getResponse();
                    if ($response && 500 <= $response->getStatusCode()) {
                        $this->logger->warning('Serverside doConversion failed', ['error' => $e->getMessage()]);
                    }
                }
            } catch (Throwable $e) {
                $this->logger->warning('Serverside doConversion failed', ['error' => $e->getMessage()]);
            }
        }
    }

    public function splitStreet(string $fullStreet, string $countryCode, Context $context): array
    {
        $payload = $this->preparePayload(
            'splitStreet',
            [
                'formatCountry' => $countryCode,
                'language' => 'de',
                'street' => $fullStreet
            ]
        );

        try {
            $response = $this->httpClient->post(
                $this->serviceUrl,
                array(
                    'headers' => $this->prepareHeaders($this->getEnderecoAgentInfo($context)),
                    'body' => json_encode($payload)
                )
            );


            $result = json_decode($response->getBody()->getContents())->result;
            return [$result->streetName, $result->houseNumber];
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                if ($response && 500 <= $response->getStatusCode()) {
                    $this->logger->error('Serverside splitStreet failed', ['error' => $e->getMessage()]);
                }
            }
        } catch (Throwable $e) {
            $this->logger->error('Serverside splitStreet failed', ['error' => $e->getMessage()]);
        }
        return [$fullStreet, ''];
    }

    public function buildFullStreet(string $street, string $housenumber, string $countryIso): string
    {
        $order =
            EnderecoConstants::STREET_ORDER_MAP[strtolower($countryIso)] ??
            EnderecoConstants::STREET_ORDER_HOUSE_SECOND;
        return $order === EnderecoConstants::STREET_ORDER_HOUSE_FIRST ?
            sprintf('%s %s', $housenumber, $street) :
            sprintf('%s %s', $street, $housenumber);
    }

    private function preparePayload(string $method, array $params = []): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => $method,
            'params' => $params
        ];
    }

    private function prepareHeaders(string $enderecoAgentInfo, string $sessionId = 'not_required'): array
    {
        return [
            'Content-Type' => 'application/json',
            'X-Auth-Key' => $this->apiKey,
            'X-Transaction-Id' => $sessionId,
            'X-Transaction-Referer' => $_SERVER['HTTP_REFERER'] ?: __FILE__,
            'X-Agent' => $enderecoAgentInfo,
        ];
    }

    private function getEnderecoAgentInfo(Context $context): string
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('name', 'EnderecoShopware6Client'));

        return sprintf(
            'Endereco Shopware6 Client v%s',
            $this->pluginRepository->search($criteria, $context)->first()->getVersion()
        );
    }
}
