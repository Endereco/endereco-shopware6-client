<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Service\AddressCheck;

use Endereco\Shopware6Client\Model\AddressCheckResult;
use Endereco\Shopware6Client\Model\FailedAddressCheckResult;
use Endereco\Shopware6Client\Model\SuccessfulAddressCheckResult;
use Endereco\Shopware6Client\Service\EnderecoService\PayloadPreparatorInterface;
use Endereco\Shopware6Client\Service\EnderecoService\RequestHeadersGeneratorInterface;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Throwable;

final class AddressChecker implements AddressCheckerInterface
{
    private SystemConfigService $systemConfigService;

    private RequestHeadersGeneratorInterface $requestHeadersGenerator;

    private AddressCheckPayloadBuilderInterface $addressCheckPayloadBuilder;

    private PayloadPreparatorInterface $payloadPreparator;

    private Client $httpClient;

    private LoggerInterface $logger;

    public function __construct(
        SystemConfigService $systemConfigService,
        RequestHeadersGeneratorInterface $requestHeadersGenerator,
        AddressCheckPayloadBuilderInterface $addressCheckPayloadBuilder,
        PayloadPreparatorInterface $payloadPreparator,
        LoggerInterface $logger
    ) {
        $this->systemConfigService = $systemConfigService;
        $this->requestHeadersGenerator = $requestHeadersGenerator;
        $this->addressCheckPayloadBuilder = $addressCheckPayloadBuilder;
        $this->payloadPreparator = $payloadPreparator;
        $this->httpClient = new Client(['timeout' => 3.0, 'connection_timeout' => 2.0]);
        $this->logger = $logger;
    }

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
        $headers = $this->requestHeadersGenerator->generateRequestHeaders(
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
                $this->payloadPreparator->preparePayload(
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
    private function generateSessionId(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
