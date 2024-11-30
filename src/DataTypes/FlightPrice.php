<?php

namespace Santosdave\VerteilWrapper\DataTypes;

class FlightPrice
{
    public static function create(array $params = []): array
    {
        return [
            'DataLists' => self::createDataLists($params['dataLists'] ?? []),
            'Query' => self::createQuery($params['query'] ?? []),
            'Travelers' => self::createTravelers($params['travelers'] ?? []),
            'ShoppingResponseID' => self::createShoppingResponseId($params['shoppingResponseId'] ?? []),
            'Party' => self::createParty($params['party'] ?? []),
            'Parameters' => self::createParameters($params['parameters'] ?? [])
        ];
    }

    public static function createDataLists(array $params): array
    {
        return [
            'FareList' => [
                'FareGroup' => array_map(function ($fare) {
                    return [
                        'ListKey' => $fare['listKey'],
                        'FareBasisCode' => [
                            'Code' => $fare['code']
                        ],
                        'refs' => $fare['refs'] ?? [],
                        'Fare' => isset($fare['fareCode']) ? [
                            'FareCode' => ['Code' => $fare['fareCode']]
                        ] : null
                    ];
                }, $params['fares'] ?? [])
            ],
            'AnonymousTravelerList' => isset($params['anonymousTravelers']) ? [
                'AnonymousTraveler' => array_map(function ($traveler) {
                    return [
                        'ObjectKey' => $traveler['objectKey'],
                        'PTC' => ['value' => $traveler['passengerType']],
                        'Age' => isset($traveler['age']) ? [
                            'Value' => ['value' => $traveler['age']]
                        ] : null
                    ];
                }, $params['anonymousTravelers'])
            ] : null
        ];
    }

    public static function createQuery(array $params): array
    {
        return [
            'OriginDestination' => array_map(function ($od) {
                return [
                    'Flight' => array_map(function ($flight) {
                        return [
                            'SegmentKey' => $flight['segmentKey'],
                            'Departure' => [
                                'AirportCode' => ['value' => $flight['departureAirport']],
                                'Date' => $flight['departureDate'],
                                'Time' => $flight['departureTime']
                            ],
                            'Arrival' => [
                                'AirportCode' => ['value' => $flight['arrivalAirport']],
                                'Date' => $flight['arrivalDate'],
                                'Time' => $flight['arrivalTime']
                            ],
                            'MarketingCarrier' => [
                                'AirlineID' => ['value' => $flight['airlineCode']],
                                'FlightNumber' => ['value' => $flight['flightNumber']]
                            ],
                            'ClassOfService' => $flight['classOfService'] ? [
                                'Code' => ['value' => $flight['classOfService']]
                            ] : null
                        ];
                    }, $od['flights'])
                ];
            }, $params['originDestinations'] ?? []),
            'Offers' => [
                'Offer' => array_map(function ($offer) {
                    return [
                        'OfferID' => [
                            'Owner' => $offer['owner'],
                            'Channel' => $offer['channel'] ?? 'NDC',
                            'value' => $offer['offerId']
                        ],
                        'OfferItemIDs' => [
                            'OfferItemID' => array_map(function ($item) {
                                return ['value' => $item];
                            }, $offer['offerItems'])
                        ]
                    ];
                }, $params['offers'] ?? [])
            ]
        ];
    }

    protected static function createTravelers(array $params): array
    {
        return [
            'Traveler' => array_map(function ($traveler) {
                return isset($traveler['frequentFlyer']) ? [
                    'RecognizedTraveler' => [
                        'ObjectKey' => $traveler['objectKey'],
                        'PTC' => ['value' => $traveler['passengerType']],
                        'FQTVs' => [[
                            'AirlineID' => ['value' => $traveler['frequentFlyer']['airlineCode']],
                            'Account' => ['Number' => ['value' => $traveler['frequentFlyer']['accountNumber']]]
                        ]]
                    ]
                ] : [
                    'AnonymousTraveler' => [[
                        'PTC' => ['value' => $traveler['passengerType']]
                    ]]
                ];
            }, $params)
        ];
    }

    protected static function createShoppingResponseId(array $params): array
    {
        return [
            'Owner' => $params['owner'],
            'ResponseID' => ['value' => $params['responseId']]
        ];
    }

    protected static function createParty(array $params): ?array
    {
        if (empty($params)) {
            return null;
        }

        return [
            'Sender' => [
                'CorporateSender' => [
                    'CorporateCode' => $params['corporateCode']
                ]
            ]
        ];
    }

    protected static function createParameters(array $params): ?array
    {
        if (empty($params)) {
            return null;
        }

        return [
            'Pricing' => [
                'OverrideCurrency' => $params['currency'] ?? null
            ]
        ];
    }
}
