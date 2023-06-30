<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Subscriber;

use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\EnderecoAddressExtensionEntity;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Event\DataMappingEvent;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Component\Validator\Constraints\NotBlank;

class AddressSubscriber extends AbstractEnderecoSubscriber
{
    private array $checkedAddressIds = [];
    private array $splittedAddressIds = [];
    private array $automaticallySelectedAddressIds = [];
    private array $selectedByCustomerAddressIds = [];

    public static function getSubscribedEvents(): array
    {
        return [
            'framework.validation.address.create' => 'onFormValidation',
            'framework.validation.address.update' => 'onFormValidation',
            CustomerEvents::MAPPING_ADDRESS_CREATE => 'onMappingCreate',
            CustomerEvents::MAPPING_REGISTER_ADDRESS_BILLING => 'onMappingCreate',
            CustomerEvents::MAPPING_REGISTER_ADDRESS_SHIPPING => 'onMappingCreate',
            CustomerEvents::CUSTOMER_ADDRESS_LOADED_EVENT => 'onAddressLoaded',
            CustomerEvents::CUSTOMER_ADDRESS_WRITTEN_EVENT => [['extractAndAccountSessions'], ['clearAmsStatus']]
        ];
    }

    public function onAddressLoaded(EntityLoadedEvent $event): void
    {
        $salesChannelId = $this->fetchSalesChannelId($event->getContext());
        if (is_null($salesChannelId) || !$this->isEnderecoActive($salesChannelId)) {
            return;
        }

        $this->checkEnderecoExtension($event);
        $this->checkAddress($event, $salesChannelId);
        $this->checkStreetField($event);
    }

    public function clearAmsStatus(EntityWrittenEvent $event): void
    {
        foreach ($event->getWriteResults() as $writeResult) {
            if ($writeResult->getEntityName() !== CustomerAddressDefinition::ENTITY_NAME) {
                continue;
            }
            $addressId = $writeResult->getPrimaryKey();

            if (
                in_array($addressId, $this->automaticallySelectedAddressIds) ||
                in_array($addressId, $this->selectedByCustomerAddressIds)
            ) {
                continue;
            }
            $this->enderecoAddressExtensionRepository->upsert([[
                'addressId' => $addressId,
                'amsStatus' => EnderecoAddressExtensionEntity::AMS_STATUS_NOT_CHECKED
            ]], $event->getContext());
        }
    }

    public function onFormValidation(BuildValidationEvent $event): void
    {
        $context = $event->getContext();
        $salesChannelId = $this->fetchSalesChannelId($context);
        if (!$this->isStreetSplittingEnabled($salesChannelId)) {
            return;
        }
        $data = $event->getData();
        $address = $data->get('address');
        $billingAddress = $data->get('billingAddress');
        $shippingAddress = $data->get('shippingAddress');

        $this->overrideStreetWithSplittedData($data, $context);
        $this->overrideStreetWithSplittedData($address, $context);
        $this->overrideStreetWithSplittedData($billingAddress, $context);
        $this->overrideStreetWithSplittedData($shippingAddress, $context);

        $definition = $event->getDefinition();
        $definition->add('enderecoStreet', new NotBlank());
        $definition->add('enderecoHousenumber', new NotBlank());
    }

