<?php

namespace Santosdave\VerteilWrapper\DataTypes;

class AirShopping
{
    public static function create(array $params = []): array
    {
        return [
            'CoreQuery' => self::createCoreQuery($params['coreQuery'] ?? []),
            'Travelers' => self::createTravelers($params['travelers'] ?? []),
            'ResponseParameters' => self::createResponseParameters($params['responseParameters'] ?? []),
            'Preference' => self::createPreference($params['preference'] ?? []),
            'EnableGDS' => $params['enableGDS'] ?? null,
            'Qualifier' => self::createQualifier($params['qualifier'] ?? [])
        ];
    }

    public static function createCoreQuery(array $params): array
    {
        return [
            'OriginDestinations' => [
                'OriginDestination' => array_map(function ($od) {
                    return [
                        'Departure' => [
                            'AirportCode' => ['value' => $od['departureAirport']],
                            'Date' => $od['departureDate'],
                            'TimeFrom' => $od['departureTimeFrom'] ?? null,
                            'TimeTo' => $od['departureTimeTo'] ?? null
                        ],
                        'Arrival' => [
                            'AirportCode' => ['value' => $od['arrivalAirport']],
                            'TimeFrom' => $od['arrivalTimeFrom'] ?? null,
                            'TimeTo' => $od['arrivalTimeTo'] ?? null,
                            'Date' => $od['arrivalDate'] ?? null
                        ],
                        'OriginDestinationKey' => $od['key']
                    ];
                }, $params['originDestinations'] ?? [])
            ]
        ];
    }

    public static function createTravelers(array $params): array
    {
        return [
            'Traveler' => array_map(function ($traveler) {
                if (isset($traveler['frequentFlyer'])) {
                    return [
                        'RecognizedTraveler' => [
                            'FQTVs' => [[
                                'AirlineID' => ['value' => $traveler['frequentFlyer']['airlineCode']],
                                'Account' => [
                                    'Number' => ['value' => $traveler['frequentFlyer']['accountNumber']]
                                ],
                                'ProgramID' => $traveler['frequentFlyer']['programId'] ?? null
                            ]],
                            'ObjectKey' => $traveler['objectKey'],
                            'PTC' => ['value' => $traveler['passengerType']],
                            'Name' => self::createName($traveler['name'])
                        ]
                    ];
                }

                return [
                    'AnonymousTraveler' => [[
                        'PTC' => ['value' => $traveler['passengerType']],
                        'Age' => isset($traveler['age']) ? [
                            'Value' => ['value' => $traveler['age']]
                        ] : null
                    ]]
                ];
            }, $params)
        ];
    }

    public static function createResponseParameters(array $params): array
    {
        return [
            'ResultsLimit' => isset($params['limit']) ? ['value' => $params['limit']] : null,
            'SortOrder' => array_map(function ($sort) {
                return [
                    'Order' => $sort['order'],
                    'Parameter' => $sort['parameter']
                ];
            }, $params['sortOrder'] ?? []),
            'ShopResultPreference' => $params['preference'] ?? null
        ];
    }

    public static function createPreference(array $params): array
    {
        return [
            'CabinPreferences' => isset($params['cabin']) ? [
                'CabinType' => [[
                    'Code' => $params['cabin'],
                    'PrefLevel' => isset($params['prefLevel']) ? [
                        'PrefLevelCode' => $params['prefLevel']
                    ] : null
                ]]
            ] : null,
            'FarePreferences' => [
                'Types' => [
                    'Type' => array_map(function ($type) {
                        return ['Code' => $type];
                    }, $params['fareTypes'] ?? ['PUBL'])
                ]
            ]
        ];
    }

    protected static function createName(array $name): array
    {
        return [
            'Given' => array_map(function ($given) {
                return ['value' => $given];
            }, (array)$name['given']),
            'Surname' => ['value' => $name['surname']],
            'Title' => $name['title'] ?? null
        ];
    }

    public static function createQualifier(array $params): ?array
    {
        if (empty($params)) {
            return null;
        }

        return [
            'ProgramQualifiers' => [
                'ProgramQualifier' => array_map(function ($qualifier) {
                    return [
                        'DiscountProgramQualifier' => [
                            'Account' => ['value' => $qualifier['promoCode']],
                            'AssocCode' => ['value' => $qualifier['airlineCode']],
                            'Name' => ['value' => 'PROMOCODE']
                        ]
                    ];
                }, $params['programQualifiers'] ?? [])
            ]
        ];
    }
}
