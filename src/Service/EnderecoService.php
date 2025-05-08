<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Service;

use Endereco\Shopware6Client\DTO\CustomerAddressDTO;
use Endereco\Shopware6Client\Entity\CustomerAddress\CustomerAddressExtension;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\CustomerAddress\EnderecoCustomerAddressExtensionEntity;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\OrderAddress\EnderecoOrderAddressExtensionEntity;
use Endereco\Shopware6Client\Entity\OrderAddress\OrderAddressExtension;
use Endereco\Shopware6Client\Misc\EnderecoConstants;
use Endereco\Shopware6Client\Model\AddressCheckResult;
use Endereco\Shopware6Client\Model\FailedAddressCheckResult;
use Endereco\Shopware6Client\Model\SuccessfulAddressCheckResult;
use Endereco\Shopware6Client\Service\AddressCheck\AddressCheckPayloadBuilderInterface;
use Endereco\Shopware6Client\Service\AddressCheck\CountryCodeFetcherInterface;
use Endereco\Shopware6Client\Service\AddressIntegrity\CustomerAddress\AddressPersistenceStrategyProvider;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\PluginEntity as Plugin;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Throwable;

class EnderecoService
{
    private Client $httpClient;

    private EntityRepository $pluginRepository;

    private EntityRepository $countryStateRepository;

    private EntityRepository $customerAddressRepository;

    private EntityRepository $orderAddressRepository;

    private LoggerInterface $logger;

    private SystemConfigService $systemConfigService;

    private ?SessionInterface $session;

    private CountryCodeFetcherInterface $countryCodeFetcher;

    private AddressCheckPayloadBuilderInterface $addressCheckPayloadBuilder;

    private AddressPersistenceStrategyProvider $addressPersistenceStrategyProvider;

    public bool $isImport = false;
    public bool $isProcessingInsurances = false;

    protected RequestStack $requestStack;

    public function __construct(
        SystemConfigService $systemConfigService,
        EntityRepository $pluginRepository,
        EntityRepository $countryStateRepository,
        EntityRepository $customerAddressRepository,
        EntityRepository $orderAddressRepository,
        CountryCodeFetcherInterface $countryCodeFetcher,
        AddressCheckPayloadBuilderInterface $addressCheckPayloadBuilder,
        AddressPersistenceStrategyProvider $addressPersistenceStrategyProvider,
        RequestStack $requestStack,
        LoggerInterface $logger
    ) {
        $this->httpClient = new Client(['timeout' => 3.0, 'connection_timeout' => 2.0]);
        $this->systemConfigService = $systemConfigService;
        $this->pluginRepository = $pluginRepository;
        $this->countryStateRepository = $countryStateRepository;
        $this->customerAddressRepository = $customerAddressRepository;
        $this->orderAddressRepository = $orderAddressRepository;
        $this->countryCodeFetcher = $countryCodeFetcher;
        $this->addressCheckPayloadBuilder = $addressCheckPayloadBuilder;
        $this->addressPersistenceStrategyProvider = $addressPersistenceStrategyProvider;
        $this->requestStack = $requestStack;

        if (!is_null($requestStack->getMasterRequest())) {
            $this->session = $requestStack->getMasterRequest()->getSession();
        } else {
            $this->session = null;
        }

        $this->logger = $logger;
    }

