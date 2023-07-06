<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Subscriber;

use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\EnderecoAddressExtensionEntity;
use Endereco\Shopware6Client\Model\FailedAddressCheckResult;
use Endereco\Shopware6Client\Service\EnderecoService;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Event\DataMappingEvent;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;

class AddressSubscriber implements EventSubscriberInterface
{
    protected SystemConfigService $systemConfigService;
    protected EnderecoService $enderecoService;
    protected EntityRepository $customerAddressRepository;
    protected EntityRepository $enderecoAddressExtensionRepository;
    protected EntityRepository $countryRepository;
    protected EntityRepository $countryStateRepository;
    protected RequestStack $requestStack;

    /** @var CustomerAddressEntity[] $addressEntityCache */
    private array $addressEntityCache = [];

    public function __construct(
        SystemConfigService $systemConfigService,
        EnderecoService $enderecoService,
        EntityRepository $customerAddressRepository,
        EntityRepository $enderecoAddressExtensionRepository,
        EntityRepository $countryRepository,
        EntityRepository $countryStateRepository,
        RequestStack $requestStack
    ) {
        $this->enderecoService = $enderecoService;
        $this->systemConfigService = $systemConfigService;
        $this->customerAddressRepository = $customerAddressRepository;
        $this->enderecoAddressExtensionRepository = $enderecoAddressExtensionRepository;
        $this->countryRepository = $countryRepository;
        $this->countryStateRepository = $countryStateRepository;
        $this->requestStack = $requestStack;
    }

    /**
     * Provides an array of events this subscriber wants to listen to.
     *
     * The method returns an array where the keys are event names and the values are method names of this class.
     * The methods linked to these events contain specific logic to run during those events.
     * The purpose of these events range from loading addresses, validating form submissions,
     * manipulating address data before writing to the database, and performing actions after the address is written.
     *
     * @return array<string, array<array<string>|string>> The array of subscribed events.
     */
    public static function getSubscribedEvents(): array
    {
        // This event is triggered when the address is loaded from the database.
        // You can add logic to ensure certain data in the address are properly set.
        $loadAddressEvents = [
            CustomerEvents::CUSTOMER_ADDRESS_LOADED_EVENT => ['ensureAddressesIntegrity'],
        ];

        // These events are called when a new or existing address form is submitted.
        // At this point, you can perform server-side validation.
        // Currently, we only check if street name and house number are set, if the split street feature is active.
        // We also save some accountable session id's for later accounting, if there are any.
        $formValidationEvents = [
            'framework.validation.address.create' => [
                ['modifyValidationConstraints'],['saveAccountableSessionForLater']
            ],
            'framework.validation.address.update' => [
                ['modifyValidationConstraints'],['saveAccountableSessionForLater']
            ]
        ];

        // These events are used to extend the data that are used when an address is written.
        // This is a place to manipulate the data before the address entity is written.
        $beforeAddressWritingEvents = [
            CustomerEvents::MAPPING_ADDRESS_CREATE => ['addMissingDataToAddressMapping'],
            CustomerEvents::MAPPING_REGISTER_ADDRESS_BILLING => ['addMissingDataToAddressMapping'],
            CustomerEvents::MAPPING_REGISTER_ADDRESS_SHIPPING => ['addMissingDataToAddressMapping'],
        ];

        // This event is used after the address has been written to the database.
        // This is generally a place to close the session regarding this address.
        $afterAddressWritingEvents = [
            CustomerEvents::CUSTOMER_ADDRESS_WRITTEN_EVENT => ['closeStoredSessions']
        ];

        // Merge all the events into a single array and return
        return array_merge(
            $loadAddressEvents,
            $formValidationEvents,
            $beforeAddressWritingEvents,
            $afterAddressWritingEvents
        );
    }

