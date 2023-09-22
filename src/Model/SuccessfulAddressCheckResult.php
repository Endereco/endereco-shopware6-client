<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Model;

/**
 * Represents a successful address check result.
 *
 * This class extends the AddressCheckResult class and provides methods to determine
 * whether an automatic correction can be applied and to generate status codes based
 * on the data from the address check result.
 */
class SuccessfulAddressCheckResult extends AddressCheckResult
{
    /**
     * Determines if an automatic correction can be made.
     *
     * This method checks if the 'address_minor_correction' status is present in the status array.
     * If it is present, an automatic correction can be applied to the address.
     *
     * @return bool True if an automatic correction can be made, false otherwise.
     */
    public function isAutomaticCorrection(): bool
    {
        // If the array list has the "address_minor_correction" status, it means the correction
        // can be used automatically.
        $hasAddressMinorStatus = in_array('address_minor_correction', $this->statuses);

        return $hasAddressMinorStatus;
    }

    /**
     * Generates status codes based on the correction.
     *
     * This method checks the predictions of the address check result and generates status
     * codes based on the presence of various keys in the first prediction.
     *
     * @return array<string> The array of status codes.
     */
    public function generateStatusesForCorrection(): array
    {
        if (empty($this->getPredictions())) {
            return [];
        }

        $firstPrediction = $this->getPredictions()[0];

        $statuses = ['address_correct', 'A1000'];

        if (array_key_exists('countryCode', $firstPrediction)) {
            $statuses[] = 'country_code_correct';
        }

        if (array_key_exists('subdivisionCode', $firstPrediction)) {
            $statuses[] = 'subdivision_code_correct';
        }

        if (array_key_exists('postalCode', $firstPrediction)) {
            $statuses[] = 'postal_code_correct';
        }

        if (array_key_exists('locality', $firstPrediction)) {
            $statuses[] = 'locality_correct';
        }

        if (array_key_exists('streetName', $firstPrediction) || array_key_exists('streetFull', $firstPrediction)) {
            $statuses[] = 'street_name_correct';
        }

        if (array_key_exists('buildingNumber', $firstPrediction) || array_key_exists('streetFull', $firstPrediction)) {
            $statuses[] = 'building_number_correct';
        }

        if (array_key_exists('additionalInfo', $firstPrediction)) {
            $statuses[] = 'additional_info_correct';
        }

        return $statuses;
    }

    /**
     * Generates status codes for automatic correction.
     *
     * This method calls the generateStatusesForCorrection method to get an array of status codes.
     * It then adds the 'address_selected_automatically' status code to the array.
     *
     * @return array<string> The array of status codes, including 'address_selected_automatically'.
     */
    public function generateStatusesForAutomaticCorrection(): array
    {
        $statuses = $this->generateStatusesForCorrection();
        $statuses[] = 'address_selected_automatically';

        return $statuses;
    }
}