    /**
     * Dispatches 'doAccounting' and 'doConversion' HTTP POST requests to a preconfigured service URL for each
     * session ID.
     *
     * For each session ID, this method sends a 'doAccounting' request, and if it's successful,
     * a 'doConversion' request follows. This behavior stems from legacy logic where 'doConversion' requests
     * were tracked alongside 'doAccounting' requests to identify anomalies.
     *
     * Both requests carry headers and a body, the creation of which is handled by helper methods. Headers include agent
     * information, and for 'doAccounting' requests, the session ID. The body encapsulates a JSON-encoded
     * payload specifying the request type and, for 'doAccounting' requests, the session ID.
     *
     * If a 'doAccounting' request fails, an error message is logged. Conversely, if the 'doConversion'
     * request fails, a warning message is logged.
     *
     * @param array<string> $sessionIds An array of session IDs for which to process requests.
     * @param Context $context The context providing details of the event that initiated this method.
     * @param string $salesChannelId The identifier of the sales channel associated with these sessions.
     *
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException If there's an error while executing HTTP requests.
     */
    public function sendDoAccountings(array $sessionIds, Context $context, string $salesChannelId): void
    {
        // Flag to check if any 'doAccounting' request was made
        $anyDoAccounting = false;

        // Retrieve the Endereco API url from settings for the specific sales channel
        $serviceUrl = $this->systemConfigService
            ->getString('EnderecoShopware6Client.config.enderecoRemoteUrl', $salesChannelId);

        // Loop through each session ID
        foreach ($sessionIds as $sessionId) {
            try {
                // Generate request headers from context, sales channel settings, and optional session id
                $headers = $this->generateRequestHeaders(
                    $context,
                    $salesChannelId,
                    $sessionId
                );

                $payload = json_encode(
                    $this->preparePayload(
                        'doAccounting',
                        [
                            'sessionId' => $sessionId
                        ]
                    )
                );

                // Dispatch the 'doAccounting' request to the Endereco API
                $this->httpClient->post(
                    $serviceUrl,
                    [
                        'headers' => $headers,
                        'body' => $payload,
                    ]
                );

                // Set flag to true as 'doAccounting' request was successful
                $anyDoAccounting = true;
            } catch (RequestException $e) {
                // Log error message if 'doAccounting' request fails
                if ($e->hasResponse()) {
                    $response = $e->getResponse();
                    if ($response && 500 <= $response->getStatusCode()) {
                        $this->logger->error('Serverside doAccounting failed', ['error' => $e->getMessage()]);
                    }
                }
            } catch (Throwable $e) {
                // Log error message for any other exceptions during 'doAccounting'
                $this->logger->error('Serverside doAccounting failed', ['error' => $e->getMessage()]);
            }
        }

        // If any 'doAccounting' request was made, dispatch a 'doConversion' request as per legacy logic
        if ($anyDoAccounting) {
            try {
                // Generate request headers from context, sales channel settings, and optional session id
                $headers = $this->generateRequestHeaders(
                    $context,
                    $salesChannelId,
                    'not_required'
                );

                $payload = json_encode(
                    $this->preparePayload(
                        'doConversion'
                    )
                );

                // Dispatch the 'doConversion' request to the Endereco API
                $this->httpClient->post(
                    $serviceUrl,
                    [
                        'headers' => $headers,
                        'body' => $payload,
                    ]
                );
            } catch (RequestException $e) {
                // Log warning message if 'doConversion' request fails
                if ($e->hasResponse()) {
                    $response = $e->getResponse();
                    if ($response && 500 <= $response->getStatusCode()) {
                        $this->logger->warning('Serverside doConversion failed', ['error' => $e->getMessage()]);
                    }
                }
            } catch (Throwable $e) {
                // Log warning message for any other exceptions during 'doConversion'
                $this->logger->warning('Serverside doConversion failed', ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Closes the stored sessions associated with 'enderecoAccountableSessions' when an address is written to
     * the database.
     *
     * This method is invoked when an address write operation occurs. It checks the current user's session
     * for any stored sessions under the 'enderecoAccountableSessions' key. If such sessions are present,
     * it calls a service to close these sessions and subsequently resets the 'enderecoAccountableSessions' array
     * in the user's session to an empty state, indicating that all sessions have been successfully closed.
     *
     * This operation is crucial for maintaining accurate accounting.
     *
     * @param Context $context The context which includes details of the event triggering this method.
     * @param string $salesChannelId The identifier of the sales channel where the address write operation occurred.
     *
     * @return void
     */
    public function closeStoredSessions(Context $context, string $salesChannelId)
    {

        if (!$this->session instanceof SessionInterface) {
            return;
        }

        // It can happen, that we write to customer address while processing insurances
        // for example for automatic corrections. To prevent restart of the sesison, we skip the
        // accounting, until the ensurances are through.
        if ($this->isProcessingInsurances) {
            return;
        }

        // Check if there are any stored sessions in 'enderecoAccountableSessions'
        if ($this->session->get('enderecoAccountableSessions')) {

            /** @var string[] $existingSessionIds */
            $existingSessionIds = $this->session->get('enderecoAccountableSessions');

            // If the retrieved session IDs array is not empty, proceed to close the sessions
            if (!empty($existingSessionIds)) {
                // Call the service method to close the sessions
                $this->sendDoAccountings($existingSessionIds, $context, $salesChannelId);

                if (!$this->session instanceof SessionInterface) {
                    return;
                }

                // Reset the 'enderecoAccountableSessions' array in the session
                $this->session->set('enderecoAccountableSessions', []);
            }
        }
    }

    /**
     * Adds a list of session IDs to the 'enderecoAccountableSessions' session storage.
     *
     * This method takes an array of session IDs as input and merges it with the existing session IDs stored
     * under the 'enderecoAccountableSessions' key in the session, if any. The resulting array is cleaned to remove
     * duplicate session IDs before being stored back into the 'enderecoAccountableSessions' session storage.
     *
     * This method is useful for keeping track of session IDs that should be accountable in subsequent requests,
     * especially in scenarios where the application creates multiple sessions during a single request.
     *
     * @param array<string> $sessionIds An array of session IDs to be added to session storage.
     *
     * @return void
     */
    public function addAccountableSessionIdsToStorage(array $sessionIds): void
    {
        if (!$this->session instanceof SessionInterface) {
            return;
        }

        // Fetch any existing sessions stored under 'enderecoAccountableSessions'
        $existingSessions = [];

        if ($this->session->get('enderecoAccountableSessions')) {
            $existingSessions = $this->session->get('enderecoAccountableSessions');
        }

        // Merge the existing sessions with the new ones
        $allSessions = array_merge($existingSessions, $sessionIds);

        // Remove duplicate session IDs
        $allSessions = array_unique($allSessions);

        // Store the resulting array back into the 'enderecoAccountableSessions' session storage
        $this->session->set('enderecoAccountableSessions', $allSessions);
    }

    /**
     * Generates headers for an API request.
     *
     * The headers include the 'Content-Type', 'X-Auth-Key', 'X-Transaction-Id', 'X-Transaction-Referer',
     * and 'X-Agent'. The 'X-Auth-Key' is retrieved from the system configuration service using the provided
     * sales channel ID. The 'X-Transaction-Id' is the provided session ID. The 'X-Transaction-Referer' is
     * retrieved from the server's HTTP_REFERER variable, defaulting to __FILE__ if it's not set. The 'X-Agent'
     * is retrieved using the provided context.
     *
     * @param Context $context The context.
     * @param ?string $salesChannelId The sales channel ID.
     * @param string|null $sessionId The session ID, defaulting to 'not_required'.
     *
     * @return array<string, string> The generated headers.
     */
    public function generateRequestHeaders(Context $context, ?string $salesChannelId, $sessionId = 'not_required')
    {
        $appName = $this->getAgentInfo($context);
        $apiKey = $this->systemConfigService
            ->getString('EnderecoShopware6Client.config.enderecoApiKey', $salesChannelId);

        $headers = [
            'Content-Type' => 'application/json',
            'X-Auth-Key' => $apiKey,
            'X-Transaction-Id' => $sessionId,
            'X-Transaction-Referer' => $_SERVER['HTTP_REFERER'] ?? __FILE__,
            'X-Agent' => $appName,
        ];

        return $headers;
    }

    /**
     * Validates a given address using the Endereco API.
     *
     * This method uses the provided customer address and sales channel ID to prepare a set of headers and a payload for
     * an address check request. The headers are generated using the sales channel settings and context, and the payload
     * is constructed using data from the address.
     *
     * The method then sends a request to the Endereco API and interprets the response. It can handle scenarios
     * where the street address is split into separate fields and where the country of the address has subdivisions.
     *
     * In case of any errors during the address check request, this method falls back to returning
     * a FailedAddressCheckResult.
     *
     * @param CustomerAddressEntity $addressEntity The customer address to be checked.
     * @param Context $context The context which includes details of the event triggering this method.
     * @param string $salesChannelId The ID of the sales channel the address is associated with.
     * @param string $sessionId (optional) The session ID. If not provided, a new one will be generated.
     *
     * @return AddressCheckResult The result of the address check operation.
     */
    public function checkAddress(
        CustomerAddressEntity $addressEntity,
        Context $context,
        string $salesChannelId,
        string $sessionId = ''
    ): AddressCheckResult {
        // Generate a new session id if none was provided
        if (empty($sessionId)) {
            try {
                $sessionId = $this->generateSessionId();
            } catch (Exception $e) {
                // Skip session id generation if it fails. It's worse to break the check
                // functionality than to have bad accounting.
                $sessionId = 'not_required';
            }
        }

        // Retrieve the Endereco API url from settings for the specific sales channel
        $serviceUrl = $this->systemConfigService->getString(
            'EnderecoShopware6Client.config.enderecoRemoteUrl',
            $salesChannelId
        );

        // Generate request headers from context, sales channel settings, and optional session id
        $headers = $this->generateRequestHeaders(
            $context,
            $salesChannelId,
            $sessionId
        );

        $payloadBody = $this->addressCheckPayloadBuilder->buildFromCustomerAddress(
            $addressEntity,
            $context
        );



        // Send the headers and payload to endereco api for valdiation.
        try {
            $data = $payloadBody->data();
            $data['language'] = 'de'; // Its just 'de' at this point. We'll remove this param in the future.

            $payload = json_encode(
                $this->preparePayload(
                    'addressCheck',
                    $data
                )
            );

            // Send to endereco api.
            $response = $this->httpClient->post(
                $serviceUrl,
                array(
                    'headers' => $headers,
                    'body' => $payload
                )
            );

            $resultJSON = $response->getBody()->getContents();

            /* @var $addressCheckResult FailedAddressCheckResult|SuccessfulAddressCheckResult */
            $addressCheckResult = AddressCheckResult::createFromJSON($resultJSON);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                if ($response && 500 <= $response->getStatusCode()) {
                    $this->logger->error('Serverside checkAddress failed', ['error' => $e->getMessage()]);
                }
            }

            $addressCheckResult = new FailedAddressCheckResult();
        } catch (Throwable $e) {
            $this->logger->error('Serverside checkAddress failed', ['error' => $e->getMessage()]);

            $addressCheckResult = new FailedAddressCheckResult();
        }

        $addressCheckResult->setUsedSessionId($sessionId);
        $addressCheckResult->setAddressSignature($payloadBody->toJSON());

        return $addressCheckResult;
    }

    /**
     * Resets the metadata of a customer address entity.
     *
     * Updates the Endereco extension fields of the address with empty values while preserving
     * the AMS status. Specifically clears the request payload, predictions, and updates the timestamp,
     * then persists these changes to the database.
     *
     * @param CustomerAddressEntity $addressEntity The address entity to reset
     * @param Context $context The context for database operations
     *
     * @return void
     */
    public function resetCustomerAddressMetaData(
        CustomerAddressEntity $addressEntity,
        Context $context
    ): void {
        $addressId = $addressEntity->getId();

        $updatePayload = [
            'id' => $addressId,
        ];

        $addressExtension = new EnderecoCustomerAddressExtensionEntity();
        $addressEntity->addExtension(CustomerAddressExtension::ENDERECO_EXTENSION, $addressExtension);

        $updatePayload['extensions'][CustomerAddressExtension::ENDERECO_EXTENSION]['amsRequestPayload']
            = $addressExtension->getAmsRequestPayload();
        $updatePayload['extensions'][CustomerAddressExtension::ENDERECO_EXTENSION]['amsStatus']
            = $addressExtension->getAmsStatus();
        $updatePayload['extensions'][CustomerAddressExtension::ENDERECO_EXTENSION]['amsPredictions']
            = $addressExtension->getAmsPredictions();
        $updatePayload['extensions'][CustomerAddressExtension::ENDERECO_EXTENSION]['amsTimestamp']
            = $addressExtension->getAmsTimestamp();

        // Update the customer address in the repository
        $this->customerAddressRepository->update([$updatePayload], $context);
    }

    /**
     * Resets the metadata of an oder address entity.
     *
     * Updates the Endereco extension fields of the address with empty values while preserving
     * the AMS status. Specifically clears the request payload, predictions, and updates the timestamp,
     * then persists these changes to the database.
     *
     * @param OrderAddressEntity $addressEntity The address entity to reset
     * @param Context $context The context for database operations
     *
     * @return void
     */
    public function resetOrderAddressMetaData(
        OrderAddressEntity $addressEntity,
        Context $context
    ): void {
        $addressId = $addressEntity->getId();

        $updatePayload = [
            'id' => $addressId,
        ];

        $addressExtension = new EnderecoOrderAddressExtensionEntity();
        $addressEntity->addExtension(OrderAddressExtension::ENDERECO_EXTENSION, $addressExtension);

        $updatePayload['extensions'][OrderAddressExtension::ENDERECO_EXTENSION]['amsRequestPayload']
            = $addressExtension->getAmsRequestPayload();
        $updatePayload['extensions'][OrderAddressExtension::ENDERECO_EXTENSION]['amsStatus']
            = $addressExtension->getAmsStatus();
        $updatePayload['extensions'][OrderAddressExtension::ENDERECO_EXTENSION]['amsPredictions']
            = $addressExtension->getAmsPredictions();
        $updatePayload['extensions'][OrderAddressExtension::ENDERECO_EXTENSION]['amsTimestamp']
            = $addressExtension->getAmsTimestamp();

        // Update the customer address in the repository
        $this->orderAddressRepository->update([$updatePayload], $context);
    }

    /**
     * Updates a customer address entity with the results of an address check operation.
     *
     * This method modifies the given customer address entity based on the outcome of an address check.
     * In the event of an automatic correction from the address check, the first prediction is applied to the customer
     * address and new virtual status codes are generated. Consequently, the 'street', 'zipcode', 'city', and possibly
     * 'countryStateId' fields of the customer address are adjusted.
     * If the address check did not result in an automatic correction, only the statuses and predictions from
     * the address check result are saved.
     *
     * Regardless of the result, the 'amsStatus', 'amsPredictions', and 'amsTimestamp' fields of the Endereco
     * extension of the customer address are updated.
     *
     * @param AddressCheckResult $addressCheckResult The outcome of the address check operation.
     * @param CustomerAddressEntity $addressEntity The customer address to be updated.
     * @param Context $context The context containing details about the event triggering this method.
     *
     * @return void
     */
    public function applyAddressCheckResult(
        AddressCheckResult $addressCheckResult,
        CustomerAddressEntity $addressEntity,
        Context $context
    ): void {
        $addressId = $addressEntity->getId();

        $updatePayload = [
            'id' => $addressId,
        ];

        if ($addressCheckResult->isAutomaticCorrection() && $this->isAutocorrectionAllowedInSettings($context)) {
            // In case of automatic correction, apply the first prediction to the customer address and generate
            // new virtual status codes
            $newStatuses = $addressCheckResult->generateStatusesForAutomaticCorrection();

            $correction = $addressCheckResult->getPredictions()[0];

            $updatePayload['zipcode'] = $correction['postalCode'];
            $updatePayload['city'] = $correction['locality'];

            $fullStreet = $this->buildFullStreet(
                $correction['streetName'],
                $correction['buildingNumber'],
                $correction['countryCode']
            );

            $updatePayload['street'] = $fullStreet;

            $addressEntity->setZipcode($correction['postalCode']);
            $addressEntity->setCity($correction['locality']);
            $addressEntity->setStreet($fullStreet);

            // If a subdivision code exists in the correction, find the corresponding country state ID and set it
            if (array_key_exists('subdivisionCode', $correction)) {
                $countryStateId = $this->countryStateRepository
                    ->searchIds(
                        (new Criteria())->addFilter(new EqualsFilter('shortCode', $correction['subdivisionCode'])),
                        $context
                    )->firstId();
                if (!is_null($countryStateId)) {
                    $updatePayload['countryStateId'] = $countryStateId;

                    $addressEntity->setCountryStateId($countryStateId);
                }
            }

            // Update the endereco extension fields
            $updatePayload['extensions'][CustomerAddressExtension::ENDERECO_EXTENSION]['street']
                = $correction['streetName'];
            $updatePayload['extensions'][CustomerAddressExtension::ENDERECO_EXTENSION]['houseNumber']
                = $correction['buildingNumber'];
            $updatePayload['extensions'][CustomerAddressExtension::ENDERECO_EXTENSION]['amsStatus']
                = implode(',', $newStatuses);
            $updatePayload['extensions'][CustomerAddressExtension::ENDERECO_EXTENSION]['amsPredictions']
                = [];
            $updatePayload['extensions'][CustomerAddressExtension::ENDERECO_EXTENSION]['amsTimestamp']
                = time();
            $updatePayload['extensions'][CustomerAddressExtension::ENDERECO_EXTENSION]['amsRequestPayload']
                = $addressCheckResult->getAddressSignature();

            /** @var EnderecoCustomerAddressExtensionEntity|null $addressExtension */
            $addressExtension = $addressEntity->getExtension(CustomerAddressExtension::ENDERECO_EXTENSION);

            if (is_null($addressExtension)) {
                $addressExtension = new EnderecoCustomerAddressExtensionEntity();
                $addressEntity->addExtension(CustomerAddressExtension::ENDERECO_EXTENSION, $addressExtension);
            }

            // We update the entity here, before it is even saved, because this function was triggered by LoadEntity
            // event. Basically the updated fields would be available only after the page reload, but by setting them
            // here, we make it possible to access the data within the first request.
            // The entity is also saved in cache right after this and reused by other entities, so we need to update
            // the data in it ASAP.
            $addressExtension->setStreet($correction['streetName']);
            $addressExtension->setHouseNumber($correction['buildingNumber']);
            $addressExtension->setAmsStatus(implode(',', $newStatuses));
            $addressExtension->setAmsPredictions([]);
            $addressExtension->setAmsTimestamp(time());
            $addressExtension->setAmsRequestPayload($addressCheckResult->getAddressSignature());
        } elseif ($addressCheckResult->isFullyCorrect() && !empty($addressCheckResult->getPredictions())) {
            $correction = $addressCheckResult->getPredictions()[0];
            $updatePayload['zipcode'] = $correction['postalCode'];
            $updatePayload['city'] = $correction['locality'];

            $fullStreet = $this->buildFullStreet(
                $correction['streetName'],
                $correction['buildingNumber'],
                $correction['countryCode']
            );

            $updatePayload['street'] = $fullStreet;

            $addressEntity->setZipcode($correction['postalCode']);
            $addressEntity->setCity($correction['locality']);
            $addressEntity->setStreet($fullStreet);

            // If a subdivision code exists in the correction, find the corresponding country state ID and set it
            if (array_key_exists('subdivisionCode', $correction)) {
                $countryStateId = $this->countryStateRepository
                    ->searchIds(
                        (new Criteria())->addFilter(new EqualsFilter('shortCode', $correction['subdivisionCode'])),
                        $context
                    )->firstId();
                if (!is_null($countryStateId)) {
                    $updatePayload['countryStateId'] = $countryStateId;

                    $addressEntity->setCountryStateId($countryStateId);
                }
            }

            // If there was no automatic correction, save the statuses and predictions from the address check result
            $updatePayload['extensions'][CustomerAddressExtension::ENDERECO_EXTENSION]['amsStatus']
                = $addressCheckResult->getStatusesAsString();
            $updatePayload['extensions'][CustomerAddressExtension::ENDERECO_EXTENSION]['amsPredictions']
                = $addressCheckResult->getPredictions();
            $updatePayload['extensions'][CustomerAddressExtension::ENDERECO_EXTENSION]['amsTimestamp']
                = time();
            $updatePayload['extensions'][CustomerAddressExtension::ENDERECO_EXTENSION]['amsRequestPayload']
                = $addressCheckResult->getAddressSignature();

            /** @var EnderecoCustomerAddressExtensionEntity|null $addressExtension */
            $addressExtension = $addressEntity->getExtension(CustomerAddressExtension::ENDERECO_EXTENSION);
            if (is_null($addressExtension)) {
                $addressExtension = new EnderecoCustomerAddressExtensionEntity();
                $addressEntity->addExtension(CustomerAddressExtension::ENDERECO_EXTENSION, $addressExtension);
            }

            $addressExtension->setAmsStatus($addressCheckResult->getStatusesAsString());
            $addressExtension->setAmsPredictions($addressCheckResult->getPredictions());
            $addressExtension->setAmstimestamp(time());
            $addressExtension->setAmsRequestPayload($addressCheckResult->getAddressSignature());
        } else {
            // If there was no automatic correction, save the statuses and predictions from the address check result
            $updatePayload['extensions'][CustomerAddressExtension::ENDERECO_EXTENSION]['amsStatus']
                = $addressCheckResult->getStatusesAsString();
            $updatePayload['extensions'][CustomerAddressExtension::ENDERECO_EXTENSION]['amsPredictions']
                = $addressCheckResult->getPredictions();
            $updatePayload['extensions'][CustomerAddressExtension::ENDERECO_EXTENSION]['amsTimestamp']
                = time();
            $updatePayload['extensions'][CustomerAddressExtension::ENDERECO_EXTENSION]['amsRequestPayload']
                = $addressCheckResult->getAddressSignature();

            /** @var EnderecoCustomerAddressExtensionEntity|null $addressExtension */
            $addressExtension = $addressEntity->getExtension(CustomerAddressExtension::ENDERECO_EXTENSION);
            if (is_null($addressExtension)) {
                $addressExtension = new EnderecoCustomerAddressExtensionEntity();
                $addressEntity->addExtension(CustomerAddressExtension::ENDERECO_EXTENSION, $addressExtension);
            }

            $addressExtension->setAmsStatus($addressCheckResult->getStatusesAsString());
            $addressExtension->setAmsPredictions($addressCheckResult->getPredictions());
            $addressExtension->setAmstimestamp(time());
            $addressExtension->setAmsRequestPayload($addressCheckResult->getAddressSignature());
        }

        // Update the customer address in the repository
        $this->customerAddressRepository->update([$updatePayload], $context);
    }

    /**
     * Returns true if the user allowed automatic correction in the advanced settings.
     *
     * It's allowed by default, however if it causes problems with payments or other systems, this setting can be used
     * to provide a temporary fix.
     *
     * @param Context $context
     * @return bool
     */
    public function isAutocorrectionAllowedInSettings(Context $context): bool
    {
        $salesChannelId = null;
        $source = $context->getSource();
        if ($source instanceof SalesChannelApiSource) {
            $salesChannelId = $source->getSalesChannelId();
        }

        return $this->systemConfigService->getBool(
            'EnderecoShopware6Client.config.enderecoAllowNativeAddressFieldsOverwrite',
            $salesChannelId
        );
    }

    /**
     * Synchronizes the street data in the given address data.
     *
     * This method checks whether the street splitting feature is enabled for the specified sales channel.
     * If it is, it constructs a full street address from the street name and building number in the address data.
     * If it is not, it splits the full street address in the address data into street name and building number.
     *
     * If the country is unknown, it uses Germany ('DE') as default.
     *
     * The method modifies the provided address data array by reference.
     *
     * @param array<string, mixed> $addressData The address data to be synchronized. Modified by reference.
     * @param Context $context The context.
     * @param string $salesChannelId The sales channel ID.
     */
    public function syncStreet(array &$addressData, Context $context, string $salesChannelId): void
    {
        $extensionName = CustomerAddressExtension::ENDERECO_EXTENSION;
        $isFullStreetEmpty = empty($addressData['street']);
        $isStreetNameEmpty = empty($addressData['extensions'][$extensionName]['street']);

        // In the following we handle three expected scenatio:
        // 1. The full street is empty, but name nad housenumber not -> fill up full street
        // 2. The full street is known and the street name is not -> fill up street name and house number
        // 3. Both are filled, then we prioritize based on whether street splitter is active or not
        // 4. If both are empty not filling is required. This should not happen normally.
        if ($isFullStreetEmpty && !$isStreetNameEmpty) {
            // Fetch important parts to build a full street.
            $streetName = $addressData['extensions'][$extensionName]['street'];
            $buildingNumber = $addressData['extensions'][$extensionName]['houseNumber'];

            // If country is unknown, use Germany as default
            $countryCode = $this->countryCodeFetcher->fetchCountryCodeByCountryIdAndContext(
                $addressData['countryId'],
                $context,
                'DE'
            );

            // Construct full street.
            $fullStreet = $this->buildFullStreet(
                $streetName,
                $buildingNumber,
                $countryCode
            );

            // Add the full street to the output data
            $addressData['street'] = $fullStreet;
        } elseif (!$isFullStreetEmpty && $isStreetNameEmpty) {
            // Get the full street and split it
            $fullStreet = $addressData['street'];

            // If country is unknown, use Germany as default
            $countryCode = $this->countryCodeFetcher->fetchCountryCodeByCountryIdAndContext(
                $addressData['countryId'],
                $context,
                'DE'
            );

            $additionalInfo = null;
            if (array_key_exists('additionalAddressLine1', $addressData)) {
                $additionalInfo = $addressData['additionalAddressLine1'];
            } elseif (array_key_exists('additionalAddressLine2', $addressData)) {
                $additionalInfo = $addressData['additionalAddressLine2'];
            }

            // Split the full street into its constituent parts
            list($normalizedStreetFull, $streetName, $buildingNumber, $normalizedAdditionalInfo) =
                $this->splitStreet(
                    $fullStreet,
                    $additionalInfo,
                    $countryCode,
                    $context,
                    $salesChannelId
                );

            $customerAddressDTO = new CustomerAddressDTO(
                null,
                null,
                $addressData
            );

            $addressPersistenceStrategy = $this->addressPersistenceStrategyProvider->getStrategy(
                $customerAddressDTO,
                $context
            );

            $addressPersistenceStrategy->execute(
                $normalizedStreetFull,
                $normalizedAdditionalInfo,
                $streetName,
                $buildingNumber,
                $customerAddressDTO
            );
        } elseif (!$isFullStreetEmpty && !$isStreetNameEmpty) {
            if ($this->isStreetSplittingFeatureEnabled($salesChannelId)) {
                // Fetch important parts to build a full street.
                $streetName = $addressData['extensions'][$extensionName]['street'];
                $buildingNumber = $addressData['extensions'][$extensionName]['houseNumber'];

                // If country is unknown, use Germany as default
                $countryCode = $this->countryCodeFetcher->fetchCountryCodeByCountryIdAndContext(
                    $addressData['countryId'],
                    $context,
                    'DE'
                );

                // Construct full street.
                $fullStreet = $this->buildFullStreet(
                    $streetName,
                    $buildingNumber,
                    $countryCode
                );

                // Add the full street to the output data
                $addressData['street'] = $fullStreet;
            } else {
                // Get the full street and split it
                $fullStreet = $addressData['street'];

                // If country is unknown, use Germany as default
                $countryCode = $this->countryCodeFetcher->fetchCountryCodeByCountryIdAndContext(
                    $addressData['countryId'],
                    $context,
                    'DE'
                );

                $additionalInfo = null;
                if (array_key_exists('additionalAddressLine1', $addressData)) {
                    $additionalInfo = $addressData['additionalAddressLine1'];
                } elseif (array_key_exists('additionalAddressLine2', $addressData)) {
                    $additionalInfo = $addressData['additionalAddressLine2'];
                }

                // Split the full street into its constituent parts
                list($normalizedStreetFull, $streetName, $buildingNumber, $normalizedAdditionalInfo) =
                    $this->splitStreet(
                        $fullStreet,
                        $additionalInfo,
                        $countryCode,
                        $context,
                        $salesChannelId
                    );

                $customerAddressDTO = new CustomerAddressDTO(
                    null,
                    null,
                    $addressData
                );

                $addressPersistenceStrategy = $this->addressPersistenceStrategyProvider->getStrategy(
                    $customerAddressDTO,
                    $context
                );

                $addressPersistenceStrategy->execute(
                    $normalizedStreetFull,
                    $normalizedAdditionalInfo,
                    $streetName,
                    $buildingNumber,
                    $customerAddressDTO
                );
            }
        }
    }

    /**
     * Determines if the Endereco plugin is active for a given sales channel.
     *
     * This method checks if the Endereco plugin is active for a specified sales channel and if the API key is set.
     * Both of these conditions must be met for the plugin to be considered active.
     *
     * @param string $salesChannelId The ID of the sales channel to check. If null, the default channel is used.
     * @return bool Returns true if the plugin is active for the given sales channel and the API key is set,
     *              false otherwise.
     */
    public function isEnderecoPluginActive(string $salesChannelId): bool
    {
        // Check if the plugin is active for this channel
        $isActiveForThisChannel = $this->systemConfigService
            ->getBool('EnderecoShopware6Client.config.enderecoActiveInThisChannel', $salesChannelId);

        // Check if the API key is set
        $isApiKeySet = !empty(
            $this->systemConfigService->get(
                'EnderecoShopware6Client.config.enderecoApiKey',
                $salesChannelId
            )
        );

        // Return true only if both conditions are met
        return $isActiveForThisChannel && $isApiKeySet;
    }

    /**
     * Determines if address validation is active for a given sales channel.
     *
     * @param string $salesChannelId The ID of the sales channel to check
     * @return bool Returns true if address validation is active for the given sales channel
     */
    public function isAddressCheckActive(string $salesChannelId): bool
    {
        // Check if the plugin is active for this channel
        $isAddressCheckActiveForThisChannel = $this->systemConfigService
            ->getBool('EnderecoShopware6Client.config.enderecoAMSActive', $salesChannelId);

        return $isAddressCheckActiveForThisChannel;
    }

    /**
     * Determines if street splitting feature is enabled.
     *
     * This method checks whether the Endereco plugin is active and
     * the street splitting feature is enabled in the settings.
     *
     * @param string $salesChannelId The ID of the sales channel for which to check the setting.
     *
     * @return bool Returns true if street splitting is enabled, false otherwise.
     */
    public function isStreetSplittingFeatureEnabled(string $salesChannelId): bool
    {
        // Check if the Endereco plugin is active and ready to use for the given sales channel.
        $pluginIsReadyToUse = $this->isEnderecoPluginActive($salesChannelId);

        // Check if the street splitting feature is active in the settings for the given sales channel.
        $featureIsActiveInSettings = $this->systemConfigService
            ->getBool('EnderecoShopware6Client.config.enderecoSplitStreetAndHouseNumber', $salesChannelId);

        // The feature is active if both the plugin is ready to use and the feature is active in settings.
        $featureIsActive = $pluginIsReadyToUse && $featureIsActiveInSettings;

        return $featureIsActive;
    }

    /**
     * Checks if the Import/Export Check feature is enabled for a given sales channel.
     *
     * This method determines whether the Import/Export Check feature of the Endereco plugin
     * is active and ready to use for the specified sales channel. It checks two conditions:
     * 1. If the Endereco plugin is active for the given sales channel.
     * 2. If the Import/Export Check feature is enabled in the plugin settings for the given sales channel.
     *
     * Essentially its used to block validation (e.g. AddressCheck) for when user is importing a bunch of data.
     *
     * @param string $salesChannelId The ID of the sales channel to check.
     *
     * @return bool Returns true if the Import/Export Check feature is enabled and ready to use,
     *              false otherwise.
     */
    public function isImportExportCheckFeatureEnabled(string $salesChannelId): bool
    {
        // Check if the Endereco plugin is active and ready to use for the given sales channel.
        $pluginIsReadyToUse = $this->isEnderecoPluginActive($salesChannelId);

        // Check if the addresses of imported customers should be validated.
        $featureIsActiveInSettings = $this->systemConfigService
            ->getBool('EnderecoShopware6Client.config.enderecoImportExportCheck', $salesChannelId);

        // The feature is active if both the plugin is ready to use and the feature is active in settings.
        $featureIsActive = $pluginIsReadyToUse && $featureIsActiveInSettings;

        return $featureIsActive;
    }

    /**
     * Checks whether the 'existing customer address check' feature is enabled.
     *
     * This method accepts a sales channel ID and checks two conditions:
     * 1. Whether the Endereco plugin is active and ready to use for the given sales channel.
     * 2. Whether the 'existing customer address check' feature is enabled in the settings for the given sales channel.
     *
     * The feature is considered active if both conditions are true. The method then returns this status.
     *
     * This feature is used to decide whether or not existing customer or order addresses should be checked for updates.
     * It can be controlled via the EnderecoShopware6Client configuration, providing flexibility to meet different
     * shop requirements.
     *
     * @param string $salesChannelId The ID of the sales channel for which to check the status of the feature.
     *
     * @return bool Returns true if the feature is enabled, false otherwise.
     */
    public function isExistingAddressCheckFeatureEnabled(string $salesChannelId): bool
    {
        // Check if the Endereco plugin is active and ready to use for the given sales channel.
        $pluginIsReadyToUse = $this->isEnderecoPluginActive($salesChannelId);

        // Check if the street splitting feature is active in the settings for the given sales channel.
        $featureIsActiveInSettings = $this->systemConfigService
            ->getBool('EnderecoShopware6Client.config.enderecoCheckExistingAddress', $salesChannelId);

        // The feature is active if both the plugin is ready to use and the feature is active in settings.
        $featureIsActive = $pluginIsReadyToUse && $featureIsActiveInSettings;

        return $featureIsActive;
    }

    /**
     * Checks whether the 'PayPal checkout address check' feature is enabled for a given sales channel.
     *
     * This method accepts a sales channel ID and checks two conditions:
     * 1. Whether the Endereco plugin is active and ready to use for the given sales channel.
     * 2. Whether the 'PayPal checkout address check' feature is enabled in the settings for the given sales channel.
     *
     * The feature is considered active if both conditions are true. The method then returns this status.
     *
     * This feature is used to decide whether or not addresses provided during a PayPal Express checkout process
     * should be checked for updates.
     * It can be controlled via the EnderecoShopware6Client configuration, providing flexibility to meet different
     * shop requirements.
     *
     * @param string $salesChannelId The ID of the sales channel for which to check the status of the feature.
     *
     * @return bool Returns true if the feature is enabled, false otherwise.
     */
    public function isPayPalCheckoutAddressCheckFeatureEnabled(string $salesChannelId): bool
    {
        // Check if the Endereco plugin is active and ready to use for the given sales channel.
        $pluginIsReadyToUse = $this->isEnderecoPluginActive($salesChannelId);

        // Check if the street splitting feature is active in the settings for the given sales channel.
        $featureIsActiveInSettings = $this->systemConfigService
            ->getBool('EnderecoShopware6Client.config.enderecoCheckPayPalExpressAddress', $salesChannelId);

        // The feature is active if both the plugin is ready to use and the feature is active in settings.
        $featureIsActive = $pluginIsReadyToUse && $featureIsActiveInSettings;

        return $featureIsActive;
    }

    /**
     * Checks whether the given address is from a remote source.
     *
     * This method accepts a CustomerAddressEntity and retrieves the corresponding
     * EnderecoAddressExtensionEntity for the address. It then checks if the address
     * originated from a remote source such as PayPal, Amazon Pay (or potentially other sources
     * like Facebook etc.).
     *
     * The method returns true if the address originated from a remote source,
     * and false otherwise.
     *
     * @param CustomerAddressEntity $addressEntity The address entity for which to check the source.
     *
     * @return bool Returns true if the address is from a remote source, false otherwise.
     */
    public function isAddressFromRemote(CustomerAddressEntity $addressEntity): bool
    {
        /** @var EnderecoCustomerAddressExtensionEntity $addressExtension */
        $addressExtension = $addressEntity->getExtension(CustomerAddressExtension::ENDERECO_EXTENSION);

        $isFromPayPal = $addressExtension->isPayPalAddress();
        $isFromAmazonPay = $addressExtension->isAmazonPayAddress();

        // The address is considered from remote if it originated from PayPal, Amazon Pay or other remote sources.
        // Current implementation considers PayPal and Amazon Pay, but this can be extended to other sources in future.
        $isFromRemote = $isFromPayPal || $isFromAmazonPay; // || $isFromAmazon || $isFromFaceBook etc.

        return $isFromRemote;
    }

    /**
     * Checks if a given address was created within the last 30 minutes.
     *
     * @param CustomerAddressEntity $addressEntity The address entity to check.
     *
     * @return bool Returns true if the address was created within the last 30 minutes, false otherwise.
     */
    public function isAddressRecent(CustomerAddressEntity $addressEntity): bool
    {
        // Get the creation time of the address
        $creationTime = $addressEntity->getCreatedAt();

        // Get the current time minus 30 minutes
        $fiveMinutesAgo = (new \DateTime())->modify('-30 minutes');

        // If the creation time of the address is greater than (or equal to) the time 30 minutes ago, return true
        // Otherwise, return false
        return $creationTime >= $fiveMinutesAgo;
    }

    /**
     * Checks whether the given address is originated from PayPal.
     *
     * This method accepts a CustomerAddressEntity and retrieves the corresponding
     * EnderecoAddressExtensionEntity for the address. It then checks if the address
     * originated from PayPal, indicated by the 'isPayPalAddress' property of the
     * EnderecoAddressExtensionEntity.
     *
     * The method returns true if the address originated from PayPal, and false otherwise.
     *
     * @param CustomerAddressEntity $addressEntity The address entity for which to check the source.
     *
     * @return bool Returns true if the address is from PayPal, false otherwise.
     */
    public function isAddressFromPayPal(CustomerAddressEntity $addressEntity): bool
    {
        /** @var EnderecoCustomerAddressExtensionEntity $addressExtension */
        $addressExtension = $addressEntity->getExtension(CustomerAddressExtension::ENDERECO_EXTENSION);

        $isFromPayPal = $addressExtension->isPayPalAddress();

        return $isFromPayPal;
    }

    /**
     * Checks whether the given address is originated from Amazon Pay.
     *
     * This method accepts a CustomerAddressEntity and retrieves the corresponding
     * EnderecoAddressExtensionEntity for the address. It then checks if the address
     * originated from Amazon Pay, indicated by the 'isAmazonPayAddress' property of the
     * EnderecoAddressExtensionEntity.
     *
     * The method returns true if the address originated from Amazon Pay, and false otherwise.
     *
     * @param CustomerAddressEntity $addressEntity The address entity for which to check the source.
     *
     * @return bool Returns true if the address is from Amazon Pay, false otherwise.
     */
    public function isAddressFromAmazonPay(CustomerAddressEntity $addressEntity): bool
    {
        /** @var EnderecoCustomerAddressExtensionEntity $addressExtension */
        $addressExtension = $addressEntity->getExtension(CustomerAddressExtension::ENDERECO_EXTENSION);

        $isFromAmazonPay = $addressExtension->isAmazonPayAddress();

        return $isFromAmazonPay;
    }

    /**
     * Finds accountable session IDs from a given array.
     *
     * This method iterates over the POST data array and looks for elements with keys ending in '_session_counter'.
     * If such an element is found and its value is a positive integer, it assumes that the corresponding session
     * identified by replacing '_session_counter' with '_session_id' in the key needs accounting.
     *
     * The session IDs found are collected and returned as an array. This is useful for identifying sessions
     * that need to be taken into account (i.e., accounted for) in further processing.
     *
     * Only those sessions that have some requests in them (hence the counter is higher than one) are relevant.
     *
     * @param array<string, string> $array The array from which to find accountable session IDs.
     *
     * @return array<string> Returns an array of the accountable session IDs found.
     */
    public function findAccountableSessionIds($array): array
    {
        $accountableSessionIds = [];
        // Look for session ID' that need doAccounting
        foreach ($array as $sVarName => $sVarValue) {
            if ((strpos($sVarName, '_session_counter') !== false) && 0 < intval($sVarValue)) {
                $sSessionIdName = str_replace('_session_counter', '', $sVarName) . '_session_id';
                $accountableSessionIds[$array[$sSessionIdName]] = true;
            }
        }
        return array_keys($accountableSessionIds);
    }

    /**
     * Splits a full street address into its components using the Endereco API.
     *
     * This method sends a "splitStreet" request to the Endereco API with a payload that includes the full street,
     * the country code (as the formatCountry), a fixed language ('de'), and optionally additional address information.
     * The API response is expected to contain the individual components: the street name, house number, and possibly
     * updated additional info. In case of any error, the method logs the issue and falls back to returning the API's
     * default values (with empty strings for missing components).
     *
     * @param string      $fullStreet      The full street address to be split.
     * @param ?string     $additionalInfo  Optional additional address information to be sent with the request.
     * @param string      $countryCode     The country code corresponding to the address.
     * @param Context     $context         The Shopware context providing details about the current execution.
     * @param ?string     $salesChannelId  The identifier for the sales channel associated with the address.
     *
     * @return array{0: string, 1: string, 2: string, 3: ?string} An array with the following elements:
     *      - [0] The street value returned from the API (may be the full street address if parsing failed),
     *      - [1] The extracted street name,
     *      - [2] The extracted house number,
     *      - [3] The additional info as returned by the API, or null if not provided.
     */
    public function splitStreet(
        string $fullStreet,
        ?string $additionalInfo,
        string $countryCode,
        Context $context,
        ?string $salesChannelId
    ): array {
        // Fetch the Endereco API URL from the settings for the specific sales channel or in general.
        $serviceUrl = $this->systemConfigService->getString(
            'EnderecoShopware6Client.config.enderecoRemoteUrl',
            $salesChannelId
        );

        $streetName = $fullStreet;
        $houseNumber = '';

        try {
            // Generate request headers from context and sales channel settings.
            $headers = $this->generateRequestHeaders(
                $context,
                $salesChannelId
            );

            $data = [
                'formatCountry' => $countryCode,
                'language' => 'de',
                'street' => $fullStreet
            ];

            if ($additionalInfo !== null) {
                $data['additionalInfo'] = $additionalInfo;
            }

            // Prepare the payload for the 'splitStreet' request.
            $payload = json_encode(
                $this->preparePayload(
                    'splitStreet',
                    $data
                )
            );

            // Send the 'splitStreet' request to the Endereco API.
            $response = $this->httpClient->post(
                $serviceUrl,
                [
                    'headers' => $headers,
                    'body' => $payload
                ]
            );

            // Decode the response from the API.
            $result = json_decode($response->getBody()->getContents())->result;

            $fullStreet = $result->street ?? '';
            $streetName = $result->streetName ?? '';
            $houseNumber = $result->houseNumber ?? '';
            $additionalInfo = $result->additionalInfo ?? null;
        } catch (RequestException $e) {
            // Handle and log specific HTTP request errors.
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                if ($response && 500 <= $response->getStatusCode()) {
                    $this->logger->error('Serverside splitStreet failed', ['error' => $e->getMessage()]);
                }
            }
        } catch (Throwable $e) {
            // Handle and log any other types of errors.
            $this->logger->error('Serverside splitStreet failed', ['error' => $e->getMessage()]);
        }

        // Return the original full street name and an empty string as the house number in case of failure.
        return [$fullStreet, $streetName, $houseNumber, $additionalInfo];
    }

    /**
     * Checks the API credentials by sending a 'readinessCheck' request to the Endereco API.
     *
     * This method sends a 'readinessCheck' request to the API using the provided API key and endpoint URL.
     * The response is then checked for a 'ready' status, and the method returns true if the status is 'ready'.
     * If the status is not 'ready' or any exceptions occur during the request, the method logs a warning message
     * and returns false.
     *
     * @param string $endpointUrl The URL to send the API request to.
     * @param string $apiKey The API key to use for the request.
     * @param Context $context The context offering details of the event triggering this method.
     *
     * @return bool True if the API readiness check is successful, false otherwise.
     */
    public function checkApiCredentials(string $endpointUrl, string $apiKey, Context $context): bool
    {
        try {
            // Get the name of the plugin and its version.
            $appName = $this->getAgentInfo($context);

            // Generate request headers from context and sales channel settings.
            $headers = [
                'Content-Type' => 'application/json',
                'X-Auth-Key' => $apiKey,
                'X-Transaction-Id' => 'not_required',
                'X-Transaction-Referer' => $_SERVER['HTTP_REFERER'] ?? __FILE__,
                'X-Agent' => $appName,
            ];

            // Prepare the payload for the 'readinessCheck' request.
            $payload = json_encode(
                $this->preparePayload(
                    'readinessCheck'
                )
            );

            // Send the 'readinessCheck' request to the Endereco API.
            $response = $this->httpClient->post(
                $endpointUrl,
                [
                    'headers' => $headers,
                    'body' => $payload
                ]
            );

            // Decode the response from the API.
            $status = json_decode($response->getBody()->getContents(), true);

            // Check if the status from the response is 'ready'.
            if ('ready' === $status['result']['status']) {
                return true;
            } else {
                // Log a warning if the status is not 'ready'.
                $this->logger->warning("Credentials test failed", ['responseFromEndereco' => json_encode($status)]);
            }
        } catch (GuzzleException $e) {
            // Log a warning if an exception occurs during the request.
            $this->logger->warning("Credentials test failed", ['error' => $e->getMessage()]);
        }

        // Return false if the status is not 'ready' or an exception occurred.
        return false;
    }


    /**
     * Constructs a full street address string from the street name and building number.
     *
     * The order in which the street name and building number are combined is determined by the provided country code.
     * The method checks for a mapping of the country code to an order in the STREET_ORDER_MAP constant.
     * If no mapping is found, it defaults to placing the house number second.
     *
     * @param string $streetName The name of the street.
     * @param string $buildingNumber The building number.
     * @param string $countryCode The ISO code of the country the address is in.
     * @return string The full street address.
     */
    public function buildFullStreet(string $streetName, string $buildingNumber, string $countryCode): string
    {
        // Determine the order for combining the street name and building number
        $order =
            EnderecoConstants::STREET_ORDER_MAP[strtolower($countryCode)] ??
            EnderecoConstants::STREET_ORDER_HOUSE_SECOND;

        // Return the full street address in the determined order
        return $order === EnderecoConstants::STREET_ORDER_HOUSE_FIRST ?
            sprintf('%s %s', $buildingNumber, $streetName) :
            sprintf('%s %s', $streetName, $buildingNumber);
    }

    /**
     * Prepares a payload for an API request in the JSON-RPC 2.0 format.
     *
     * This method prepares a payload array containing the JSON-RPC version, a default ID, the request method,
     * and an optional params array. The params array can be used to include any additional data that the API request
     * might require.
     *
     * @param string $method The name of the method for the API request.
     * @param array<string, string> $params Additional parameters to include in the API request (optional).
     *
     * @return array<string, string|int|array<string, string>> The prepared payload for the API request.
     */
    public function preparePayload(string $method, array $params = []): array
    {
        // Prepare the payload array.
        return [
            'jsonrpc' => '2.0',  // The JSON-RPC version.
            'id' => 1,           // A default ID.
            'method' => $method, // The name of the method for the API request.
            'params' => $params  // Any additional parameters for the API request.
        ];
    }

    /**
     * Fetches the version of the 'EnderecoShopware6Client' plugin and formats it along with the plugin name.
     *
     * This method calls the getPluginVersion method to fetch the version of the 'EnderecoShopware6Client' plugin.
     * The fetched version is then appended to the formatted plugin name string.
     *
     * The returned string is in the format 'Endereco Shopware6 Client (Download) vX.X.X',
     * where 'X.X.X' is the version number of the plugin.
     *
     * @param Context $context The context which includes details of the event triggering this method.
     *
     * @return string The formatted string containing the name and version of the plugin.
     */
    public function getAgentInfo(Context $context): string
    {
        $versionTag = $this->getPluginVersion($context);

        return sprintf(
            'Endereco Shopware6 Client (Download) v%s',
            $versionTag
        );
    }

    /**
     * Retrieves the version of the 'EnderecoShopware6Client' plugin.
     *
     * This method constructs a criteria object and applies a filter to it to target the plugin
     * with the name 'EnderecoShopware6Client'.
     * This criteria object is then used to perform a search within the plugin repository.
     * The version of the first matching plugin is retrieved.
     *
     * The returned value is the version number (X.X.X) of the 'EnderecoShopware6Client' plugin.
     *
     * @param Context $context The context which includes details of the event triggering this method.
     *
     * @return string The version number of the 'EnderecoShopware6Client' plugin.
     */
    public function getPluginVersion(Context $context): string
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('name', 'EnderecoShopware6Client'));

        /** @var Plugin|null $plugin */
        $plugin = $this->pluginRepository->search($criteria, $context)->first();

        if ($plugin !== null) {
            $versionTag = $plugin->getVersion();
        } else {
            $versionTag = 'unknown';
        }

        return $versionTag;
    }