    public function onMappingCreate(DataMappingEvent $event): void
    {
        $context = $event->getContext();
        $salesChannelId = $this->fetchSalesChannelId($context);

        $data = $event->getInput();
        $output = $event->getOutput();
        $hasModification = false;
        $enderecoExtension = [];
        if ($this->isStreetSplittingEnabled($salesChannelId)) {
            $enderecoStreet = $data->get('enderecoStreet');
            $enderecoHousenumber = $data->get('enderecoHousenumber');

            if ($enderecoStreet && $enderecoHousenumber) {
                $country = $this->fetchCountry($data->get('countryId', ''), $context);
                $output['street'] = $this->enderecoService->buildFullStreet(
                    $enderecoStreet,
                    $enderecoHousenumber,
                    $country ? $country->getIso() : ''
                );
                $enderecoExtension['street'] = $enderecoStreet;
                $enderecoExtension['houseNumber'] = $enderecoHousenumber;
                $hasModification = true;
            }
        }

        $addressId = $data->get('id');
        $amsStatus = $data->get('amsStatus');
        if (!empty($amsStatus) && !empty($addressId)) {
            $hasModification = true;
            $enderecoExtension['amsStatus'] = $amsStatus;

            if (str_contains($amsStatus, EnderecoAddressExtensionEntity::AMS_STATUS_SELECTED_BY_CUSTOMER)) {
                $this->selectedByCustomerAddressIds[] = $addressId;
            }
        }


        if ($this->isPaypalCheckoutRequest()) {
            $hasModification = true;
            $enderecoExtension['isPayPalAddress'] = true;
        }

        if ($hasModification) {
            $output['extensions']['enderecoAddress'] = $enderecoExtension;
            $event->setOutput($output);
        }
    }

    public function extractAndAccountSessions(EntityWrittenEvent $event): void
    {
        $accountableSessionIds = [];
        $isPostRequest =
            isset($_SERVER)
            && is_array($_SERVER)
            && array_key_exists('REQUEST_METHOD', $_SERVER)
            && 'POST' === $_SERVER['REQUEST_METHOD'];

        if ($isPostRequest) {
            foreach ($_POST as $sVarName => $sVarValue) {
                if ((strpos($sVarName, '_session_counter') !== false) && 0 < intval($sVarValue)) {
                    $sSessionIdName = str_replace('_session_counter', '', $sVarName) . '_session_id';
                    $accountableSessionIds[$_POST[$sSessionIdName]] = true;
                }
            }

            $accountableSessionIds = array_map('strval', array_keys($accountableSessionIds));

            if (!empty($accountableSessionIds)) {
                $this->enderecoService->closeSessions($accountableSessionIds, $event->getContext());
            }
        }
    }

    private function isPaypalCheckoutRequest(): bool
    {
        $currentRequest = $this->requestStack->getCurrentRequest();

        if (!$currentRequest) {
            return false;
        }

        return str_contains($currentRequest->getPathInfo(), '/store-api/paypal');
    }

    /**
     * Creating new database entry for EnderecoAddressExtension if it's missing
     */
    private function checkEnderecoExtension(EntityLoadedEvent $event): void
    {
        foreach ($event->getEntities() as $entity) {
            if (!$entity instanceof CustomerAddressEntity) {
                continue;
            }

            $enderecoAddress = $entity->getExtension('enderecoAddress');

            if ($enderecoAddress instanceof EnderecoAddressExtensionEntity) {
                continue;
            }

            $this->enderecoAddressExtensionRepository->upsert([[
                'addressId' => $entity->getId()
            ]], $event->getContext());

            $entity->addExtension('enderecoAddress', new EnderecoAddressExtensionEntity());
        }
    }

    private function checkAddress(EntityLoadedEvent $event, string $salesChannelId): void
    {
        $checkAddressEnabled = $this->isCheckAddressEnabled($salesChannelId);
        $paypalCheckEnabled = $this->isCheckPayPalExpressAddressEnabled($salesChannelId);
        foreach ($event->getEntities() as $entity) {
            if (!$entity instanceof CustomerAddressEntity) {
                continue;
            }

            if (in_array($entity->getId(), $this->checkedAddressIds)) {
                continue;
            }

            $enderecoAddress = $entity->getExtension('enderecoAddress');


            if (
                !$enderecoAddress instanceof EnderecoAddressExtensionEntity
                || $enderecoAddress->isSelectedByCustomer()
            ) {
                continue;
            }

            if ($enderecoAddress->hasMinorCorrection() && !$enderecoAddress->isSelectedAutomatically()) {
                $this->useAutomaticAddressCorrection($entity, $enderecoAddress, $event->getContext());
            }

            $shouldCheckAddress = !$enderecoAddress->isAddressChecked()
                && (
                    ($checkAddressEnabled && !$enderecoAddress->isPayPalAddress())
                    || ($paypalCheckEnabled && $enderecoAddress->isPayPalAddress())
                );

            if (!$shouldCheckAddress) {
                continue;
            }

            $this->enderecoService->checkAddress($entity, $event->getContext());
            $this->checkedAddressIds[] = $entity->getId();
        }
    }