    /**
     * Ensures the integrity of all addresses loaded in the event.
     *
     * The function loops through all entities loaded in the event and performs certain operations if the entity
     * is an instance of CustomerAddressEntity. For each address entity, it ensures the address extension exists,
     * ensures the street is split, and checks if the existing customer address check or the PayPal checkout address
     * check is required. If either is required, it ensures the address status is set. After looping through all
     * address entities, it closes all stored sessions.
     *
     * @param EntityLoadedEvent $event The event that was triggered when entities were loaded.
     * @return void
     */
    public function ensureAddressesIntegrity(EntityLoadedEvent $event): void
    {
        $context = $event->getContext();

        // Retrieve the sales channel ID and check if Endereco service is active for the channel
        $salesChannelId = $this->enderecoService->fetchSalesChannelId($context);
        if (is_null($salesChannelId) || !$this->enderecoService->isEnderecoPluginActive($salesChannelId)) {
            return;
        }

        // Loop through all entities loaded in the event
        foreach ($event->getEntities() as $entity) {
            // Skip the entity if it's not a CustomerAddressEntity
            if (!$entity instanceof CustomerAddressEntity) {
                continue;
            }

            // IF the address has been processed already, we can be sure the database has all the information
            // So we just sync the entity with this information.
            $processedEntityIds = array_keys($this->addressEntityCache);
            if (in_array($entity->getId(), $processedEntityIds)) {
                $this->syncAddressEntity($entity, $context);
                continue;
            }

            $addressEntity = $entity;

            // Ensure the address extension exists and the street is split
            $this->ensureAddressExtensionExists($addressEntity, $context);
            $this->ensureTheStreetIsSplit($addressEntity, $context, $salesChannelId);

            // Determine if existing customer address check or PayPal checkout address check is required
            $existingCustomerCheckIsRelevant =
                $this->enderecoService->isExistingCustomerAddressCheckFeatureEnabled($salesChannelId)
                && !$this->enderecoService->isAddressFromRemote($addressEntity);

            $paypalExpressCheckoutCheckIsRelevant =
                $this->enderecoService->isPayPalCheckoutAddressCheckFeatureEnabled($salesChannelId)
                && $this->enderecoService->isAddressFromPayPal($addressEntity);

            // If either check is required, ensure the address status is set
            if ($existingCustomerCheckIsRelevant || $paypalExpressCheckoutCheckIsRelevant) {
                $this->ensureAddressStatusIsSet($addressEntity, $context, $salesChannelId);
            }
        }

        // Close all stored sessions after checking all addresses
        $this->enderecoService->closeStoredSessions($context, $salesChannelId);
    }

    /**
     * When the customer address entity is loaded, it can happen, that it doesnt have the address extension
     * and the relevant data from address check in the entity (but has it in the database), because
     * those data have been added in the same request process. So we use this method to update the data inside the
     * entity in order to have correct display on the frontend without having to reload the page.
     *
     * @param CustomerAddressEntity $addressEntity
     * @param Context $context
     * @return void
     */
    public function syncAddressEntity(CustomerAddressEntity $addressEntity, Context $context): void
    {
        if (!array_key_exists($addressEntity->getId(), $this->addressEntityCache)) {
            return;
        }

        /** @var CustomerAddressEntity $processedEntity */
        $processedEntity = $this->addressEntityCache[$addressEntity->getId()];

        /** @var EnderecoAddressExtensionEntity $processedAddressExtension */
        $processedAddressExtension = $processedEntity->getExtension('enderecoAddress');

        /** @var EnderecoAddressExtensionEntity|null $toAddressExtension */
        $toAddressExtension = $addressEntity->getExtension('enderecoAddress');

        if (is_null($toAddressExtension)) {
            // Create a new extension.
            $toAddressExtension = new EnderecoAddressExtensionEntity();

            // Add the new extension to the address entity.
            $addressEntity->addExtension('enderecoAddress', $toAddressExtension);
        }

        $addressEntity->setZipcode($processedEntity->get('zipcode'));
        $addressEntity->setCity($processedEntity->get('city'));
        $addressEntity->setStreet($processedEntity->get('street'));
        $addressEntity->setCountryStateId($processedEntity->get('countryStateId'));

        $toAddressExtension->setStreet($processedAddressExtension->get('street'));
        $toAddressExtension->setHouseNumber($processedAddressExtension->get('houseNumber'));
        $toAddressExtension->setAmsStatus($processedAddressExtension->get('amsStatus'));
        $toAddressExtension->setAmsPredictions($processedAddressExtension->get('amsPredictions'));
        $toAddressExtension->setAmsTimestamp($processedAddressExtension->get('amsTimestamp'));
    }