    /**
     * Generates a version 4 UUID (Universally Unique Identifier) as a session ID.
     *
     * The method uses the random_bytes function to generate 16 random bytes. Then it modifies bits
     * in the 7th and 9th bytes to comply with the version 4 UUID specification.
     * This version of UUID is used because it's based on random (or pseudo-random) numbers.
     * The final UUID has 32 characters separated by hyphens in the format:
     * 8-4-4-4-12 for a total of 36 characters including hyphens.
     *
     * @return string The generated version 4 UUID.
     *
     * @throws \Exception If it was not possible to gather sufficient random data.
     */
    public function generateSessionId(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Fetches the sales channel ID from the context source.
     *
     * This method tries to fetch the sales channel ID from the context source.
     * If the source is an instance of SalesChannelApiSource, it returns the sales channel ID.
     * If the source is not an instance of SalesChannelApiSource, it returns null.
     *
     * @param Context $context The context which includes details of the event triggering this method.
     *
     * @return string|null The ID of the sales channel or null if the context source is not an instance
     *                     of SalesChannelApiSource.
     */
    public function fetchSalesChannelId(Context $context): ?string
    {
        $source = $context->getSource();
        if ($source instanceof SalesChannelApiSource) {
            return $source->getSalesChannelId();
        }
        return null;
    }
}
