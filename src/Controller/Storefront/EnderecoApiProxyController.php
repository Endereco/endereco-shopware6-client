<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Controller\Storefront;

use Endereco\Shopware6Client\Service\ApiConfiguration\ApiConfigurationFetcherInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Proxy controller for Endereco API requests.
 *
 * This controller acts as a proxy between the storefront and the Endereco API to:
 * - Avoid CORS issues from direct frontend API calls
 * - Centralize API configuration and credential management
 * - Keep API credentials secure on the server side
 * - Provide a single point for request/response monitoring and logging
 *
 * SECURITY NOTE: CSRF protection is intentionally disabled for this endpoint
 * as it serves as an API proxy. The Endereco API itself provides the
 * authentication layer via X-Auth-Key headers.
 *
 * @Route(defaults={"_routeScope"={"storefront"}}) // For SW Version >= 6.4.11.0
 */
class EnderecoApiProxyController
{
    private const ROBOTS_HEADER = ['X-Robots-Tag' => 'noindex, nofollow'];

    private HttpClientInterface $httpClient;
    private ApiConfigurationFetcherInterface $apiConfigurationFetcher;
    private LoggerInterface $logger;

    public function __construct(
        HttpClientInterface $httpClient,
        ApiConfigurationFetcherInterface $apiConfigurationFetcher,
        LoggerInterface $logger
    ) {
        $this->httpClient = $httpClient;
        $this->apiConfigurationFetcher = $apiConfigurationFetcher;
        $this->logger = $logger;
    }

    /**
     * Proxies requests to the Endereco API.
     *
     * This method forwards POST requests containing data to the configured
     * Endereco API endpoint while preserving transaction tracking headers
     * for correlation and debugging purposes.
     *
     * @param Request $request The HTTP request containing data as JSON
     * @return Response The proxied response from Endereco API or error response
     */
    public function __invoke(Request $request): Response
    {
        if ($request->getMethod() !== 'POST') {
            return new Response('Method not allowed', 405, ['Allow' => 'POST'] + self::ROBOTS_HEADER);
        }

        $content = $request->getContent();
        if (!$content) {
            return new Response('Request body is empty. We expect a valid JSON.', 400, self::ROBOTS_HEADER);
        }

        // Validate JSON format to prevent forwarding malformed data
        try {
            json_decode($content, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new Response('Invalid JSON format in request body.', 400, self::ROBOTS_HEADER);
        }

        $salesChannelId = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID);

        try {
            $apiConfiguration = $this->apiConfigurationFetcher->fetchConfiguration($salesChannelId);

            if ($apiConfiguration->url === '' || $apiConfiguration->accessKey === '') {
                return new Response('Missing configuration', 500, self::ROBOTS_HEADER);
            }

            $response = $this->httpClient->request('POST', $apiConfiguration->url, [
                'body' => $content,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Auth-Key' => $apiConfiguration->accessKey,
                    'X-Transaction-Id' => $request->headers->get('X-TRANSACTION-ID', 'not_required'),
                    'X-Agent' => $request->headers->get('X-AGENT', ''),
                    'X-Transaction-Referer' => $request->headers->get('X-TRANSACTION-REFERER', ''),
                    'Content-Length' => strlen($content),
                ],
            ]);

            return new Response(
                $response->getContent(false),
                $response->getStatusCode(),
                ['Content-Type' => 'application/json'] + self::ROBOTS_HEADER
            );
        } catch (HttpExceptionInterface $e) {
            // HTTP errors from Endereco API (4xx, 5xx responses)
            // Return the actual status code and response body from the API for easier debugging
            $apiResponse = $e->getResponse();
            $statusCode = $apiResponse->getStatusCode();
            $responseBody = $apiResponse->getContent(false);

            $this->logger->warning('Endereco API returned HTTP error', [
                'status_code' => $statusCode,
                'response_body' => $responseBody,
                'sales_channel_id' => $salesChannelId,
            ]);

            return new Response(
                $responseBody,
                $statusCode,
                ['Content-Type' => 'application/json'] + self::ROBOTS_HEADER
            );
        } catch (TransportExceptionInterface $e) {
            // Network/timeout errors (connection failed, timeout, DNS issues, etc.)
            $this->logger->error('Endereco API transport error', [
                'error' => $e->getMessage(),
                'sales_channel_id' => $salesChannelId,
            ]);

            return new Response(
                '{"error":"Unable to reach Endereco service"}',
                503,
                ['Content-Type' => 'application/json'] + self::ROBOTS_HEADER
            );
        } catch (\Throwable $e) {
            // Unexpected programming errors
            $this->logger->critical('Unexpected error in Endereco proxy controller', [
                'exception' => $e->getMessage(),
                'type' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'sales_channel_id' => $salesChannelId,
            ]);

            return new Response(
                '{"error":"Internal server error"}',
                500,
                ['Content-Type' => 'application/json'] + self::ROBOTS_HEADER
            );
        }
    }
}
