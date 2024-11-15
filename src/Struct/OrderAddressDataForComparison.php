<?php

namespace Endereco\Shopware6Client\Struct;

final class OrderAddressDataForComparison
{
    private ?string $company;
    private ?string $department;
    private ?string $salutationId;
    private ?string $title;
    private string $firstName;
    private string $lastName;
    private string $street;
    private ?string $zipcode;
    private string $city;
    private ?string $phoneNumber;
    private ?string $additionalAddressLine1;
    private ?string $additionalAddressLine2;
    private string $countryId;
    private ?string $countryStateId;

    /**
     * @param string|null $company
     * @param string|null $department
     * @param string|null $salutationId
     * @param string|null $title
     * @param string $firstName
     * @param string $lastName
     * @param string $street
     * @param string|null $zipcode
     * @param string $city
     * @param string|null $phoneNumber
     * @param string|null $additionalAddressLine1
     * @param string|null $additionalAddressLine2
     * @param string $countryId
     * @param string|null $countryStateId
     */
    public function __construct(
        ?string $company,
        ?string $department,
        ?string $salutationId,
        ?string $title,
        string $firstName,
        string $lastName,
        string $street,
        ?string $zipcode,
        string $city,
        ?string $phoneNumber,
        ?string $additionalAddressLine1,
        ?string $additionalAddressLine2,
        string $countryId,
        ?string $countryStateId
    ) {
        $this->company = $company;
        $this->department = $department;
        $this->salutationId = $salutationId;
        $this->title = $title;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->street = $street;
        $this->zipcode = $zipcode;
        $this->city = $city;
        $this->phoneNumber = $phoneNumber;
        $this->additionalAddressLine1 = $additionalAddressLine1;
        $this->additionalAddressLine2 = $additionalAddressLine2;
        $this->countryId = $countryId;
        $this->countryStateId = $countryStateId;
    }

    /**
     * @param array<string, mixed> $cartToOrderConversionData
     * @return self
     */
    public static function fromCartToOrderConversionData(array $cartToOrderConversionData): self
    {
        if (($cartToOrderConversionData['firstName'] ?? null) === null) {
            throw new \LogicException(
                'The "firstName" should be set, but it is not. The Shopware logic has to be checked.'
            );
        }
        if (($cartToOrderConversionData['lastName'] ?? null) === null) {
            throw new \LogicException(
                'The "lastName" should be set, but it is not. The Shopware logic has to be checked.'
            );
        }
        if (($cartToOrderConversionData['street'] ?? null) === null) {
            throw new \LogicException(
                'The "street" should be set, but it is not. The Shopware logic has to be checked.'
            );
        }
        if (($cartToOrderConversionData['city'] ?? null) === null) {
            throw new \LogicException(
                'The "city" should be set, but it is not. The Shopware logic has to be checked.'
            );
        }
        if (($cartToOrderConversionData['countryId'] ?? null) === null) {
            throw new \LogicException(
                'The "countryId" should be set, but it is not. The Shopware logic has to be checked.'
            );
        }

        return new self(
            $cartToOrderConversionData['company'] ?? null,
            $cartToOrderConversionData['department'] ?? null,
            $cartToOrderConversionData['salutationId'] ?? null,
            $cartToOrderConversionData['title'] ?? null,
            $cartToOrderConversionData['firstName'],
            $cartToOrderConversionData['lastName'],
            $cartToOrderConversionData['street'],
            $cartToOrderConversionData['zipcode'] ?? null,
            $cartToOrderConversionData['city'],
            $cartToOrderConversionData['phoneNumber'] ?? null,
            $cartToOrderConversionData['additionalAddressLine1'] ?? null,
            $cartToOrderConversionData['additionalAddressLine2'] ?? null,
            $cartToOrderConversionData['countryId'],
            $cartToOrderConversionData['countryStateId'] ?? null
        );
    }

    /**
     * @return array{
     *     company: string|null,
     *     department: string|null,
     *     salutationId: string|null,
     *     title: string|null,
     *     firstName: string,
     *     lastName: string,
     *     street: string,
     *     zipcode: string|null,
     *     city: string,
     *     phoneNumber: string|null,
     *     additionalAddressLine1: string|null,
     *     additionalAddressLine2: string|null,
     *     countryId: string,
     *     countryStateId: string|null,
     * }
     */
    public function data(): array
    {
        return [
            'company' => $this->company,
            'department' => $this->department,
            'salutationId' => $this->salutationId,
            'title' => $this->title,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'street' => $this->street,
            'zipcode' => $this->zipcode,
            'city' => $this->city,
            'phoneNumber' => $this->phoneNumber,
            'additionalAddressLine1' => $this->additionalAddressLine1,
            'additionalAddressLine2' => $this->additionalAddressLine2,
            'countryId' => $this->countryId,
            'countryStateId' => $this->countryStateId,
        ];
    }
}
