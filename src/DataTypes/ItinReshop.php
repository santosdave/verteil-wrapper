<?php

namespace Santosdave\VerteilWrapper\DataTypes;

class ItinReshop
{
    public static function create(array $params = []): array
    {
        return [
            'Query' => self::createQuery($params),
            'Party' => self::createParty($params['party'] ?? []),
            'Metadata' => self::createMetadata($params['metadata'] ?? [])
        ];
    }

    protected static function createQuery(array $params): array
    {
        $query = [
            'OrderID' => [
                'Owner' => $params['orderId']['owner'],
                'value' => $params['orderId']['value'],
                'Channel' => $params['orderId']['channel'] ?? 'NDC'
            ],
            'ItineraryChanges' => self::createItineraryChanges($params['itineraryChanges'] ?? [])
        ];

        if (isset($params['pricingQualifiers'])) {
            $query['PricingQualifiers'] = self::createPricingQualifiers($params['pricingQualifiers']);
        }

        return $query;
    }

    protected static function createItineraryChanges(array $changes): array
    {
        return array_map(function($change) {
            switch ($change['type']) {
                case 'SEGMENT_CHANGE':
                    return self::createSegmentChange($change);
                case 'ROUTING_CHANGE':
                    return self::createRoutingChange($change);
                case 'DATE_CHANGE':
                    return self::createDateChange($change);
                default:
                    return [];
            }
        }, $changes);
    }

    protected static function createSegmentChange(array $change): array
    {
        return [
            'Type' => 'SEGMENT_CHANGE',
            'OldSegment' => self::createSegmentDetails($change['oldSegment']),
            'NewSegment' => self::createSegmentDetails($change['newSegment']),
            'RelatedSegments' => isset($change['relatedSegments']) ? 
                array_map(fn($ref) => ['SegmentKey' => $ref], $change['relatedSegments']) : null
        ];
    }

    protected static function createRoutingChange(array $change): array
    {
        return [
            'Type' => 'ROUTING_CHANGE',
            'NewRouting' => array_map(function($segment) {
                return self::createSegmentDetails($segment);
            }, $change['newRouting']),
            'PreserveConnections' => $change['preserveConnections'] ?? true
        ];
    }

    protected static function createDateChange(array $change): array
    {
        return [
            'Type' => 'DATE_CHANGE',
            'SegmentReference' => $change['segmentRef'],
            'NewDepartureDate' => $change['newDate'],
            'NewDepartureTime' => $change['newTime'] ?? null,
            'FlexibleDates' => isset($change['flexibleDates']) ? [
                'Before' => $change['flexibleDates']['before'] ?? 0,
                'After' => $change['flexibleDates']['after'] ?? 0
            ] : null
        ];
    }

    protected static function createSegmentDetails(array $segment): array
    {
        return [
            'Departure' => [
                'AirportCode' => ['value' => $segment['origin']],
                'Date' => $segment['departure']['date'],
                'Time' => $segment['departure']['time'] ?? null,
                'Terminal' => isset($segment['departure']['terminal']) ? [
                    'Name' => $segment['departure']['terminal']
                ] : null
            ],
            'Arrival' => [
                'AirportCode' => ['value' => $segment['destination']],
                'Date' => $segment['arrival']['date'] ?? null,
                'Time' => $segment['arrival']['time'] ?? null,
                'Terminal' => isset($segment['arrival']['terminal']) ? [
                    'Name' => $segment['arrival']['terminal']
                ] : null
            ],
            'MarketingCarrier' => [
                'AirlineID' => ['value' => $segment['airline']],
                'FlightNumber' => ['value' => $segment['flightNumber']]
            ],
            'OperatingCarrier' => isset($segment['operatingCarrier']) ? [
                'AirlineID' => ['value' => $segment['operatingCarrier']['airline']],
                'FlightNumber' => ['value' => $segment['operatingCarrier']['flightNumber']]
            ] : null,
            'Equipment' => isset($segment['aircraft']) ? [
                'AircraftCode' => $segment['aircraft']
            ] : null,
            'CabinType' => isset($segment['cabin']) ? [
                'Code' => $segment['cabin']
            ] : null,
            'ClassOfService' => isset($segment['classOfService']) ? [
                'Code' => $segment['classOfService']
            ] : null
        ];
    }

    protected static function createPricingQualifiers(array $qualifiers): array
    {
        return array_map(function($qualifier) {
            switch ($qualifier['type']) {
                case 'FARE_BASIS':
                    return [
                        'FareBasisCode' => [
                            'Code' => $qualifier['code'],
                            'Application' => $qualifier['application'] ?? 'All',
                            'SegmentRefs' => isset($qualifier['segments']) ? 
                                array_map(fn($ref) => ['value' => $ref], $qualifier['segments']) : null
                        ]
                    ];
                
                case 'CABIN':
                    return [
                        'CabinType' => [
                            'Code' => $qualifier['code'],
                            'Definition' => $qualifier['definition'] ?? null
                        ],
                        'PriorityCode' => $qualifier['priority'] ?? null
                    ];

                case 'BRAND':
                    return [
                        'BrandID' => [
                            'value' => $qualifier['brandId']
                        ],
                        'BrandName' => $qualifier['brandName'] ?? null
                    ];

                case 'LOYALTY':
                    return [
                        'LoyaltyProgram' => [
                            'Alliance' => $qualifier['alliance'] ?? null,
                            'CardNumber' => $qualifier['cardNumber'],
                            'Carrier' => [
                                'AirlineID' => ['value' => $qualifier['airline']]
                            ],
                            'ProgramID' => $qualifier['programId'] ?? null,
                            'Tier' => $qualifier['tier'] ?? null
                        ]
                    ];

                case 'CORPORATE':
                    return [
                        'CorporateContract' => [
                            'Code' => $qualifier['code'],
                            'Name' => $qualifier['name'] ?? null,
                            'CorpID' => $qualifier['corporateId'] ?? null
                        ]
                    ];

                default:
                    return [];
            }
        }, $qualifiers);
    }

    protected static function createParty(array $params): ?array
    {
        if (empty($params)) {
            return null;
        }

        return [
            'Sender' => [
                $params['type'] . 'Sender' => [
                    'Code' => $params['code'],
                    'Name' => $params['name'] ?? null,
                    'IATA' => isset($params['iata']) ? [
                        'value' => $params['iata']
                    ] : null,
                    'Department' => isset($params['department']) ? [
                        'Name' => $params['department']
                    ] : null,
                    'ContactInfo' => isset($params['contact']) ? [
                        'EmailContact' => [
                            'Address' => ['value' => $params['contact']['email']]
                        ],
                        'PhoneContact' => [
                            'Number' => [
                                'CountryCode' => $params['contact']['phoneCountryCode'] ?? '1',
                                'value' => $params['contact']['phoneNumber']
                            ]
                        ]
                    ] : null
                ]
            ]
        ];
    }

    protected static function createMetadata(array $metadata): ?array
    {
        if (empty($metadata)) {
            return null;
        }

        return [
            'Other' => [
                'OtherMetadata' => array_map(function($meta) {
                    return [
                        'MetadataKey' => $meta['key'],
                        'Value' => $meta['value'],
                        'Description' => $meta['description'] ?? null
                    ];
                }, $metadata)
            ]
        ];
    }
}