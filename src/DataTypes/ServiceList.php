<?php

namespace Santosdave\VerteilWrapper\DataTypes;

class ServiceList
{
    public static function create(string $type, array $params = []): array
    {
        if ($type === 'post') {
            return [
                'Query' => [
                    'OrderID' => [
                        'Owner' => $params['owner'],
                        'value' => $params['orderId']
                    ]
                ]
            ];
        }

        return [
            'Query' => self::createQuery($params['query'] ?? []),
            'Travelers' => self::createTravelers($params['travelers'] ?? []),
            'ShoppingResponseID' => self::createShoppingResponseId($params['shoppingResponseId'] ?? []),
            'Party' => self::createParty($params['party'] ?? []),
            'Qualifier' => self::createQualifier($params['qualifier'] ?? [])
        ];
    }

    protected static function createQuery(array $params): array
    {
        return [
            'OriginDestination' => array_map(function($od) {
                return [
                    'Flight' => array_map(function($flight) {
                        return [
                            'SegmentKey' => $flight['segmentKey'],
                            'Departure' => [
                                'AirportCode' => ['value' => $flight['departureAirport']],
                                'Date' => $flight['departureDate']
                            ],
                            'Arrival' => [
                                'AirportCode' => ['value' => $flight['arrivalAirport']]
                            ],
                            'MarketingCarrier' => [
                                'AirlineID' => ['value' => $flight['airlineCode']],
                                'FlightNumber' => ['value' => $flight['flightNumber']]
                            ]
                        ];
                    }, $od['flights'])
                ];
            }, $params['originDestinations'] ?? []),
            'Offers' => [
                'Offer' => array_map(function($offer) {
                    return [
                        'OfferID' => [
                            'Owner' => $offer['owner'],
                            'value' => $offer['offerId']
                        ],
                        'OfferItemIDs' => [
                            'OfferItemID' => ['value' => $offer['offerItem']]
                        ]
                    ];
                }, $params['offers'] ?? [])
            ]
        ];
    }

    protected static function createTravelers(array $params): array
    {
        return [
            'Traveler' => array_map(function($traveler) {
                return [
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

    protected static function createQualifier(array $params): ?array
    {
        if (empty($params)) {
            return null;
        }

        return [
            'ProgramQualifiers' => [
                'ProgramQualifier' => array_map(function($qualifier) {
                    return [
                        'DiscountProgramQualifier' => [
                            'Account' => ['value' => $qualifier['promoCode']],
                            'AssocCode' => ['value' => $qualifier['airlineCode']],
                            'Name' => ['value' => 'PROMOCODE']
                        ]
                    ];
                }, $params['programQualifiers'])
            ]
        ];
    }
}