    /**
     * Ensures that an address status is set by checking and applying the result from the Endereco API.
     *
     * This method checks whether a new status is needed for the address. If so, it uses the Endereco service
     * to check the address. If the check fails, it doesn't throw an exception but simply stops.
     *
     * If a session ID was used in the check, it adds it to the accountable session IDs storage.
     * Then, it applies the check result to the address entity.
     *
     * @param CustomerAddressEntity $addressEntity The customer address to be checked.
     * @param Context $context The context which includes details of the event triggering this method.
     * @param string $salesChannelId The ID of the sales channel the address is associated with.
     *
     * @return void
     */
    public function ensureAddressStatusIsSet(
        CustomerAddressEntity $addressEntity,
        Context $context,
        string $salesChannelId
    ): void {
        /** @var EnderecoAddressExtensionEntity $addressExtension */
        $addressExtension = $addressEntity->getExtension('enderecoAddress');

        if ($this->isNewStatusNeededForAddressExtension($addressExtension)) {
            $addressCheckResult = $this->enderecoService->checkAddress($addressEntity, $context, $salesChannelId);

            // We dont throw exceptions, we just gracefully stop here. Maybe the API will be available later again.
            if ($addressCheckResult instanceof FailedAddressCheckResult) {
                return;
            }

            if (!empty($addressCheckResult->getUsedSessionId())) {
                $this->enderecoService->addAccountableSessionIdsToStorage([$addressCheckResult->getUsedSessionId()]);
            }

            // Here we save the status codes and predictions. If it's an automatic correction, then we also save
            // the data from the correction to customer address entity and generate a new,
            // "virtual" address check result.
            $this->enderecoService->applyAddressCheckResult($addressCheckResult, $addressEntity, $context);

            // Cache the entity, in case others entities might need an update. We will just copy values from this one.
            $this->addressEntityCache[$addressEntity->getId()] = $addressEntity;
        }
    }

    /**
     * Determines whether the given EnderecoAddressExtensionEntity requires a new address management
     * system (AMS) status.
     *
     * This method accepts an EnderecoAddressExtensionEntity and retrieves its current AMS status. It then checks if the
     * current status is empty or has the default value (as defined by the AMS_STATUS_NOT_CHECKED constant).
     *
     * If the current status is empty or has the default value, this method returns true, indicating that a new status
     * check is needed. If the current status is neither empty nor the default value, the method returns false,
     * indicating that no new status check is required.
     *
     * @param EnderecoAddressExtensionEntity $addressExtension The Endereco address extension entity for which to
     *                                                         determine the need for a new AMS status check.
     *
     * @return bool Returns true if a new AMS status check is needed, false otherwise.
     */
    public function isNewStatusNeededForAddressExtension(EnderecoAddressExtensionEntity $addressExtension): bool
    {
        $currentStatus = $addressExtension->getAmsStatus();

        $isEmpty = empty($currentStatus);
        $hasDefaultValue =  ($currentStatus === EnderecoAddressExtensionEntity::AMS_STATUS_NOT_CHECKED);

        $isCheckNeeded = $isEmpty || $hasDefaultValue;

        return $isCheckNeeded;
    }


