<?php
namespace Endereco\Shopware6Client\Service;

use Shopware\Storefront\Page\GenericPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use GuzzleHttp\Exception\RequestException;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class EnderecoService implements EventSubscriberInterface
{
    /**
     * @var SystemConfigService
     */
    private $systemConfigService;
    private $httpClient;
    private $apiKey;
    private $serviceUrl;
    private $pluginRepository;

    public function __construct(SystemConfigService $systemConfigService, $pluginRepository)
    {
        $this->systemConfigService = $systemConfigService;
        $this->httpClient = new \GuzzleHttp\Client(['timeout' => 3.0, 'connection_timeout' => 2.0]);

        $this->apiKey = $systemConfigService->get('EnderecoShopware6Client.config.enderecoApiKey');
        $this->serviceUrl = $systemConfigService->get('EnderecoShopware6Client.config.enderecoRemoteUrl');

        $this->pluginRepository = $pluginRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CustomerEvents::CUSTOMER_ADDRESS_WRITTEN_EVENT => 'extractAndAccountSessions',
        ];
    }

    public function extractAndAccountSessions(EntityWrittenEvent $event)
    {
        $accountableSessionIds = array();

        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            foreach ($_POST as $sVarName => $sVarValue) {

                if ((strpos($sVarName, '_session_counter') !== false) && 0 < intval($sVarValue)) {
                    $sSessionIdName = str_replace('_session_counter', '', $sVarName) . '_session_id';
                    $accountableSessionIds[$_POST[$sSessionIdName]] = true;
                }
            }

            $accountableSessionIds = array_keys($accountableSessionIds);

            if (!empty($accountableSessionIds)) {
                $this->closeSessions($accountableSessionIds, $event);
            }
        }
    }

    public function generateTid()
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public function closeSessions($sessionIds = [], $event)
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
                    if (500 <= $response->getStatusCode()) {
                        // TODO: log
                    }
                }
            } catch(\Exception $e) {
                // TODO: log
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
                    if (500 <= $response->getStatusCode()) {
                        // TODO: log
                    }
                }
            } catch(\Exception $e) {
                // TODO: log
            }
        }

        return;
    }
}
