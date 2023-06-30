<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Service;

use Endereco\Shopware6Client\Misc\EnderecoConstants;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateEntity;
use Shopware\Core\System\Country\CountryEntity;
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

    private EntityRepository $customerRepository;

    private EntityRepository $enderecoAddressExtensionRepository;

    private EntityRepository $countryRepository;

    private EntityRepository $countryStateRepository;

    private LoggerInterface $logger;

    public function __construct(
        SystemConfigService $systemConfigService,
        EntityRepository $pluginRepository,
        EntityRepository $customerRepository,
        EntityRepository $enderecoAddressExtensionRepository,
        EntityRepository $countryRepository,
        EntityRepository $countryStateRepository,
        LoggerInterface $logger
    ) {
        $this->httpClient = new Client(['timeout' => 3.0, 'connection_timeout' => 2.0]);
        $this->apiKey = $systemConfigService->getString('EnderecoShopware6Client.config.enderecoApiKey') ?? '';
        $this->serviceUrl = $systemConfigService->getString('EnderecoShopware6Client.config.enderecoRemoteUrl') ?? '';
        $this->pluginRepository = $pluginRepository;
        $this->customerRepository = $customerRepository;
        $this->enderecoAddressExtensionRepository = $enderecoAddressExtensionRepository;
        $this->countryRepository = $countryRepository;
        $this->countryStateRepository = $countryStateRepository;
        $this->logger = $logger;
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

    public function checkAddress(CustomerAddressEntity $address, Context $context): void
    {
        $customer = $this->fetchEntityById(
            $address->getCustomerId(),
            $this->customerRepository,
            $context,
            ['language.locale']
        );
        $country = $this->fetchEntityById($address->getCountryId(), $this->countryRepository, $context, ['states']);
        if (!$country instanceof CountryEntity || !$customer instanceof CustomerEntity) {
            return;
        }
        $locale = $customer->getLanguage()->getLocale();
        $countryCode = strtoupper($country->getIso());
        $localeCode = explode('-', $locale->getCode())[0];

        $payload = [
            'country' => $countryCode,
            'language' => $localeCode,
            'postCode' => $address->getZipcode(),
            'cityName' => $address->getCity(),
            'streetFull' => $address->getStreet()
        ];

        if (!is_null($address->getCountryStateId())) {
            $countryState = $this->fetchEntityById(
                $address->getCountryStateId(),
                $this->countryStateRepository,
                $context
            );
            if ($countryState instanceof CountryStateEntity) {
                $payload['subdivisionCode'] = strtoupper($countryState->getShortCode());
            }
        } elseif (!is_null($country->getStates()) && $country->getStates()->count() > 1) {
            //countryStateId is null, but country has states -- allow to send prediction for subdivision code
            $payload['subdivisionCode'] = '';
        }

        try {
            $tid = $this->generateTid();
            $response = $this->httpClient->post(
                $this->serviceUrl,
                array(
                    'headers' => $this->prepareHeaders(
                        $this->getEnderecoAgentInfo($context),
                        $tid
                    ),
                    'body' => json_encode($this->preparePayload('addressCheck', $payload))
                )
            );


            $result = json_decode($response->getBody()->getContents(), true)['result'];

            $statuses = implode(',', $result['status'] ?? '');
            $predictions = [];

            foreach ($result['predictions'] as $prediction) {
                $tempAddress = array(
                    'countryCode' => $prediction['country'] ?: $countryCode,
                    'postalCode' => $prediction['postCode'],
                    'locality' => $prediction['cityName'],
                    'streetName' => $prediction['street'],
                    'buildingNumber' => $prediction['houseNumber']
                );

                if (array_key_exists('additionalInfo', $prediction)) {
                    $tempAddress['additionalInfo'] = $prediction['additionalInfo'];
                }

                if (array_key_exists('subdivisionCode', $prediction)) {
                    $tempAddress['subdivisionCode'] = $prediction['subdivisionCode'];
                }

                $predictions[] = $tempAddress;
            }


            $this->enderecoAddressExtensionRepository->upsert([[
                'addressId' => $address->getId(),
                'amsStatus' => $statuses,
                'amsPredictions' => $predictions,
                'amsTimestamp' => time()
            ]], $context);


            $this->doAccountings([$tid], $context);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                if ($response && 500 <= $response->getStatusCode()) {
                    $this->logger->error('Serverside checkAddress failed', ['error' => $e->getMessage()]);
                }
            }
        } catch (Throwable $e) {
            $this->logger->error('Serverside checkAddress failed', ['error' => $e->getMessage()]);
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

    /**
     * Automatically fetches the session ids from the request data bag and does the accountings for them.
     */
    public function doAccountingsForRequest(RequestDataBag $dataBag, Context $context): void
    {
        $sessionIds = array_values(
            array_filter(
                $dataBag->all(),
                static fn($key) => str_contains($key, "ams_session_id"),
                ARRAY_FILTER_USE_KEY
            )
        );

        if (count($sessionIds) === 0) {
            return;
        }

        $this->doAccountings($sessionIds, $context);
    }

    public function doAccountings(array $sessionIds, Context $context): void
    {
        if (empty($sessionIds)) {
            return;
        }

        $hasAccounting = false;
        foreach ($sessionIds as $sessionId) {
            $payload = $this->preparePayload(
                'doAccounting',
                [
                    'sessionId' => $sessionId
                ]
            );
            try {
                $this->httpClient->post(
                    $this->serviceUrl,
                    array(
                        'headers' => $this->prepareHeaders($this->getEnderecoAgentInfo($context), $sessionId),
                        'body' => json_encode($payload)
                    )
                );

                $hasAccounting = true;
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

        if ($hasAccounting) {
            $this->doConversion($context);
        }
    }

    public function doConversion(Context $context): void
    {
        try {
            $this->httpClient->post(
                $this->serviceUrl,
                array(
                    'headers' => $this->prepareHeaders($this->getEnderecoAgentInfo($context)),
                    'body' => json_encode($this->preparePayload('doConversion'))
                )
            );
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                if ($response && 500 <= $response->getStatusCode()) {
                    $this->logger->error('Serverside doConversion failed', ['error' => $e->getMessage()]);
                }
            }
        } catch (Throwable $e) {
            $this->logger->error('Serverside doConversion failed', ['error' => $e->getMessage()]);
        }
    }

    public function checkApiCredentials(string $endpointUrl, string $apiKey, Context $context): bool
    {
        $headers = $this->prepareHeaders($this->getEnderecoAgentInfo($context));
        $headers['X-Auth-Key'] = $apiKey;
        try {
            $response = $this->httpClient->post(
                $endpointUrl,
                array(
                    'headers' => $headers,
                    'body' => json_encode($this->preparePayload('readinessCheck'))
                )
            );


            $status = json_decode($response->getBody()->getContents(), true);
            if ('ready' === $status['result']['status']) {
                return true;
            } else {
                $this->logger->warning("Credentials test failed", ['responseFromEndereco' => json_encode($status)]);
            }
        } catch (GuzzleException $e) {
            $this->logger->warning("Credentials test failed", ['error' => $e->getMessage()]);
        }

        return false;
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

    private function fetchEntityById(
        string $id,
        EntityRepository $repository,
        Context $context,
        array $associations = []
    ): ?Entity {
        $criteria = new Criteria([$id]);
        if (!empty($associations)) {
            $criteria->addAssociations($associations);
        }
        return $repository->search(
            $criteria,
            $context
        )->first();
    }

    /**
     * @throws Exception
     */
    private function generateTid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
