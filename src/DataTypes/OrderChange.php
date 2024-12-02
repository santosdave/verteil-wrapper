<?php

namespace Santosdave\VerteilWrapper\DataTypes;

class OrderChange
{
    public static function create(array $params = []): array
    {
        return [
            'Query' => [
                'OrderID' => self::createOrderId($params['orderId']),
                'Changes' => self::createChanges($params['changes'] ?? [])
            ],
            'Passengers' => self::createPassengers($params['passengers'] ?? []),
            'Payments' => self::createPayments($params['payments'] ?? []),
            'CorrelationID' => $params['correlationId'] ?? null
        ];
    }

    protected static function createOrderId(array $params): array
    {
        return [
            'Owner' => $params['owner'],
            'value' => $params['orderId'],
            'Channel' => $params['channel'] ?? 'NDC'
        ];
    }

    protected static function createChanges(array $changes): array
    {
        return array_map(function ($change) {
            switch ($change['type']) {
                case 'FLIGHT_CHANGE':
                    return self::createFlightChange($change);
                case 'ADD_SERVICE':
                    return self::createServiceChange($change);
                case 'PASSENGER_INFO':
                    return self::createPassengerChange($change);
                case 'SEAT_CHANGE':
                    return self::createSeatChange($change);
                default:
                    return [];
            }
        }, $changes);
    }

    protected static function createFlightChange(array $change): array
    {
        return [
            'ChangeType' => 'FLIGHT_CHANGE',
            'FlightDetails' => array_map(function ($segment) {
                return [
                    'Departure' => [
                        'AirportCode' => ['value' => $segment['origin']],
                        'Date' => $segment['departureDate'],
                        'Time' => $segment['departureTime'] ?? null
                    ],
                    'Arrival' => [
                        'AirportCode' => ['value' => $segment['destination']],
                        'Date' => $segment['arrivalDate'] ?? null,
                        'Time' => $segment['arrivalTime'] ?? null
                    ],
                    'MarketingCarrier' => [
                        'AirlineID' => ['value' => $segment['airlineCode']],
                        'FlightNumber' => ['value' => $segment['flightNumber']]
                    ],
                    'OperatingCarrier' => isset($segment['operatingCarrier']) ? [
                        'AirlineID' => ['value' => $segment['operatingCarrier']['code']],
                        'FlightNumber' => ['value' => $segment['operatingCarrier']['flightNumber']]
                    ] : null
                ];
            }, $change['segments'])
        ];
    }

    protected static function createServiceChange(array $change): array
    {
        return [
            'ChangeType' => 'ADD_SERVICE',
            'Service' => [
                'ServiceID' => ['value' => $change['serviceCode']],
                'PassengerRefs' => array_map(function ($ref) {
                    return ['value' => $ref];
                }, $change['passengerReferences'])
            ]
        ];
    }

    protected static function createPassengerChange(array $change): array
    {
        return [
            'ChangeType' => 'PASSENGER_INFO',
            'PassengerReference' => ['value' => $change['passengerReference']],
            'Updates' => array_map(function ($update) {
                return [
                    'Field' => $update['field'],
                    'Value' => ['value' => $update['value']]
                ];
            }, $change['updates'])
        ];
    }

    protected static function createSeatChange(array $change): array
    {
        return [
            'ChangeType' => 'SEAT_CHANGE',
            'SeatAssignment' => [
                'SegmentRef' => ['value' => $change['segmentReference']],
                'PassengerRef' => ['value' => $change['passengerReference']],
                'SeatNumber' => $change['seatNumber']
            ]
        ];
    }

    protected static function createPassengers(array $passengers): array
    {
        if (empty($passengers)) {
            return [];
        }

        return [
            'Passenger' => array_map(function ($passenger) {
                return [
                    'ObjectKey' => $passenger['reference'],
                    'PTC' => ['value' => $passenger['type']],
                    'PassengerIDInfo' => isset($passenger['document']) ? [
                        'PassengerDocument' => [[
                            'Type' => $passenger['document']['type'],
                            'ID' => $passenger['document']['number'],
                            'CountryOfIssuance' => $passenger['document']['issuingCountry'],
                            'DateOfExpiration' => $passenger['document']['expiryDate']
                        ]]
                    ] : null
                ];
            }, $passengers)
        ];
    }

    protected static function createPayments(array $payments): array
    {
        if (empty($payments)) {
            return [];
        }

        return [
            'Payment' => array_map(function ($payment) {
                return [
                    'Amount' => [
                        'value' => $payment['amount'],
                        'Code' => $payment['currency']
                    ],
                    'Method' => isset($payment['card']) ? [
                        'PaymentCard' => [
                            'CardNumber' => ['value' => $payment['card']['number']],
                            'SeriesCode' => ['value' => $payment['card']['securityCode']],
                            'CardHolderName' => ['value' => $payment['card']['holderName']],
                            'EffectiveExpireDate' => ['value' => $payment['card']['expiryDate']],
                            'CardCode' => $payment['card']['brand'] ?? 'VI'
                        ]
                    ] : null
                ];
            }, $payments)
        ];
    }
}