    /**
     * Modifies form validation rules during a BuildValidationEvent.
     *
     * This method checks if the street splitting feature is enabled for the given sales channel.
     * If the feature is enabled, it adds the NotBlank() validation rule to 'enderecoStreet' and 'enderecoHousenumber',
     * and sets the 'street' field as Optional().
     * This is because when street splitting is enabled, the address input form replaces the 'street' field
     * with 'enderecoStreet' and 'enderecoHousenumber'. Therefore, we need to ensure that these fields are filled,
     * while disabling the validation for the original 'street' field so that the form can be submitted.
     *
     * @param BuildValidationEvent $event The event that triggers form validation.
     * @return void
     */
    public function modifyValidationConstraints(BuildValidationEvent $event): void
    {
        // Fetch context and sales channel ID
        $context = $event->getContext();
        $salesChannelId = $this->enderecoService->fetchSalesChannelId($context);

        // Break execution if there is no sales channel id.
        if (is_null($salesChannelId)) {
            return;
        }

        // Check if the street splitting feature is enabled for the sales channel
        if ($this->enderecoService->isStreetSplittingFeatureEnabled($salesChannelId)) {
            // Fetch the form definition
            $definition = $event->getDefinition();

            // If street splitting is enabled, add NotBlank validation rule
            // to 'enderecoStreet' and 'enderecoHousenumber'
            $definition->add('enderecoStreet', new NotBlank());
            $definition->add('enderecoHousenumber', new NotBlank());

            // And set the 'street' field as optional since it is replaced in the frontend form
            $definition->set('street', new Optional());
        }
    }

    /**
     * Handles the creation of a data mapping.
     *
     * This method ensures the correct format and content of an address during the creation of a data mapping.
     * It takes into account whether the street splitting feature is enabled and provides default values where needed.
     * The function also handles addresses coming from PayPal Express Checkout and sets appropriate flags.
     *
     * @param DataMappingEvent $event The data mapping event instance.
     * @return void
     */
    public function addMissingDataToAddressMapping(DataMappingEvent $event): void
    {
        // Get context and SalesChannel ID for current operation
        $context = $event->getContext();
        $salesChannelId = $this->enderecoService->fetchSalesChannelId($context);

        if (is_null($salesChannelId)) {
            return;
        }

        $input = $event->getInput();
        $output = $event->getOutput();

        // Add relevant endereco data.
        $output['extensions'] = [
            'enderecoAddress' => [
                'street' => $input->get('enderecoStreet', ''),
                'houseNumber' => $input->get('enderecoHousenumber', ''),
                'amsStatus' => $input->get('amsStatus') ?? EnderecoAddressExtensionEntity::AMS_STATUS_NOT_CHECKED,
                'amsTimestamp' => (int) $input->get('amsTimestamp'),
                'amsPredictions' => json_decode($input->get('amsPredictions'), true) ?? [],
                'isPayPalAddress' => $this->isPaypalCheckoutRequest()
            ]
        ];

        // Make sure the default street and endereco street name and house number are synchronized.
        $this->enderecoService->syncStreet($output, $context, $salesChannelId);

        // Update the output data in the event
        $event->setOutput($output);
    }

    /**
     * If validation fails for some reason and the user is returned to the original form, then the session id for
     * endereco service is generated a new. Is the second submit successsfull, then the first session id is lost.
     * With this method we try to save the session id (if its relevant), so we can do doaccounting later.
     * Delayed doaccounting can be also used for other serverside checks, that dont rely on $_POST variable.
     *
     * We don't send doAccounting at this point. It will actually happen after the address is saved.
     *
     * @param BuildValidationEvent $event
     * @return void
     */
    public function saveAccountableSessionForLater(BuildValidationEvent $event)
    {
        $isPostRequest =
            is_array($_SERVER)
            && array_key_exists('REQUEST_METHOD', $_SERVER)
            && 'POST' === $_SERVER['REQUEST_METHOD']
            && $_POST;

        if ($isPostRequest) {
            // Look for accountable session id's in $_POST
            $accountableSessionIds = $this->enderecoService->findAccountableSessionIds($_POST);

            // Save them to session variable, if any found.
            if (!empty($accountableSessionIds)) {
                $this->enderecoService->addAccountableSessionIdsToStorage($accountableSessionIds);
            }
        }
    }

