<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\CustomerAddressPipeline\Operation;

use Endereco\Shopware6Client\CustomerAddressPipeline\Workspace;
use Endereco\Shopware6Client\Service\EnderecoService;
use Endereco\Shopware6Client\Service\ProcessContextService;
use Symfony\Component\HttpFoundation\Request;

/**
 * Checks if the pipeline should run based on context prerequisites
 * 
 * This operation runs first and sets skipRemaining flag if conditions are not met
 */
final class SetSkipFlag implements Operation
{
    private ProcessContextService $processContext;
    private EnderecoService $enderecoService;

    /** @var string[] Controllers with address forms - can specify ControllerName or ControllerName::method */
    private array $whiteListedController = [
        'AddressController::accountEditAddress',
        'AddressController::addressBook',
        'CheckoutController::confirmPage',
    ];

    public function __construct(
        ProcessContextService $processContext,
        EnderecoService $enderecoService
    ) {
        $this->processContext = $processContext;
        $this->enderecoService = $enderecoService;
    }

    public static function getPriority(): int
    {
        return 0; // First to execute
    }

    public function applies(Workspace $workspace): bool
    {
        $context = $workspace->getContext();

        // Check if we are in storefront
        if (!$this->processContext->isStorefront()) {
            $workspace->skipRemaining();
            return false;
        }

        // Check if plugin is active for this sales channel
        if ($context === null) {
            $workspace->skipRemaining();
            return false;
        }
        $salesChannelId = $this->enderecoService->fetchSalesChannelId($context);
        if ($salesChannelId === null || !$this->enderecoService->isEnderecoPluginActive($salesChannelId)) {
            $workspace->skipRemaining();
            return false;
        }

        return true;
    }

    public function process(Workspace $workspace): void
    {
        if (!$this->isWhitelistedController($workspace)) {
            $workspace->skipRemaining();
        }
    }

    private function isWhitelistedController(Workspace $workspace): bool
    {
        $request = $workspace->getRequest();
        if ($request === null) {
            return false;
        }

        // Get controller and method from request attributes
        $controller = $request->attributes->get('_controller');
        if (!is_string($controller)) {
            return false;
        }

        $normalizedController = $this->normalizeController($controller);

        foreach ($this->whiteListedController as $whitelistedController) {
            if (stripos($normalizedController, $whitelistedController) === 0) {
                return true;
            }
        }

        return false;
    }

    private function normalizeController(string $controller): string
    {
        $controllerClass = $this->extractControllerClassName($controller);
        $method = $this->extractControllerMethodName($controller);
        $shortControllerClass = $this->getShortControllerName($controllerClass);

        if ($method === '') {
            return $shortControllerClass;
        }

        return $shortControllerClass . '::' . $method;
    }

    private function extractControllerClassName(string $controller): string
    {
        if (str_contains($controller, '::')) {
            return explode('::', $controller, 2)[0];
        }
        
        return $controller;
    }

    private function extractControllerMethodName(string $controller): string
    {
        if (str_contains($controller, '::')) {
            return explode('::', $controller, 2)[1];
        }
        
        return '';
    }

    private function getShortControllerName(string $fullControllerClass): string
    {
        $parts = explode('\\', $fullControllerClass);
        return end($parts);
    }
}