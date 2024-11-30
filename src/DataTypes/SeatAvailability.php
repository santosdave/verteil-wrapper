<?php

namespace Santosdave\VerteilWrapper\DataTypes;

class SeatAvailability
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
            'DataLists' => self::createDataLists($params['dataLists'] ?? []),
            'Travelers' => self::createTravelers($params['travelers'] ?? []),
            'ShoppingResponseID' => self::createShoppingResponseId($params['shoppingResponseId'] ?? [])
        ];
    }

    protected static function createQuery(array $params): array
    {
        return [
            'OriginDestination' => array_map(function($od) {
                return [
                    'FlightSegmentReference' => array_map(function($ref) {
                        return ['ref' => $ref];
                    }, $od['segmentRefs'])
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
                            'OfferItemID' => array_map(function($item) {
                                return ['value' => $item];
                            }, $offer['offerItems'])
                        ]
                    ];
                }, $params['offers'] ?? [])
            ]
        ];
    }

    protected static function createDataLists(array $params): array
    {
        return [
            'FareList' => [
                'FareGroup' => array_map(function($fare) {
                    return [
                        'ListKey' => $fare['listKey'],
                        'FareBasisCode' => [
                            'Code' => $fare['code']
                        ]
                    ];
                }, $params['fares'] ?? [])
            ],
            'FlightSegmentList' => [
                'FlightSegment' => array_map(function($segment) {
                    return [
                        'SegmentKey' => $segment['segmentKey'],
                        'Departure' => [
                            'AirportCode' => ['value' => $segment['departureAirport']],
                            'Date' => $segment['departureDate'],
                            'Time' => $segment['departureTime']
                        ],
                        'Arrival' => [
                            'AirportCode' => ['value' => $segment['arrivalAirport']],
                            'Date' => $segment['arrivalDate'],
                            'Time' => $segment['arrivalTime']
                        ],
                        'MarketingCarrier' => [
                            'AirlineID' => ['value' => $segment['airlineCode']],
                            'FlightNumber' => ['value' => $segment['flightNumber']]
                        ]
                    ];
                }, $params['segments'] ?? [])
            ]
        ];
    }

    protected static function createTravelers(array $params): array
    {
        return [
            'Traveler' => array_map(function($traveler) {
                return [
                    'AnonymousTraveler' => [[
                        'ObjectKey' => $traveler['objectKey'],
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
}