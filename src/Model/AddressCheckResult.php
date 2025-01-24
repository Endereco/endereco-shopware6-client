<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Model;

/**
 * Class AddressCheckResult
 * Represents the result of an address check operation.
 *
 * This class includes basic information about the address check result, such as predictions, statuses,
 * timestamp, and session ID used for the address check.
 */
class AddressCheckResult
{
    /**
     * @var array<array<string, string>> $predictions An array of predictions from the address check.
     */
    protected $predictions = [];

    /**
     * @var array<string> $statuses An array of status messages from the address check.
     */
    protected $statuses = [];

    /**
     * @var int $timestamp The timestamp when the address check was performed.
     */
    protected $timestamp = 0;

    /**
     * @var string $usedSessionId The session ID used for the address check.
     */
    protected $usedSessionId = '';

    /**
     * @var string A concatenated string of address fields used as fingerprint to detect address changes
     */
    protected $addressSignature = '';

    /**
     * Checks if automatic correction has been applied.
     *
     * @return bool Always returns false. This method is meant to be overridden by subclasses
     *              if automatic correction is supported.
     */
    public function isAutomaticCorrection(): bool
    {
        return false;
    }

    /**
     * Converts the statuses array to a string.
     *
     * @return string A comma-separated string of statuses.
     */
    public function getStatusesAsString(): string
    {
        return implode(',', $this->statuses);
    }

    /**
     * @return string Fingerprint to verify if address validation results are still valid for current address
     */
    public function getAddressSignature(): string
    {
        return $this->addressSignature;
    }

    /**
     * @param string $addressSignature Fingerprint generated from concatenated address fields
     */
    public function setAddressSignature(string $addressSignature): void
    {
        $this->addressSignature = $addressSignature;
    }

    /**
     * Get the session ID used for the address check.
     *
     * @return string The session ID.
     */
    public function getUsedSessionId(): string
    {
        return $this->usedSessionId;
    }

    /**
     * Set the session ID used for the address check.
     *
     * @param string $sessionId The session ID.
     *
     * @return void
     */
    public function setUsedSessionId(string $sessionId): void
    {
        $this->usedSessionId = $sessionId;
    }

    /**
     * Set the statuses.
     *
     * @param array<string> $statuses Array of statuses.
     *
     * @return void
     */
    public function setStatuses(array $statuses): void
    {
        $this->statuses = $statuses;
    }

    /**
     * Set the predictions.
     *
     * @param array<array<string, string>> $predictions Array of predictions.
     *
     * @return void
     */
    public function setPredictions(array $predictions): void
    {
        $this->predictions = $predictions;
    }

    /**
     * Get the predictions.
     *
     * @return array<array<string, string>> $predictions Array of predictions.
     */
    public function getPredictions(): array
    {
        return $this->predictions;
    }

    /**
     * Set the timestamp.
     *
     * @param int $timestamp The timestamp.
     *
     * @return void
     */
    public function setTimestamp(int $timestamp): void
    {
        $this->timestamp = $timestamp;
    }

    /**
     * Creates an AddressCheckResult object from a JSON response.
     *
     * This static method parses a JSON response and constructs an AddressCheckResult object,
     * either a FailedAddressCheckResult or a SuccessfulAddressCheckResult, depending on the result data.
     *
     * @param string $enderecoResponseJSON The JSON response from the Endereco API.
     *
     * @return AddressCheckResult An instance of either FailedAddressCheckResult or SuccessfulAddressCheckResult.
     */
    public static function createFromJSON(string $enderecoResponseJSON): AddressCheckResult
    {
        $resultData = json_decode($enderecoResponseJSON, true);

        if (!array_key_exists('result', $resultData)) {
            return new FailedAddressCheckResult();
        }

        $addressCheckResult = new SuccessfulAddressCheckResult();

        $addressCheckResult->setStatuses($resultData['result']['status']);

        $predictions = [];
        foreach ($resultData['result']['predictions'] as $prediction) {
            $tempAddress = array(
                'countryCode' => $prediction['country'],
                'postalCode' => $prediction['postCode'],
                'locality' => $prediction['cityName'],
                'streetName' => $prediction['street'],
                'buildingNumber' => $prediction['houseNumber']
            );

            if (array_key_exists('additionalInfo', $prediction)) {
                $tempAddress['additionalInfo'] = $prediction['additionalInfo'];
            }

            if (array_key_exists('subdivisionCode', $prediction)) {
                $tempAddress['subdivisionCode'] = $prediction['subdivisionCode'];
            }

            $predictions[] = $tempAddress;
        }

        $addressCheckResult->setPredictions($predictions);
        $addressCheckResult->setTimestamp(time());

        return $addressCheckResult;
    }

    /**
     * Generates a list of statuses indicating which parts of an address were corrected.
     *
     * This method should be overridden in subclasses that support address correction.
     * This base implementation is meant to be overridden in the subclass.
     *
     * @return array<string> An array of status codes.
     */
    public function generateStatusesForCorrection(): array
    {
        return [];
    }

    /**
     * Generates a list of statuses indicating which parts of an address were corrected automatically.
     *
     * This method should be overridden in subclasses that support automatic address correction.
     * This base implementation is meant to be overridden in the subclass.
     *
     * @return array<string> An array of status codes.
     */
    public function generateStatusesForAutomaticCorrection(): array
    {
        return [];
    }
}
