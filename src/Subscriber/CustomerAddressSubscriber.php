<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Subscriber;

use Endereco\Shopware6Client\Entity\CustomerAddress\CustomerAddressExtension;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\EnderecoBaseAddressExtensionEntity;
use Endereco\Shopware6Client\Service\AddressCheck\AddressCheckPayloadBuilderInterface;
use Endereco\Shopware6Client\Service\AddressCheck\CountryCodeFetcherInterface;
use Endereco\Shopware6Client\Service\AddressIntegrity\CustomerAddressIntegrityInsuranceInterface;
use Endereco\Shopware6Client\Service\EnderecoService;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Event\DataMappingEvent;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;
use Shopware\Core\Content\ImportExport\Event\ImportExportBeforeImportRecordEvent;
use Shopware\Core\Content\ImportExport\Event\ImportExportAfterImportRecordEvent;
use Shopware\Core\Content\ImportExport\Event\ImportExportExceptionImportRecordEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Checkout\Customer\CustomerDefinition;

class CustomerAddressSubscriber implements EventSubscriberInterface
{
    protected SystemConfigService $systemConfigService;
    protected EnderecoService $enderecoService;
    protected EntityRepository $customerRepository;
    protected EntityRepository $customerAddressRepository;
    protected EntityRepository $enderecoAddressExtensionRepository;
    protected EntityRepository $countryRepository;
    protected EntityRepository $countryStateRepository;
    protected CountryCodeFetcherInterface $countryCodeFetcher;
    private CustomerAddressIntegrityInsuranceInterface $customerAddressIntegrityInsurance;
    protected RequestStack $requestStack;
    private AddressCheckPayloadBuilderInterface $addressCheckPayloadBuilder;

    public function __construct(
        AddressCheckPayloadBuilderInterface $addressCheckPayloadBuilder,
        SystemConfigService $systemConfigService,
        EnderecoService $enderecoService,
        EntityRepository $customerRepository,
        EntityRepository $customerAddressRepository,
        EntityRepository $enderecoAddressExtensionRepository,
        EntityRepository $countryRepository,
        EntityRepository $countryStateRepository,
        CountryCodeFetcherInterface $countryCodeFetcher,
        CustomerAddressIntegrityInsuranceInterface $customerAddressIntegrityInsurance,
        RequestStack $requestStack
    ) {
        $this->addressCheckPayloadBuilder = $addressCheckPayloadBuilder;
        $this->enderecoService = $enderecoService;
        $this->systemConfigService = $systemConfigService;
        $this->customerRepository = $customerRepository;
        $this->customerAddressRepository = $customerAddressRepository;
        $this->enderecoAddressExtensionRepository = $enderecoAddressExtensionRepository;
        $this->countryRepository = $countryRepository;
        $this->countryStateRepository = $countryStateRepository;
        $this->countryCodeFetcher = $countryCodeFetcher;
        $this->customerAddressIntegrityInsurance = $customerAddressIntegrityInsurance;
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

        // These three events are used to recognize a running import to optionally deactivate checks.
        $importExportEvents = [
            ImportExportBeforeImportRecordEvent::class => ['onBeforeImportRecord'],
            ImportExportAfterImportRecordEvent::class => ['onAfterImportRecord'],
            ImportExportExceptionImportRecordEvent::class => ['onImportFailed'],
        ];

        // Merge all the events into a single array and return
        return array_merge(
            $loadAddressEvents,
            $formValidationEvents,
            $beforeAddressWritingEvents,
            $afterAddressWritingEvents,
            $importExportEvents
        );
    }

