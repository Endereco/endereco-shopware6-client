<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Service;

use Endereco\Shopware6Client\Service\EnderecoService\PayloadPreparatorInterface;
use Endereco\Shopware6Client\Service\EnderecoService\RequestHeadersGeneratorInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Throwable;

class SessionManagementService
{
    private Client $httpClient;
    private SystemConfigService $systemConfigService;
    private RequestHeadersGeneratorInterface $requestHeadersGenerator;
    private PayloadPreparatorInterface $payloadPreparator;
    private LoggerInterface $logger;
    private ?SessionInterface $session;

    public bool $isProcessingInsurances = false;

    public function __construct(
        SystemConfigService $systemConfigService,
        RequestHeadersGeneratorInterface $requestHeadersGenerator,
        PayloadPreparatorInterface $payloadPreparator,
        RequestStack $requestStack,
        LoggerInterface $logger
    ) {
        $this->httpClient = new Client(['timeout' => 3.0, 'connection_timeout' => 2.0]);
        $this->systemConfigService = $systemConfigService;
        $this->requestHeadersGenerator = $requestHeadersGenerator;
        $this->payloadPreparator = $payloadPreparator;
        $this->logger = $logger;

        if (!is_null($requestStack->getMainRequest())) {
            $this->session = $requestStack->getMainRequest()->getSession();
        } else {
            $this->session = null;
        }
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
                $headers = $this->requestHeadersGenerator->generateRequestHeaders(
                    $context,
                    $salesChannelId,
                    $sessionId
                );

                $payload = json_encode(
                    $this->payloadPreparator->preparePayload(
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
                $headers = $this->requestHeadersGenerator->generateRequestHeaders(
                    $context,
                    $salesChannelId,
                    'not_required'
                );

                $payload = json_encode(
                    $this->payloadPreparator->preparePayload(
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
    public function closeStoredSessions(Context $context, string $salesChannelId): void
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
     * Sets the processing insurances flag.
     *
     * @param bool $isProcessingInsurances
     * @return void
     */
    public function setIsProcessingInsurances(bool $isProcessingInsurances): void
    {
        $this->isProcessingInsurances = $isProcessingInsurances;
    }
}
