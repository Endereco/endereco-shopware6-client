<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Service\AddressCorrection;

use Endereco\Shopware6Client\DTO\SplitStreetResultDto;
use Endereco\Shopware6Client\Service\EnderecoService\PayloadPreparatorInterface;
use Endereco\Shopware6Client\Service\EnderecoService\RequestHeadersGeneratorInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Throwable;

final class StreetSplitter implements StreetSplitterInterface
{
    private SystemConfigService $systemConfigService;

    private RequestHeadersGeneratorInterface $requestHeadersGenerator;

    private PayloadPreparatorInterface $payloadPreparator;

    private Client $httpClient;

    private LoggerInterface $logger;

    public function __construct(
        SystemConfigService $systemConfigService,
        RequestHeadersGeneratorInterface $requestHeadersGenerator,
        PayloadPreparatorInterface $payloadPreparator,
        LoggerInterface $logger
    ) {
        $this->systemConfigService = $systemConfigService;
        $this->requestHeadersGenerator = $requestHeadersGenerator;
        $this->payloadPreparator = $payloadPreparator;
        $this->httpClient = new Client(['timeout' => 3.0, 'connection_timeout' => 2.0]);
        $this->logger = $logger;
    }

    public function splitStreet(
        string $fullStreet,
        ?string $additionalInfo,
        string $countryCode,
        Context $context,
        ?string $salesChannelId
    ): SplitStreetResultDto {
        // Fetch the Endereco API URL from the settings for the specific sales channel or in general.
        $serviceUrl = $this->systemConfigService->getString(
            'EnderecoShopware6Client.config.enderecoRemoteUrl',
            $salesChannelId
        );

        $streetName = $fullStreet;
        $houseNumber = '';

        try {
            // Generate request headers from context and sales channel settings.
            $headers = $this->requestHeadersGenerator->generateRequestHeaders(
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
                $this->payloadPreparator->preparePayload(
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
        return new SplitStreetResultDto($fullStreet, $streetName, $houseNumber, $additionalInfo);
    }
}