    private function checkStreetField(EntityLoadedEvent $event): void
    {
        foreach ($event->getEntities() as $entity) {
            if (!$entity instanceof CustomerAddressEntity) {
                continue;
            }

            if (in_array($entity->getId(), $this->splittedAddressIds)) {
                continue;
            }

            $this->ensureAddressIsSplit($event->getContext(), $entity);
            $this->splittedAddressIds[] = $entity->getId();
        }
    }

    private function overrideStreetWithSplittedData(?DataBag $address, Context $context): void
    {
        if (!$address instanceof RequestDataBag) {
            return;
        }
        $enderecoStreet = $address->get('enderecoStreet');
        $enderecoHousenumber = $address->get('enderecoHousenumber');
        $street = $address->get('street');
        $countryId = $address->get('countryId');
        if (!empty($countryId)) {
            $country = $this->fetchCountry($countryId, $context);
        }
        if (!empty($enderecoStreet) && !empty($enderecoHousenumber) && !empty($country)) {
            $address->set(
                'street',
                $this->enderecoService->buildFullStreet(
                    $enderecoStreet,
                    $enderecoHousenumber,
                    $country->getIso()
                )
            );
            return;
        }
        if (!empty($street) && !empty($country)) {
            list($street, $houseNumber) =
                $this->enderecoService->splitStreet($street, $country->getIso(), $context);

            if ($street) {
                $address->set('enderecoStreet', $street);
                $address->set('enderecoHousenumber', $houseNumber);
                $address->set(
                    'street',
                    $this->enderecoService->buildFullStreet(
                        $street,
                        $houseNumber,
                        $country->getIso()
                    )
                );
            }
        }
    }

    private function useAutomaticAddressCorrection(
        CustomerAddressEntity $address,
        EnderecoAddressExtensionEntity $enderecoAddressExtension,
        Context $context
    ): void {

        $predictions = $enderecoAddressExtension->getAmsPredictions()[0];
        $countryIso = $predictions['countryCode'] ?? null;
        $street = $predictions['streetName'] ?? null;
        $houseNumber = $predictions['buildingNumber'] ?? null;
        $city = $predictions['locality'] ?? null;
        $zipcode = $predictions['postalCode'] ?? null;
        $countryStateShortCode = $predictions['subdivisionCode'] ?? null;

        if (
            is_null($countryIso)
            || is_null($street)
            || is_null($houseNumber)
            || is_null($city)
            || is_null($zipcode)
        ) {
            return;
        }
        $addressId = $address->getId();
        $this->automaticallySelectedAddressIds[] = $addressId;

        $updatePayload = [
            'id' => $addressId,
            'zipcode' => $zipcode,
            'city' => $city,
            'street' => $this->enderecoService->buildFullStreet($street, $houseNumber, $countryIso),
            'extensions' => [
                'enderecoAddress' => [
                    'street' => $street,
                    'houseNumber' => $houseNumber,
                    'amsStatus' => implode(
                        ',',
                        array_merge(
                            EnderecoAddressExtensionEntity::CORRECT_STATUS_CODES,
                            [EnderecoAddressExtensionEntity::AMS_STATUS_SELECTED_AUTOMATICALLY],
                        )
                    )
                ]
            ]
        ];

        if (!is_null($countryStateShortCode)) {
            $countryStateId = $this->countryStateRepository
                ->searchIds(
                    (new Criteria())->addFilter(new EqualsFilter('shortCode', $countryStateShortCode)),
                    $context
                )->firstId();
            if (!is_null($countryStateId)) {
                $updatePayload['countryStateId'] = $countryStateId;
            }
        }

        $this->customerAddressRepository->update([$updatePayload], $context);
    }
}
