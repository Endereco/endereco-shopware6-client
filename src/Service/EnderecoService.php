<?php
namespace Endereco\Shopware6Client\Service;

use GuzzleHttp\Client;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Storefront\Page\GenericPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use GuzzleHttp\Exception\RequestException;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Psr\Log\LoggerInterface;

class EnderecoService implements EventSubscriberInterface
{
    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @var Client
     */
    private $httpClient;

    /**
     * @var string
     */
    private $apiKey;

    /**
     * @var string
     */
    private $serviceUrl;

    /**
     * @var EntityRepository
     */
    private $pluginRepository;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        SystemConfigService $systemConfigService,
        EntityRepository $pluginRepository,
        LoggerInterface $logger)
    {
        $this->systemConfigService = $systemConfigService;
        $this->httpClient = new Client(['timeout' => 3.0, 'connection_timeout' => 2.0]);

        $this->apiKey = $systemConfigService->getString('EnderecoShopware6Client.config.enderecoApiKey') ?? '';
        $this->serviceUrl = $systemConfigService->getString('EnderecoShopware6Client.config.enderecoRemoteUrl') ?? '';

        $this->pluginRepository = $pluginRepository;

        $this->logger = $logger;
    }

    /** @return array<string, string> */
    public static function getSubscribedEvents(): array
    {
        return [
            CustomerEvents::CUSTOMER_ADDRESS_WRITTEN_EVENT => 'extractAndAccountSessions',
        ];
    }

    public function extractAndAccountSessions(EntityWrittenEvent $event): void
    {
        /**
         * @var array<string, boolean>
         */
        $accountableSessionIds = array();

        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            foreach ($_POST as $sVarName => $sVarValue) {

                if ((strpos($sVarName, '_session_counter') !== false) && 0 < intval($sVarValue)) {
                    $sSessionIdName = str_replace('_session_counter', '', $sVarName) . '_session_id';
                    $accountableSessionIds[$_POST[$sSessionIdName]] = true;
                }
            }

            $accountableSessionIds = array_map('strval', array_keys($accountableSessionIds));

            if (!empty($accountableSessionIds)) {
                $this->closeSessions($accountableSessionIds, $event);
            }
        }
    }

    /** @return string */
    public function generateTid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
    /**
     * @param array<int, string> $sessionIds
     * @param EntityWrittenEvent $event
     */
    public function closeSessions(array $sessionIds, EntityWrittenEvent $event): void
    {
        $anyDoAccounting = false;

        $context = $event->getContext();
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'EnderecoShopware6Client'));
        $enderecoAgentInfo = 'Endereco Shopware6 Client v' . $this->pluginRepository->search($criteria, $context)->first()->getVersion();

        foreach ($sessionIds as $sessionId) {
            try {
                $message = array(
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'method' => 'doAccounting',
                    'params' => array(
                        'sessionId' => $sessionId
                    )
                );
                $newHeaders = array(
                    'Content-Type' => 'application/json',
                    'X-Auth-Key' => $this->apiKey,
                    'X-Transaction-Id' => $sessionId,
                    'X-Transaction-Referer' => $_SERVER['HTTP_REFERER']?$_SERVER['HTTP_REFERER']:__FILE__,
                    'X-Agent' => $enderecoAgentInfo,
                );
                $this->httpClient->post(
                    $this->serviceUrl,
                    array(
                        'headers' => $newHeaders,
                        'body' => json_encode($message)
                    )
                );
                $anyDoAccounting = true;

            } catch (RequestException $e) {
                if ($e->hasResponse()) {
                    $response = $e->getResponse();
                    if ($response && 500 <= $response->getStatusCode()) {
                        $this->logger->error('Serverside doAccounting failed', ['error' => $e->getMessage()]);
                    }
                }
            } catch(\Exception $e) {
                $this->logger->error('Serverside doAccounting failed', ['error' => $e->getMessage()]);
            }
        }

        if ($anyDoAccounting) {
            try {
                $message = array(
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'method' => 'doConversion',
                    'params' => array()
                );
                $newHeaders = array(
                    'Content-Type' => 'application/json',
                    'X-Auth-Key' => $this->apiKey,
                    'X-Transaction-Id' => 'not_required',
                    'X-Transaction-Referer' => $_SERVER['HTTP_REFERER']?$_SERVER['HTTP_REFERER']:__FILE__,
                    'X-Agent' => $enderecoAgentInfo,
                );
                $this->httpClient->post(
                    $this->serviceUrl,
                    array(
                        'headers' => $newHeaders,
                        'body' => json_encode($message)
                    )
                );
            } catch (RequestException $e) {
                if ($e->hasResponse()) {
                    $response = $e->getResponse();
                    if ($response && 500 <= $response->getStatusCode()) {
                        $this->logger->warning('Serverside doConversion failed', ['error' => $e->getMessage()]);
                    }
                }
            } catch(\Exception $e) {
                $this->logger->warning('Serverside doConversion failed', ['error' => $e->getMessage()]);
            }
        }
    }
}
