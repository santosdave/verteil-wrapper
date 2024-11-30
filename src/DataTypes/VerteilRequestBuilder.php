<?php

namespace Santosdave\VerteilWrapper\DataTypes;

class VerteilRequestBuilder
{
    public static function createNameType(string $given, string $surname, ?string $title = null): array
    {
        return [
            'given' => (array)$given,
            'surname' => $surname,
            'title' => $title
        ];
    }

    public static function createContactType(
        string $phoneNumber,
        string $email,
        string $street,
        string $city,
        string $postalCode,
        string $countryCode,
        string $phoneCountryCode = '1'
    ): array {
        return [
            'phone' => [
                'countryCode' => $phoneCountryCode,
                'number' => $phoneNumber
            ],
            'email' => $email,
            'address' => [
                'street' => $street,
                'city' => $city,
                'postalCode' => $postalCode,
                'countryCode' => $countryCode
            ]
        ];
    }

    public static function createPassengerDocumentType(
        string $documentNumber,
        string $issuingCountry,
        string $type = 'PT',
        ?string $expiryDate = null
    ): array {
        return array_filter([
            'type' => $type,
            'number' => $documentNumber,
            'country' => $issuingCountry,
            'expiryDate' => $expiryDate
        ]);
    }

    public static function createPaymentCardType(
        string $cardNumber,
        string $cvv,
        string $expiryDate,
        string $holderName,
        string $brand = 'VI'
    ): array {
        return [
            'type' => 'card',
            'card' => [
                'number' => $cardNumber,
                'cvv' => $cvv,
                'brand' => $brand,
                'expiry' => $expiryDate,
                'holderName' => $holderName
            ]
        ];
    }

    public static function createFlightType(
        string $departureAirport,
        string $arrivalAirport,
        string $departureDate,
        string $departureTime,
        string $airlineCode,
        string $flightNumber,
        ?string $arrivalDate = null,
        ?string $arrivalTime = null,
        ?string $classOfService = null
    ): array {
        return array_filter([
            'departureAirport' => $departureAirport,
            'arrivalAirport' => $arrivalAirport,
            'departureDate' => $departureDate,
            'departureTime' => $departureTime,
            'arrivalDate' => $arrivalDate,
            'arrivalTime' => $arrivalTime,
            'airlineCode' => $airlineCode,
            'flightNumber' => $flightNumber,
            'classOfService' => $classOfService
        ]);
    }

    public static function createPriceType(float $baseAmount, float $taxAmount, string $currency): array
    {
        return [
            'baseAmount' => $baseAmount,
            'totalTax' => $taxAmount,
            'currency' => $currency
        ];
    }
}