    /**
     * Checks if the current request is a PayPal Checkout request.
     *
     * This method retrieves the current request from the request stack and checks if the path info
     * contains '/store-api/paypal'. If so, the method returns true, indicating that the request is
     * a PayPal Checkout request. If the current request cannot be fetched or the path info
     * does not contain '/store-api/paypal', the method returns false.
     *
     * This method is useful for determining if specific logic needs to be run for PayPal Checkout requests.
     *
     * @return bool Returns true if the current request is a PayPal Checkout request, false otherwise.
     */
    private function isPaypalCheckoutRequest(): bool
    {
        // Fetch the current request
        $currentRequest = $this->requestStack->getCurrentRequest();

        // If there is no current request, return false
        if (!$currentRequest) {
            return false;
        }

        // Check if the path info of the current request contains '/store-api/paypal' and return the result
        return str_contains($currentRequest->getPathInfo(), '/store-api/paypal');
    }

    /**
     * Handles closing of stored sessions upon an entity write event.
     *
     * This method is triggered by an EntityWrittenEvent and fetches the event's context.
     * It then uses the Endereco service to close any stored sessions within that context.
     *
     * @param EntityWrittenEvent $event The event that triggers this method.
     *
     * @return void
     */
    public function closeStoredSessions(EntityWrittenEvent $event): void
    {
        // Fetch the context in which this event was triggered
        $context = $event->getContext();

        // Get id of the sales channel.
        $salesChannelId = $this->enderecoService->fetchSalesChannelId($context);

        // Break if there is no sales channel id.
        if (is_null($salesChannelId)) {
            return;
        }

        // Close sessions.
        $this->enderecoService->closeStoredSessions($context, $salesChannelId);
    }

    /**
     * Ensures that a corresponding EnderecoAddressExtension entry exists for a given address entity.
     *
     * This method accepts a CustomerAddressEntity and a Context. It checks if the address entity has a corresponding
     * EnderecoAddressExtension (indicated by the presence of the 'enderecoAddress' extension).
     *
     * If the address entity does not have a corresponding EnderecoAddressExtension, this method creates one with
     * default values
     * (such as a default status and an empty list of predictions) using the EnderecoAddressExtensionRepository, and
     * adds it to the address entity.
     *
     * This ensures that the address entity has a corresponding EnderecoAddressExtension, which is important for
     * managing additional address-related information provided by the Endereco service.
     *
     * @param CustomerAddressEntity $addressEntity The address entity for which to ensure the existence of
     *                                             a corresponding EnderecoAddressExtension.
     * @param Context $context The context in which the address entity is being handled.
     *
     * @return void
     */
    private function ensureAddressExtensionExists(CustomerAddressEntity $addressEntity, Context $context): void
    {
        // Check if the address has an 'enderecoAddress' extension
        $enderecoAddressExtension = $addressEntity->getExtension('enderecoAddress');

        if (!$enderecoAddressExtension) {
            // If it doesn't exist, create a new one with default values
            $this->enderecoAddressExtensionRepository->upsert([[
                'addressId' => $addressEntity->getId(),
                'amsStatus' => EnderecoAddressExtensionEntity::AMS_STATUS_NOT_CHECKED,
                'amsPredictions' => []
            ]], $context);

            // Add the new extension to the address entity
            $addressEntity->addExtension('enderecoAddress', new EnderecoAddressExtensionEntity());
        }
    }