    /**
     * Handles the event before an import record is processed.
     *
     * This method is triggered before each record is imported during an import process.
     * It sets the internal `isImport` flag to true, indicating that an import operation
     * is currently in progress. This flag can be used elsewhere in the class to modify
     * behavior specific to import operations.
     *
     * @param ImportExportBeforeImportRecordEvent $event The event object containing information
     *                                                   about the import process.
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function onBeforeImportRecord(ImportExportBeforeImportRecordEvent $event): void
    {
        $this->enderecoService->isImport = true;
    }

    /**
     * Handles the event after an import record has been processed.
     *
     * This method is triggered after each record has been imported during an import process.
     * It resets the internal `isImport` flag to `false`, indicating the completion of the import operation
     * for the current record.
     *
     * @param ImportExportAfterImportRecordEvent $event The event object containing the result of the import process.
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function onAfterImportRecord(ImportExportAfterImportRecordEvent $event): void
    {
        $this->enderecoService->isImport = false;
    }

    /**
     * Handles the event when an import record fails to process.
     *
     * This method is triggered when an exception occurs during the import of a record.
     * It resets the internal `isImport` flag to `false`, indicating that the import operation
     * for the current record has failed and any special handling for import operations should be turned off.
     *
     * @param ImportExportExceptionImportRecordEvent $event The event object containing information
     *                                                      about the import exception.
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function onImportFailed(ImportExportExceptionImportRecordEvent $event): void
    {
        $this->enderecoService->isImport = false;
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
        $this->enderecoService->isProcessingInsurances = true;
        foreach ($event->getEntities() as $entity) {
            // Skip the entity if it's not a CustomerAddressEntity
            if (!$entity instanceof CustomerAddressEntity) {
                continue;
            }

            $this->customerAddressIntegrityInsurance->ensure($entity, $context);
        }
        $this->enderecoService->isProcessingInsurances = false;

        // Close all stored sessions after checking all addresses
        $this->enderecoService->closeStoredSessions($context, $salesChannelId);
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

        $formData = $event->getData();

        // Check if the street splitting feature is enabled for the sales channel
        if ($this->isStreetSplittingFieldsValidationNeeded($formData)) {
            // Fetch the form definition
            $definition = $event->getDefinition();

            /* Check if 'pickwareDhlAddressRadioGroup' is present and 'regular'
               to ensure compatibility with template changes by PickwareDhl */
            if (
                $event->getData()->has('pickwareDhlAddressRadioGroup') &&
                $event->getData()->only('pickwareDhlAddressRadioGroup') != 'regular'
            ) {
                // If street splitting is enabled, add NotBlank validation rule
                // to 'enderecoStreet' and 'enderecoHousenumber'
                $definition->add('enderecoStreet', new Optional());
                $definition->add('enderecoHousenumber', new Optional());
            } else {
                // Disable required-validation in case of PickwareDhl functionality use
                $definition->add('enderecoStreet', new NotBlank());
                $definition->add('enderecoHousenumber', new NotBlank());
            }

            // And set the 'street' field as optional since it is replaced in the frontend form
            $definition->set('street', new Optional());
        }
    }

    /**
     * Checks if street splitting fields validation is needed based on the contents of the DataBag.
     *
     * The function checks if the given DataBag contains a 'billingAddress' or a 'shippingAddress'.
     * If 'billingAddress' is present, it will use it. If not, it checks for 'shippingAddress' and uses it.
     * Lastly, it checks if 'enderecoStreet' is present in the chosen address and returns this information as a boolean.
     *
     * @param DataBag $address The data bag object containing address information.
     *
     * @return bool Returns true if 'enderecoStreet' exists in the chosen address, false otherwise.
     */
    private function isStreetSplittingFieldsValidationNeeded(DataBag $address): bool
    {
        if ($address->has('billingAddress')) {
            $address =  $address->get('billingAddress');
        } elseif ($address->has('shippingAddress')) {
            $address =  $address->get('billingAddress');
        }

        $validationCustomRulesNeeded = $address->has('enderecoStreet');

        return $validationCustomRulesNeeded;
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

        if (is_null($input->get('amsPredictions'))) {
            $predictions = [];
        } else {
            $predictions = json_decode($input->get('amsPredictions'), true) ?? [];
        }

        // Add relevant endereco data.
        $output['extensions'] = [
            CustomerAddressExtension::ENDERECO_EXTENSION => [
                'street' => $input->get('enderecoStreet', ''),
                'houseNumber' => $input->get('enderecoHousenumber', ''),
                'amsStatus' => $input->get('amsStatus') ?? EnderecoBaseAddressExtensionEntity::AMS_STATUS_NOT_CHECKED,
                'amsTimestamp' => (int) $input->get('amsTimestamp', 0),
                'amsPredictions' => $predictions,
                'isPayPalAddress' => false // We will calculate it later.
            ]
        ];

        // Make sure the default street and endereco street name and house number are synchronized.
        $this->enderecoService->syncStreet($output, $context, $salesChannelId);

        // Calculate payload
        $payloadBody = $this->addressCheckPayloadBuilder->buildFromArray(
            [
                'countryId' => $output['countryId'],
                'countryStateId' => $output['countryStateId'] ?? null,
                'zipcode' => $output['zipcode'],
                'city' => $output['city'],
                'street' => $output['street'],
                'additionalAddressLine1' => $output['additionalAddressLine1'] ?? null,
                'additionalAddressLine2' => $output['additionalAddressLine2'] ?? null,
            ],
            $context
        );
        $output['extensions'][CustomerAddressExtension::ENDERECO_EXTENSION]['amsRequestPayload']
            = $payloadBody->toJSON();

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
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
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
}
