<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Subscriber;

use Endereco\Shopware6Client\Service\EnderecoService;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Protects CrefoPay PayPal Express session from being cleared by CrefoPay's CartEventListener.
 *
 * CrefoPay clears PayPal Express session for paths not in allowed list (/checkout/, /widgets/, /crefo-pay/).
 * This subscriber saves session data before CrefoPay runs (priority 100) and restores it after (priority -100).
 * Session data is stored persistently to survive CrefoPay's cleanup.
 */
class CrefoPayPayPalSessionProtectionSubscriber implements EventSubscriberInterface
{
    private const REQUEST_ATTR_SAVED_SESSION = 'endereco_crefopay_saved_session';
    private const SESSION_KEY_SAVED_DATA = 'endereco_crefopay_saved_session_data';
    private const CONFIG_KEY_CREFO_PAY_PAYPAL_CHECK =
        'EnderecoShopware6Client.config.enderecoCheckCrefoPayPayPalExpressAddress';

    private SystemConfigService $systemConfigService;
    private EnderecoService $enderecoService;

    public function __construct(
        SystemConfigService $systemConfigService,
        EnderecoService $enderecoService
    ) {
        $this->systemConfigService = $systemConfigService;
        $this->enderecoService = $enderecoService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                ['saveSessionBeforeCrefoPay', 100],
                ['restoreSessionAfterCrefoPay', -100],
            ],
        ];
    }

    /**
     * Saves CrefoPay PayPal Express session data before CrefoPay's listener runs.
     *
     * Strategy:
     * 1. Check if feature is enabled for the sales channel
     * 2. Load current session data (transactionId, paypalExpressActive)
     * 3. Restore from persistent storage if current session was cleared
     * 4. Save to request attributes (for same request) and persistent storage (for next requests)
     */
    public function saveSessionBeforeCrefoPay(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (str_starts_with($path, '/api') || str_starts_with($path, '/admin') || str_starts_with($path, '/_wdt')) {
            return;
        }

        $salesChannelId = $this->getSalesChannelId($request);
        if (!$this->isCrefoPayPayPalCheckEnabled($salesChannelId)) {
            return;
        }

        if (!$request->hasSession()) {
            return;
        }

        try {
            $session = $request->getSession();
            $transactionId = $session->get('crefopay-paypal-express-transaction');
            $currentPaypalActive = $_SESSION['paypalExpressActive'] ?? null;
            $previousSavedData = $session->get(self::SESSION_KEY_SAVED_DATA);

            // Restore from persistent storage if current session was cleared
            if ($previousSavedData && is_array($previousSavedData)) {
                if (empty($transactionId) && !empty($previousSavedData['crefopay-paypal-express-transaction'])) {
                    $transactionId = $previousSavedData['crefopay-paypal-express-transaction'];
                }
                if (empty($currentPaypalActive) && !empty($previousSavedData['paypalExpressActive'])) {
                    $currentPaypalActive = $previousSavedData['paypalExpressActive'];
                }
            }

            // Save if we have any session data (transactionId, paypalExpressActive, or previous saved data)
            if (!empty($transactionId) || !empty($currentPaypalActive) || !empty($previousSavedData)) {
                $savedData = [
                    'crefopay-paypal-express-transaction' => $transactionId,
                    'paypalExpressActive' => $currentPaypalActive,
                ];

                // Update persistent storage only if data changed
                $needsUpdate = false;
                if (!$previousSavedData || !is_array($previousSavedData)) {
                    $needsUpdate = true;
                } else {
                    $oldTransactionId = $previousSavedData['crefopay-paypal-express-transaction'] ?? null;
                    $oldPaypalActive = $previousSavedData['paypalExpressActive'] ?? null;

                    if ($oldTransactionId !== $transactionId || $oldPaypalActive !== $currentPaypalActive) {
                        $needsUpdate = true;
                    }
                }

                // Save to request attributes for same request restoration
                $request->attributes->set(self::REQUEST_ATTR_SAVED_SESSION, $savedData);

                // Save to persistent storage for next requests
                if ($needsUpdate) {
                    $session->set(self::SESSION_KEY_SAVED_DATA, $savedData);
                }
            }
        } catch (\RuntimeException $e) {
            return;
        }
    }

    /**
     * Restores CrefoPay PayPal Express session data after CrefoPay's listener runs.
     *
     * Strategy:
     * 1. Load saved data from request attributes (same request) or persistent storage (previous requests)
     * 2. Restore session values (transactionId, paypalExpressActive) if they were cleared
     * 3. Works for all paths, not just /checkout/confirm, to protect session during address validation
     * 4. Clears session data on /checkout/order or / (homepage) as checkout is complete
     */
    public function restoreSessionAfterCrefoPay(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (str_starts_with($path, '/api') || str_starts_with($path, '/admin') || str_starts_with($path, '/_wdt')) {
            return;
        }

        $salesChannelId = $this->getSalesChannelId($request);
        if (!$this->isCrefoPayPayPalCheckEnabled($salesChannelId)) {
            return;
        }

        // Clear session data on checkout completion or homepage
        if ($path === '/checkout/order' || $path === '/') {
            $this->clearSessionData($request);
            return;
        }

        // Try to load from request attributes first (same request), then from persistent storage
        $savedData = $request->attributes->get(self::REQUEST_ATTR_SAVED_SESSION);

        if ((!$savedData || !is_array($savedData)) && $request->hasSession()) {
            try {
                $session = $request->getSession();
                $savedData = $session->get(self::SESSION_KEY_SAVED_DATA);
            } catch (\RuntimeException $e) {
                return;
            }
        }

        if (!$savedData || !is_array($savedData)) {
            return;
        }

        if (!$request->hasSession()) {
            return;
        }

        try {
            $session = $request->getSession();

            // Restore session values if they were cleared by CrefoPay
            if (isset($savedData['crefopay-paypal-express-transaction'])) {
                $session->set('crefopay-paypal-express-transaction', $savedData['crefopay-paypal-express-transaction']);
            }

            if (isset($savedData['paypalExpressActive'])) {
                $_SESSION['paypalExpressActive'] = $savedData['paypalExpressActive'];
            }

            // Update persistent storage if needed
            $currentPersistentData = $session->get(self::SESSION_KEY_SAVED_DATA);
            if (!empty($savedData) && $currentPersistentData !== $savedData) {
                $session->set(self::SESSION_KEY_SAVED_DATA, $savedData);
            }
        } catch (\RuntimeException $e) {
            return;
        }
    }

    /**
     * Clears CrefoPay PayPal Express session data.
     *
     * Called when checkout is complete (/checkout/order) or user returns to homepage (/).
     * Removes all session variables and persistent storage related to CrefoPay PayPal Express.
     */
    private function clearSessionData(Request $request): void
    {
        if (!$request->hasSession()) {
            return;
        }

        try {
            $session = $request->getSession();

            // Clear session variables
            $session->remove('crefopay-paypal-express-transaction');
            if (isset($_SESSION['paypalExpressActive'])) {
                unset($_SESSION['paypalExpressActive']);
            }

            // Clear persistent storage
            $session->remove(self::SESSION_KEY_SAVED_DATA);

            // Clear request attributes
            $request->attributes->remove(self::REQUEST_ATTR_SAVED_SESSION);
        } catch (\RuntimeException $e) {
            return;
        }
    }

    private function getSalesChannelId(Request $request): ?string
    {
        $salesChannelId = $request->attributes->get('sw-sales-channel-id');
        if ($salesChannelId) {
            return (string) $salesChannelId;
        }

        return null;
    }

    private function isCrefoPayPayPalCheckEnabled(?string $salesChannelId): bool
    {
        if (!$this->enderecoService->isEnderecoPluginActive($salesChannelId)) {
            return false;
        }

        return $this->systemConfigService->getBool(
            self::CONFIG_KEY_CREFO_PAY_PAYPAL_CHECK,
            $salesChannelId
        );
    }
}
