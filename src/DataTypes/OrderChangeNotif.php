<?php

namespace Santosdave\VerteilWrapper\DataTypes;

class OrderChangeNotif
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
                'Owner' => $params['orderId']['owner'],
                'value' => $params['orderId']['value'],
                'Channel' => $params['orderId']['channel'] ?? 'NDC'
            ],
            'Notification' => self::createNotification($params['notification'])
        ];

        if (isset($params['serviceImpact'])) {
            $query['ServiceImpact'] = self::createServiceImpact($params['serviceImpact']);
        }

        if (isset($params['alternatives'])) {
            $query['Alternatives'] = self::createAlternatives($params['alternatives']);
        }

        return $query;
    }

    protected static function createNotification(array $notification): array
    {
        $notif = [
            'Type' => $notification['type'],
            'Reason' => $notification['reason'],
            'Severity' => $notification['severity'] ?? 'INFO',
            'Description' => $notification['description'] ?? null,
            'Timestamp' => $notification['timestamp'] ?? date('Y-m-d\TH:i:s\Z')
        ];

        if (isset($notification['affectedSegments'])) {
            $notif['AffectedSegments'] = array_map(function($segment) {
                return [
                    'SegmentRef' => [
                        'value' => $segment['segmentRef']
                    ],
                    'ChangeDetails' => [
                        'Type' => $segment['changeType'] ?? null,
                        'Description' => $segment['description'] ?? null,
                        'OldValue' => $segment['oldValue'] ?? null,
                        'NewValue' => $segment['newValue'] ?? null
                    ],
                    'ImpactIndicators' => isset($segment['impacts']) ? 
                        self::createImpactIndicators($segment['impacts']) : null
                ];
            }, $notification['affectedSegments']);
        }

        if (isset($notification['customerNotification'])) {
            $notif['CustomerNotification'] = [
                'Required' => $notification['customerNotification']['required'] ?? true,
                'Method' => $notification['customerNotification']['method'] ?? null,
                'Template' => $notification['customerNotification']['template'] ?? null,
                'Language' => $notification['customerNotification']['language'] ?? null
            ];
        }

        return $notif;
    }

    protected static function createImpactIndicators(array $impacts): array
    {
        return [
            'Duration' => isset($impacts['duration']) ? [
                'Change' => $impacts['duration']['change'],
                'Unit' => $impacts['duration']['unit'] ?? 'MIN'
            ] : null,
            'Connection' => isset($impacts['connection']) ? [
                'Affected' => $impacts['connection']['affected'],
                'MinimumTime' => $impacts['connection']['minimumTime'] ?? null
            ] : null,
            'Cabin' => isset($impacts['cabin']) ? [
                'DowngradeStatus' => $impacts['cabin']['downgradeStatus'] ?? false,
                'NewCabinCode' => $impacts['cabin']['newCode'] ?? null
            ] : null
        ];
    }

    protected static function createServiceImpact(array $serviceImpact): array
    {
        return array_map(function($impact) {
            return [
                'ServiceDefinitionID' => [
                    'value' => $impact['serviceId']
                ],
                'ServiceType' => $impact['serviceType'],
                'Status' => $impact['status'],
                'Description' => $impact['description'] ?? null,
                'AffectedPassengers' => isset($impact['passengers']) ? 
                    array_map(fn($ref) => ['PassengerReference' => $ref], $impact['passengers']) : null,
                'CompensationDetails' => isset($impact['compensation']) ? [
                    'Type' => $impact['compensation']['type'],
                    'Amount' => [
                        'value' => $impact['compensation']['amount'],
                        'Code' => $impact['compensation']['currency']
                    ],
                    'ValidityPeriod' => isset($impact['compensation']['validity']) ? [
                        'Start' => $impact['compensation']['validity']['start'],
                        'End' => $impact['compensation']['validity']['end']
                    ] : null
                ] : null
            ];
        }, $serviceImpact);
    }

    protected static function createAlternatives(array $alternatives): array
    {
        return array_map(function($alternative) {
            $alt = [
                'Type' => $alternative['type'],
                'Description' => $alternative['description'] ?? null,
                'ValidityPeriod' => isset($alternative['validity']) ? [
                    'Start' => $alternative['validity']['start'],
                    'End' => $alternative['validity']['end']
                ] : null
            ];

            if (isset($alternative['segments'])) {
                $alt['Segments'] = array_map(function($segment) {
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
                        ] : null
                    ];
                }, $alternative['segments']);
            }

            if (isset($alternative['pricing'])) {
                $alt['PricingDetails'] = [
                    'PriceDifference' => [
                        'Amount' => [
                            'value' => $alternative['pricing']['difference'],
                            'Code' => $alternative['pricing']['currency']
                        ]
                    ],
                    'RefundDetails' => isset($alternative['pricing']['refund']) ? [
                        'Amount' => [
                            'value' => $alternative['pricing']['refund']['amount'],
                            'Code' => $alternative['pricing']['refund']['currency']
                        ],
                        'Type' => $alternative['pricing']['refund']['type'] ?? 'Full'
                    ] : null
                ];
            }

            return $alt;
        }, $alternatives);
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
                        'Description' => $meta['description'] ?? null,
                        'Category' => $meta['category'] ?? null
                    ];
                }, $metadata)
            ]
        ];
    }
}