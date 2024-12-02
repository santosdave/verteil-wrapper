<?php

namespace Santosdave\VerteilWrapper\DataTypes;

class VerteilRequestBuilder
{
    public static function createNameType(string $given, string $surname, ?string $title = null): array
    {
        return [
            'given' => (array)($params['given'] ?? ''),
            'surname' => $params['surname'] ?? '',
            'title' => $params['title'] ?? null
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
                'countryCode' => $params['phoneCountryCode'] ?? '1',
                'number' => $params['phoneNumber'] ?? ''
            ],
            'email' => $params['email'] ?? '',
            'address' => [
                'street' => $params['street'] ?? '',
                'city' => $params['city'] ?? '',
                'postalCode' => $params['postalCode'] ?? '',
                'countryCode' => $params['countryCode'] ?? ''
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
            'type' => $params['type'] ?? 'PT',
            'number' => $params['documentNumber'] ?? '',
            'country' => $params['issuingCountry'] ?? '',
            'expiryDate' => $params['expiryDate'] ?? null
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
                'number' => $params['cardNumber'] ?? '',
                'cvv' => $params['cvv'] ?? '',
                'brand' => $params['brand'] ?? 'VI',
                'expiry' => $params['expiryDate'] ?? '',
                'holderName' => $params['holderName'] ?? ''
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
            'departureAirport' => $params['departureAirport'] ?? '',
            'arrivalAirport' => $params['arrivalAirport'] ?? '',
            'departureDate' => $params['departureDate'] ?? '',
            'departureTime' => $params['departureTime'] ?? '',
            'arrivalDate' => $params['arrivalDate'] ?? null,
            'arrivalTime' => $params['arrivalTime'] ?? null,
            'airlineCode' => $params['airlineCode'] ?? '',
            'flightNumber' => $params['flightNumber'] ?? '',
            'classOfService' => $params['classOfService'] ?? null
        ]);
    }

    public static function createPriceType(float $baseAmount, float $taxAmount, string $currency): array
    {
        return [
            'baseAmount' => $params['baseAmount'] ?? 0.0,
            'totalTax' => $params['taxAmount'] ?? 0.0,
            'currency' => $params['currency'] ?? 'USD'
        ];
    }
}