    /**
     * Ensures that the full street address of a given address entity is properly split into street name and building
     * number.
     *
     * This method accepts a CustomerAddressEntity and a Context. It retrieves the corresponding
     * EnderecoAddressExtensionEntity for the address and the full street address stored in the CustomerAddressEntity.
     * It checks whether a street splitting operation is needed by comparing the expected full street (constructed using
     * data from the EnderecoAddressExtensionEntity) with the current full street.
     *
     * If the street address is not empty and street splitting is needed, the method splits the full street address into
     * street name and building number using the 'splitStreet' method of the Endereco service. The country code for
     * splitting the street is retrieved using the 'getCountryCodeById' method (defaulting to 'DE' if unknown). The
     * split street name and building number are then saved back into the EnderecoAddressExtensionEntity for the
     * address.
     *
     * @param CustomerAddressEntity $addressEntity The address entity for which to ensure the street is split.
     * @param Context $context The context in which the address entity is being handled.
     * @param string $salesChannelId The ID of the sales channel the address is associated with.
     *
     * @return void
     */
    public function ensureTheStreetIsSplit(
        CustomerAddressEntity $addressEntity,
        Context $context,
        string $salesChannelId
    ): void {
        /** @var EnderecoAddressExtensionEntity $addressExtension */
        $addressExtension = $addressEntity->getExtension('enderecoAddress');

        $fullStreet = $addressEntity->getStreet();

        if (!empty($fullStreet) && $this->isStreetSplitNeeded($addressEntity, $addressExtension, $context)) {
            // If country is unknown, use Germany as default
            $countryCode = $this->enderecoService->getCountryCodeById(
                $addressEntity->getCountryId(),
                $context,
                'DE'
            );

            list($streetName, $buildingNumber) = $this->enderecoService->splitStreet(
                $fullStreet,
                $countryCode,
                $context,
                $salesChannelId
            );

            $this->enderecoAddressExtensionRepository->upsert(
                [[
                    'addressId' => $addressEntity->getId(),
                    'street' => $streetName,
                    'houseNumber' => $buildingNumber
                ]],
                $context
            );

            $addressExtension->setStreet($streetName);
            $addressExtension->setHouseNumber($buildingNumber);
        }
    }

    /**
     * Determines whether a street splitting operation is necessary for the given address.
     *
     * This method accepts a CustomerAddressEntity, a corresponding EnderecoAddressExtensionEntity,
     * and the context of the current execution. It constructs an expected full street string using the
     * street and house number from the EnderecoAddressExtensionEntity, along with the country ISO code
     * from the CustomerAddressEntity.
     *
     * The expected full street string is then compared to the current full street string stored
     * in the CustomerAddressEntity. The country code is fetched by the `getCountryCodeById` method
     * and 'DE' is used as a default if the country code cannot be determined.
     *
     * If the expected and current full street strings do not match, the method returns true,
     * indicating that a street splitting operation is necessary. If they do match, the method
     * returns false, indicating that no street splitting operation is required.
     *
     * @param CustomerAddressEntity $addressEntity The address entity for which to determine the need for street
     *                                             splitting.
     * @param EnderecoAddressExtensionEntity $addressExtension The corresponding Endereco address extension for the
     *                                                         address entity.
     * @param Context $context The context of the current execution.
     *
     * @return bool Returns true if street splitting is needed, false otherwise.
     */
    public function isStreetSplitNeeded(
        CustomerAddressEntity $addressEntity,
        EnderecoAddressExtensionEntity $addressExtension,
        Context $context
    ): bool {
        // Construct the expected full street string
        $expectedFullStreet = $this->enderecoService->buildFullStreet(
            $addressExtension->getStreet(),
            $addressExtension->getHouseNumber(),
            $this->enderecoService->getCountryCodeById($addressEntity->getCountryId(), $context, 'DE')
        );

        // Fetch the current full street string from the address entity
        $currentFullStreet = $addressEntity->getStreet();

        // Compare the expected and current full street strings and return the result
        return $expectedFullStreet !== $currentFullStreet;
    }
}
