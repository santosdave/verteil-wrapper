<?php

namespace Santosdave\VerteilWrapper\DataTypes;

class OrderReshop
{
    public static function create(array $params = []): array
    {
        return [
            'Query' => self::createQuery($params),
            'Metadata' => self::createMetadata($params['metadata'] ?? [])
        ];
    }

    protected static function createQuery(array $params): array
    {
        $query = [
            'OrderID' => [
                'Owner' => $params['owner'],
                'value' => $params['orderId'],
                'Channel' => $params['channel'] ?? 'NDC'
            ]
        ];

        if (isset($params['qualifiers'])) {
            $query['Qualifiers'] = self::createQualifiers($params['qualifiers']);
        }

        if (isset($params['segments'])) {
            $query['Segments'] = self::createSegments($params['segments']);
        }

        if (isset($params['passengerRefs'])) {
            $query['PassengerRefs'] = array_map(function($ref) {
                return ['value' => $ref];
            }, $params['passengerRefs']);
        }

        if (isset($params['searchAlternateDates'])) {
            $query['SearchAlternateDates'] = $params['searchAlternateDates'];
        }

        return $query;
    }

    protected static function createQualifiers(array $qualifiers): array
    {
        $formattedQualifiers = [];

        foreach ($qualifiers as $qualifier) {
            switch ($qualifier['type']) {
                case 'CABIN':
                    $formattedQualifiers[] = [
                        'CabinPreference' => [
                            'CabinType' => [
                                'Code' => $qualifier['cabin']
                            ],
                            'PreferenceLevel' => $qualifier['preferenceLevel'] ?? null
                        ]
                    ];
                    break;

                case 'FARE':
                    $formattedQualifiers[] = [
                        'FarePreference' => [
                            'Types' => [
                                'Type' => array_map(function($type) {
                                    return ['Code' => $type];
                                }, $qualifier['fareTypes'] ?? ['PUBL'])
                            ],
                            'FareBasisCode' => isset($qualifier['fareBasis']) ? [
                                'Code' => $qualifier['fareBasis']
                            ] : null
                        ]
                    ];
                    break;

                case 'SERVICE':
                    $formattedQualifiers[] = [
                        'ServicePreference' => [
                            'ServiceType' => [
                                'Code' => $qualifier['serviceCode']
                            ],
                            'ServiceDefinitionID' => $qualifier['serviceDefinitionId'] ?? null
                        ]
                    ];
                    break;
            }
        }

        return $formattedQualifiers;
    }

    protected static function createSegments(array $segments): array
    {
        return array_map(function($segment) {
            $formattedSegment = [
                'SegmentKey' => $segment['segmentKey']
            ];

            if (isset($segment['newFlight'])) {
                $formattedSegment['NewFlight'] = [
                    'Departure' => [
                        'AirportCode' => ['value' => $segment['newFlight']['origin']],
                        'Date' => $segment['newFlight']['departureDate'],
                        'Time' => $segment['newFlight']['departureTime'] ?? null
                    ],
                    'Arrival' => [
                        'AirportCode' => ['value' => $segment['newFlight']['destination']],
                        'Date' => $segment['newFlight']['arrivalDate'] ?? null,
                        'Time' => $segment['newFlight']['arrivalTime'] ?? null
                    ],
                    'MarketingCarrier' => [
                        'AirlineID' => ['value' => $segment['newFlight']['airlineCode']],
                        'FlightNumber' => ['value' => $segment['newFlight']['flightNumber']]
                    ],
                    'OperatingCarrier' => isset($segment['newFlight']['operatingCarrier']) ? [
                        'AirlineID' => ['value' => $segment['newFlight']['operatingCarrier']['code']],
                        'FlightNumber' => ['value' => $segment['newFlight']['operatingCarrier']['flightNumber']]
                    ] : null
                ];
            }

            return $formattedSegment;
        }, $segments);
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