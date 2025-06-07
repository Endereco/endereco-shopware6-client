<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Controller\Storefront;

use Endereco\Shopware6Client\Service\ApiConfiguration\ApiConfigurationFetcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Ultra-lightweight proxy controller for Endereco address check API requests.
 * 
 * This controller acts as a performance-optimized proxy between the storefront 
 * and the Endereco API to:
 * - Avoid CORS issues from direct frontend API calls
 * - Centralize API configuration and credential management
 * - Bypass heavy Shopware context resolution for maximum performance
 * - Provide a single point for request/response monitoring
 * 
 * SECURITY NOTE: CSRF protection is intentionally disabled for this endpoint
 * as it serves as an API proxy. The Endereco API itself provides the 
 * authentication layer via X-Auth-Key headers.
 * 
 * PERFORMANCE: This controller bypasses Shopware's standard context resolution
 * through a custom ContextResolverListener to minimize response latency for
 * real-time address validation requests.
 * 
 * @Route(defaults={"_routeScope"={"storefront"}}) // For SW Version >= 6.4.11.0
 */
class AddressCheckProxyController
{
    private HttpClientInterface $httpClient;
    private ApiConfigurationFetcherInterface $apiConfigurationFetcher;

    public function __construct(
        HttpClientInterface $httpClient,
        ApiConfigurationFetcherInterface $apiConfigurationFetcher,
    ) {
        $this->httpClient = $httpClient;
        $this->apiConfigurationFetcher = $apiConfigurationFetcher;
    }

    /**
     * Proxies address validation requests to the Endereco API.
     * 
     * This method forwards POST requests containing address data to the 
     * configured Endereco API endpoint while preserving transaction tracking
     * headers for correlation and debugging purposes.
     * 
     * @param Request $request The HTTP request containing address data as JSON
     * @return Response The proxied response from Endereco API or error response
     */
    public function __invoke(Request $request): Response
    {
        if ($request->getMethod() !== 'POST') {
            return new Response('Method not allowed', 405, [
                'Allow' => 'POST',
                'X-Robots-Tag' => 'noindex, nofollow'
            ]);
        }

        $content = $request->getContent();
        if (!$content) {
            return new Response('Request body is empty. We expect a valid JSON.', 400, [
                'X-Robots-Tag' => 'noindex, nofollow'
            ]);
        }

        // Validate JSON format to prevent forwarding malformed data
        try {
            json_decode($content, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new Response('Invalid JSON format in request body.', 400, [
                'X-Robots-Tag' => 'noindex, nofollow'
            ]);
        }

        $salesChannelId = $request->attributes->get('sw-sales-channel-id');

        try {
            $apiConfiguration = $this->apiConfigurationFetcher->fetchConfiguration($salesChannelId);

            if ($apiConfiguration->url === '' || $apiConfiguration->accessKey === '') {
                return new Response('Missing configuration', 500, ['X-Robots-Tag' => 'noindex, nofollow']);
            }

            $response = $this->httpClient->request('POST', $apiConfiguration->url, [
                'body' => $content,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Auth-Key' => $apiConfiguration->accessKey,
                    'X-Transaction-Id' => $request->headers->get('X-TRANSACTION-ID', 'not_set'),
                    'X-Agent' => $request->headers->get('X-AGENT', ''),
                    'X-Transaction-Referer' => $request->headers->get('X-TRANSACTION-REFERER', ''),
                    'Content-Length' => strlen($content),
                ],
                'timeout' => 6,
                'max_duration' => 6,
            ]);

            return new Response(
                $response->getContent(false),
                $response->getStatusCode(),
                ['Content-Type' => 'application/json', 'X-Robots-Tag' => 'noindex, nofollow']
            );
        } catch (\Exception $e) {
            // Log actual error internally but return generic message to client
            // TODO: Implement proper logging service injection
            return new Response('Address validation service temporarily unavailable', 503, [
                'X-Robots-Tag' => 'noindex, nofollow'
            ]);
        }
    }
}
