<?php

namespace Santosdave\VerteilWrapper\DataTypes;

class OrderCreate
{
    public static function create(array $params = []): array
    {
        return [
            'Query' => self::createQuery($params['query'] ?? []),
            'Party' => self::createParty($params['party'] ?? []),
            'Payments' => self::createPayments($params['payments'] ?? []),
            'Commission' => $params['commission'] ?? null,
            'Metadata' => self::createMetadata($params['metadata'] ?? [])
        ];
    }

    public static function createQuery(array $params): array
    {
        return [
            'OrderItems' => [
                'ShoppingResponse' => [
                    'Owner' => $params['owner'],
                    'ResponseID' => ['value' => $params['responseId']],
                    'Offers' => self::createOffers($params['offers'] ?? [])
                ],
                'OfferItem' => array_map(function ($item) {
                    return [
                        'OfferItemID' => [
                            'Owner' => $item['owner'],
                            'value' => $item['offerId']
                        ],
                        'OfferItemType' => self::createOfferItemType($item)
                    ];
                }, $params['offerItems'] ?? [])
            ],
            'DataLists' => self::createDataLists($params['dataLists'] ?? []),
            'Passengers' => self::createPassengers($params['passengers'] ?? [])
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
                    'CorporateCode' => $params['corporateCode'] ?? null,
                    'Name' => $params['corporateName'] ?? null,
                    'Department' => isset($params['department']) ? [
                        'Name' => $params['department']
                    ] : null,
                    'ContactInfo' => isset($params['contact']) ? [
                        'EmailContact' => [
                            'Address' => ['value' => $params['contact']['email']]
                        ],
                        'PhoneContact' => [
                            'Number' => [[
                                'CountryCode' => $params['contact']['phoneCountryCode'],
                                'value' => $params['contact']['phoneNumber']
                            ]]
                        ]
                    ] : null
                ],
                'TravelAgencySender' => isset($params['agency']) ? [
                    'IATA' => [
                        'value' => $params['agency']['iataNumber']
                    ],
                    'AgencyID' => [
                        'value' => $params['agency']['agencyId']
                    ],
                    'Name' => $params['agency']['name'],
                    'PseudoCity' => $params['agency']['pseudoCity'] ?? null
                ] : null
            ]
        ];
    }

    protected static function createOffers(array $offers): array
    {
        return [
            'Offer' => array_map(function ($offer) {
                return [
                    'OfferID' => [
                        'Owner' => $offer['owner'],
                        'Channel' => $offer['channel'] ?? 'NDC',
                        'ObjectKey' => $offer['objectKey'],
                        'value' => $offer['offerId']
                    ],
                    'OfferItems' => [
                        'OfferItem' => array_map(function ($item) {
                            return [
                                'OfferItemID' => [
                                    'Owner' => $item['owner'],
                                    'value' => $item['offerId']
                                ]
                            ];
                        }, $offer['items'])
                    ]
                ];
            }, $offers)
        ];
    }

    protected static function createOfferItemType(array $item): array
    {
        if (isset($item['flight'])) {
            return [
                'DetailedFlightItem' => [[
                    'Price' => self::createPrice($item['flight']['price']),
                    'OriginDestination' => self::createOriginDestination($item['flight']['originDestination']),
                    'refs' => $item['flight']['refs']
                ]]
            ];
        }

        if (isset($item['seat'])) {
            return [
                'SeatItem' => [[
                    'Price' => self::createPrice($item['seat']['price']),
                    'SeatAssociation' => array_map(function ($assoc) {
                        return [
                            'SegmentReferences' => [
                                'value' => $assoc['segmentRef']
                            ],
                            'TravelerReference' => $assoc['travelerRef']
                        ];
                    }, $item['seat']['associations']),
                    'Location' => [
                        'Column' => $item['seat']['column'],
                        'Row' => ['Number' => ['value' => $item['seat']['row']]]
                    ]
                ]]
            ];
        }

        return [];
    }

    protected static function createPrice(array $price): array
    {
        return [
            'BaseAmount' => [
                'value' => $price['baseAmount'],
                'Code' => $price['currency']
            ],
            'Taxes' => [
                'Total' => [
                    'value' => $price['totalTax'],
                    'Code' => $price['currency']
                ]
            ]
        ];
    }

    protected static function createOriginDestination(array $originDestination): array
    {
        return [];
    }

    protected static function createDataLists(array $dataLists): array
    {
        $result = [];

        // Create FareList if provided
        if (isset($dataLists['fares'])) {
            $result['FareList'] = [
                'FareGroup' => array_map(function ($fare) {
                    return [
                        'ListKey' => $fare['listKey'],
                        'FareBasisCode' => [
                            'Code' => $fare['code']
                        ],
                        'Fare' => isset($fare['fareCode']) ? [
                            'FareCode' => ['Code' => $fare['fareCode']]
                        ] : null,
                        'FareRule' => isset($fare['rules']) ? [
                            'Rules' => array_map(function ($rule) {
                                return [
                                    'RuleCode' => $rule['code'],
                                    'Description' => $rule['description']
                                ];
                            }, $fare['rules'])
                        ] : null
                    ];
                }, $dataLists['fares'])
            ];
        }

        // Create FlightSegmentList if provided
        if (isset($dataLists['segments'])) {
            $result['FlightSegmentList'] = [
                'FlightSegment' => array_map(function ($segment) {
                    return [
                        'SegmentKey' => $segment['segmentKey'],
                        'Departure' => [
                            'AirportCode' => ['value' => $segment['departureAirport']],
                            'Date' => $segment['departureDate'],
                            'Time' => $segment['departureTime'],
                            'Terminal' => isset($segment['departureTerminal']) ? [
                                'Name' => $segment['departureTerminal']
                            ] : null
                        ],
                        'Arrival' => [
                            'AirportCode' => ['value' => $segment['arrivalAirport']],
                            'Date' => $segment['arrivalDate'],
                            'Time' => $segment['arrivalTime'],
                            'Terminal' => isset($segment['arrivalTerminal']) ? [
                                'Name' => $segment['arrivalTerminal']
                            ] : null
                        ],
                        'MarketingCarrier' => [
                            'AirlineID' => ['value' => $segment['airlineCode']],
                            'FlightNumber' => ['value' => $segment['flightNumber']]
                        ],
                        'OperatingCarrier' => isset($segment['operatingCarrier']) ? [
                            'AirlineID' => ['value' => $segment['operatingCarrier']['code']],
                            'FlightNumber' => ['value' => $segment['operatingCarrier']['flightNumber']]
                        ] : null,
                        'Equipment' => isset($segment['aircraft']) ? [
                            'AircraftCode' => $segment['aircraft']
                        ] : null,
                        'ClassOfService' => isset($segment['classOfService']) ? [
                            'Code' => ['value' => $segment['classOfService']]
                        ] : null
                    ];
                }, $dataLists['segments'])
            ];
        }

        // Create ServiceList if provided
        if (isset($dataLists['services'])) {
            $result['ServiceList'] = [
                'Service' => array_map(function ($service) {
                    return [
                        'ServiceID' => ['value' => $service['serviceId']],
                        'Name' => $service['name'],
                        'Descriptions' => isset($service['description']) ? [
                            'Description' => [['Text' => $service['description']]]
                        ] : null,
                        'ServiceCode' => ['Code' => $service['code']],
                        'Price' => isset($service['price']) ? [
                            'Total' => [
                                'value' => $service['price']['amount'],
                                'Code' => $service['price']['currency']
                            ]
                        ] : null
                    ];
                }, $dataLists['services'])
            ];
        }

        // Create BaggageAllowanceList if provided
        if (isset($dataLists['baggage'])) {
            $result['BaggageAllowanceList'] = [
                'BaggageAllowance' => array_map(function ($baggage) {
                    return [
                        'BaggageAllowanceID' => ['value' => $baggage['id']],
                        'TypeCode' => $baggage['type'],
                        'WeightAllowance' => isset($baggage['weight']) ? [
                            'MaximumWeight' => [
                                'Value' => $baggage['weight']['value'],
                                'UOM' => $baggage['weight']['unit']
                            ]
                        ] : null,
                        'PieceAllowance' => isset($baggage['pieces']) ? [
                            'TotalQuantity' => $baggage['pieces']
                        ] : null
                    ];
                }, $dataLists['baggage'])
            ];
        }

        return $result;
    }

    protected static function createPassengers(array $passengers): array
    {
        return [
            'Passenger' => array_map(function ($passenger) {
                return [
                    'Contacts' => self::createContacts($passenger['contacts']),
                    'ObjectKey' => $passenger['objectKey'],
                    'Gender' => ['value' => $passenger['gender']],
                    'PTC' => ['value' => $passenger['passengerType']],
                    'Name' => [
                        'Given' => array_map(function ($given) {
                            return ['value' => $given];
                        }, (array)$passenger['name']['given']),
                        'Surname' => ['value' => $passenger['name']['surname']],
                        'Title' => $passenger['name']['title'] ?? null
                    ],
                    'PassengerIDInfo' => isset($passenger['document']) ? [
                        'PassengerDocument' => [[
                            'Type' => $passenger['document']['type'],
                            'ID' => $passenger['document']['number'],
                            'CountryOfIssuance' => $passenger['document']['country'],
                            'DateOfExpiration' => $passenger['document']['expiryDate'] ?? null
                        ]]
                    ] : null
                ];
            }, $passengers)
        ];
    }

    protected static function createContacts(array $contacts): array
    {
        return [
            'Contact' => [[
                'PhoneContact' => [
                    'Number' => [[
                        'CountryCode' => $contacts['phone']['countryCode'],
                        'value' => $contacts['phone']['number']
                    ]]
                ],
                'EmailContact' => [
                    'Address' => ['value' => $contacts['email']]
                ],
                'AddressContact' => [
                    'Street' => [$contacts['address']['street']],
                    'PostalCode' => $contacts['address']['postalCode'],
                    'CityName' => $contacts['address']['city'],
                    'CountryCode' => ['value' => $contacts['address']['countryCode']]
                ]
            ]]
        ];
    }

    protected static function createPayments(array $payments): ?array
    {
        if (empty($payments)) {
            return null;
        }

        return [
            'Payment' => array_map(function ($payment) {
                return [
                    'Amount' => [
                        'Code' => $payment['currency'],
                        'value' => $payment['amount']
                    ],
                    'Method' => self::createPaymentMethod($payment)
                ];
            }, $payments)
        ];
    }

    protected static function createPaymentMethod(array $payment): array
    {
        if ($payment['type'] === 'card') {
            return [
                'PaymentCard' => [
                    'CardNumber' => ['value' => $payment['card']['number']],
                    'SeriesCode' => ['value' => $payment['card']['cvv']],
                    'CardType' => 'Credit',
                    'CardCode' => $payment['card']['brand'],
                    'EffectiveExpireDate' => [
                        'Expiration' => $payment['card']['expiry']
                    ],
                    'CardHolderName' => ['value' => $payment['card']['holderName']]
                ]
            ];
        }

        if ($payment['type'] === 'cash') {
            return [
                'Cash' => [
                    'CashInd' => true
                ]
            ];
        }

        return [];
    }

    protected static function createMetadata(array $metadata): ?array
    {
        if (empty($metadata)) {
            return null;
        }

        return [
            'Other' => [
                'OtherMetadata' => array_map(function ($meta) {
                    return [
                        'PriceMetadatas' => [
                            'PriceMetadata' => [[
                                'AugmentationPoint' => $meta['augmentationPoint'],
                                'MetadataKey' => $meta['key']
                            ]]
                        ]
                    ];
                }, $metadata)
            ]
        ];
    }
}
