<?php

namespace Santosdave\VerteilWrapper\DataTypes;

class FlightPrice
{
    public static function create(array $params = []): array
    {
        $request = [];

        // DataLists section
        if (!empty($params['dataLists'])) {
            $request['DataLists'] = self::createDataLists($params['dataLists']);
        }

        // Query section
        if (!empty($params['query'])) {
            $request['Query'] = self::createQuery($params['query']);
        }

        // Travelers section
        if (!empty($params['travelers'])) {
            $request['Travelers'] = self::createTravelers($params['travelers']);
        }


        // Add ShoppingResponseID only if required fields are present
        if (isset($params['shoppingResponseId']['owner']) && isset($params['shoppingResponseId']['responseId'])) {
            $request['ShoppingResponseID'] = self::createShoppingResponseId($params['shoppingResponseId']);
        }

        if (isset($params['party'])) {
            $request['Party'] = self::createParty($params['party']);
        }

        if (isset($params['parameters'])) {
            $request['Parameters'] = self::createParameters($params['parameters']);
        }

        if (isset($params['qualifier'])) {
            $request['Qualifier'] = self::createQualifier($params['qualifier']);
        }

        if (isset($params['metadata'])) {
            $request['Metadata'] = self::createMetadata($params['metadata']);
        }

        return $request;
    }

    protected static function createDataLists(array $params): array
    {
        $dataLists = [];

        if (!empty($params['fares'])) {
            $dataLists['FareList'] = [
                'FareGroup' => array_map(function ($fare) {
                    $fareGroup = [
                        'ListKey' => $fare['listKey'],
                        'FareBasisCode' => [
                            'Code' => $fare['code']
                        ],
                        'Fare' => [
                            'FareCode' => [
                                'Code' => $fare['fareCode']
                            ],
                            // 'FareDetail' => [
                            //     'Remarks' => [
                            //         'Remark' => [
                            //             ['value' => 'PVT']
                            //         ]
                            //     ]
                            // ]
                        ],
                    ];

                    if (isset($fare['refs']) && !empty($fare['refs'])) {
                        $fareGroup['refs'] = is_array($fare['refs']) ? $fare['refs'] : [$fare['refs']];
                    }

                    return $fareGroup;
                }, $params['fares'])
            ];
        }

        if (!empty($params['anonymousTravelers'])) {
            $dataLists['AnonymousTravelerList'] = [
                'AnonymousTraveler' => array_map(function ($traveler) {
                    $data = [
                        'ObjectKey' => $traveler['objectKey'],
                        'PTC' => ['value' => $traveler['passengerType']]
                    ];

                    if (isset($traveler['age'])) {
                        $data['Age'] = [
                            'Value' => ['value' => $traveler['age']['value']],
                            'BirthDate' => ['value' => $traveler['age']['birthDate']]
                        ];
                    }

                    return $data;
                }, $params['anonymousTravelers'])
            ];
        }

        if (isset($params['recognizedTravelers'])) {
            $dataLists['RecognizedTravelerList'] = [
                'RecognizedTraveler' => array_map(function ($traveler) {
                    return [
                        'ObjectKey' => $traveler['objectKey'],
                        'PTC' => ['value' => $traveler['passengerType']],
                        'FQTVs' => array_map(function ($fqtv) {
                            return [
                                'AirlineID' => ['value' => $fqtv['airlineCode']],
                                'Account' => [
                                    'Number' => [
                                        ['value' => $fqtv['accountNumber']]
                                    ]
                                ],
                                'ProgramID' => $fqtv['programId'] ?? null
                            ];
                        }, $traveler['frequentFlyer'] ?? []),
                        'Name' => isset($traveler['name']) ? [
                            'Given' => array_map(function ($given) {
                                return ['value' => $given];
                            }, (array)$traveler['name']['given']),
                            'Surname' => ['value' => $traveler['name']['surname']]
                        ] : null
                    ];
                }, $params['recognizedTravelers'])
            ];
        }

        return $dataLists;
    }

    protected static function createQuery(array $params): array
    {
        return [
            'OriginDestination' => array_map(function ($od) {
                return [
                    'Flight' => array_map(function ($flight) {
                        $data = [
                            'SegmentKey' => $flight['segmentKey'],
                            'Departure' => self::createDepartureArrival($flight['departure']),
                            'Arrival' => self::createDepartureArrival($flight['arrival']),
                            'MarketingCarrier' => [
                                'AirlineID' => ['value' => $flight['airlineCode']],
                                'FlightNumber' => ['value' => $flight['flightNumber']]
                            ]
                        ];

                        if (isset($flight['operatingCarrier'])) {
                            $data['OperatingCarrier'] = [
                                'AirlineID' => ['value' => $flight['operatingCarrier']['airlineCode']],
                                'FlightNumber' => ['value' => $flight['operatingCarrier']['flightNumber']]
                            ];
                        }

                        if (isset($flight['classOfService'])) {
                            $data['ClassOfService'] = [
                                'Code' => ['value' => $flight['classOfService']],
                                'refs' => $flight['classOfServiceRefs'] ?? []
                            ];
                        }

                        if (isset($flight['segmentType'])) {
                            $data['SegmentType'] = $flight['segmentType'];
                        }

                        return $data;
                    }, $od['flights'])
                ];
            }, $params['originDestinations'] ?? []),
            'Offers' => [
                'Offer' => array_map(function ($offer) {
                    $data = [
                        'OfferID' => [
                            'Owner' => $offer['owner'],
                            'Channel' => $offer['channel'] ?? 'NDC',
                            'value' => $offer['offerId']
                        ],
                        'OfferItemIDs' => [
                            'OfferItemID' => array_map(function ($item) {
                                $data = ['value' => $item['id']];

                                if (isset($item['refs'])) {
                                    $data['refs'] = $item['refs'];
                                }

                                // if (isset($item['quantity'])) {
                                //     $data['Quantity'] = $item['quantity'];
                                // }

                                if (isset($item['selectedSeats'])) {
                                    $data['SelectedSeat'] = array_map(function ($seat) {
                                        return [
                                            'SeatAssociation' => [
                                                'SegmentReferences' => ['value' => $seat['segmentRefs']],
                                                'TravelerReference' => $seat['travelerRef']
                                            ],
                                            'Location' => [
                                                'Column' => $seat['column'],
                                                'Row' => ['Number' => ['value' => $seat['row']]]
                                            ]
                                        ];
                                    }, $item['selectedSeats']);
                                }

                                return $data;
                            }, $offer['offerItems'])
                        ]
                    ];

                    if (isset($offer['refs'])) {
                        $data['refs'] = array_map(function ($ref) {
                            return ['Ref' => $ref];
                        }, $offer['refs']);
                    }

                    return $data;
                }, $params['offers'] ?? [])
            ]
        ];
    }

    protected static function createDepartureArrival(array $params): array
    {
        $data = [
            'AirportCode' => ['value' => $params['airportCode']],
            'Date' => $params['date']
        ];

        if (isset($params['time'])) {
            $data['Time'] = $params['time'];
        }

        if (isset($params['terminal'])) {
            $data['Terminal'] = ['Name' => $params['terminal']];
        }

        if (isset($params['cityName'])) {
            $data['CityName'] = $params['cityName'];
        }

        if (isset($params['countryName'])) {
            $data['CountryName'] = $params['countryName'];
        }

        if (isset($params['airportName'])) {
            $data['AirportName'] = $params['airportName'];
        }

        return $data;
    }

    protected static function createTravelers(array $params): array
    {
        return [
            'Traveler' => array_map(function ($traveler) {
                if (isset($traveler['frequentFlyer'])) {
                    return [
                        'RecognizedTraveler' => [
                            'ObjectKey' => $traveler['objectKey'],
                            'PTC' => ['value' => $traveler['passengerType']],
                            'FQTVs' => [[
                                'AirlineID' => ['value' => $traveler['frequentFlyer']['airlineCode']],
                                'Account' => ['Number' => ['value' => $traveler['frequentFlyer']['accountNumber']]],
                                'ProgramID' => $traveler['frequentFlyer']['programId'] ?? null
                            ]],
                            'Name' => isset($traveler['name']) ? [
                                'Given' => array_map(function ($given) {
                                    return ['value' => $given];
                                }, (array)$traveler['name']['given']),
                                'Surname' => ['value' => $traveler['name']['surname']]
                            ] : null
                        ]
                    ];
                }

                return [
                    'AnonymousTraveler' => [[
                        'PTC' => ['value' => $traveler['passengerType']],
                        // 'Age' => isset($traveler['age']) ? self::createAge($traveler['age']) : null
                    ]]
                ];
            }, $params)
        ];
    }

    protected static function createAge(array $age): array
    {
        $data = [];

        if (isset($age['value'])) {
            $data['Value'] = ['value' => $age['value']];
        }

        if (isset($age['birthDate'])) {
            $data['BirthDate'] = ['value' => $age['birthDate']];
        }

        return $data;
    }

    protected static function createShoppingResponseId(array $params): array
    {
        return [
            'Owner' => $params['owner'],
            'ResponseID' => ['value' => $params['responseId']]
        ];
    }

    protected static function createParty(array $params): array
    {
        return [
            'Sender' => [
                'CorporateSender' => array_filter([
                    'CorporateCode' => $params['corporateCode'],
                    'Name' => $params['name'] ?? null,
                    'Department' => isset($params['department']) ? [
                        'Name' => $params['department']
                    ] : null,
                    'ContactInfo' => isset($params['contact']) ? [
                        'EmailContact' => [
                            'Address' => ['value' => $params['contact']['email']]
                        ],
                        'PhoneContact' => [
                            'Number' => [
                                'CountryCode' => $params['contact']['phoneCountryCode'],
                                'value' => $params['contact']['phoneNumber']
                            ]
                        ]
                    ] : null
                ])
            ]
        ];
    }

    protected static function createParameters(array $params): array
    {
        return [
            'Pricing' => [
                'OverrideCurrency' => $params['currency']
            ]
        ];
    }

    protected static function createQualifier(array $params): array
    {
        $qualifier = [];

        if (isset($params['programQualifiers'])) {
            $qualifier['ProgramQualifiers'] = [
                'ProgramQualifier' => array_map(function ($prog) {
                    return [
                        'DiscountProgramQualifier' => [
                            'Account' => ['value' => $prog['promoCode']],
                            'AssocCode' => ['value' => $prog['airlineCode']],
                            'Name' => ['value' => 'PROMOCODE']
                        ]
                    ];
                }, $params['programQualifiers'])
            ];
        }

        if (isset($params['paymentCard'])) {
            $qualifier['PaymentCardQualifier'] = array_filter([
                'cardProductTypeCode' => $params['paymentCard']['productType'] ?? 'P',
                'cardBrandCode' => $params['paymentCard']['brandCode'],
                'cardNumber' => $params['paymentCard']['number']
            ]);
        }

        return $qualifier;
    }

    protected static function createMetadata(array $metadata): array
    {
        return [
            'Other' => [
                'OtherMetadata' => array_map(function ($meta) {
                    $metadataItem = [];

                    if (isset($meta['priceMetadata'])) {
                        $metadataItem['PriceMetadatas'] = [
                            'PriceMetadata' => array_map(function ($price) {
                                return [
                                    'MetadataKey' => $price['key'],
                                    'AugmentationPoint' => [
                                        'AugPoint' => [
                                            [
                                                'any' => array_filter([
                                                    '@type' => $price['type'] ?? null,
                                                    'type' => $price['javaType'] ?? null,
                                                    'value' => $price['value']
                                                ])
                                            ]
                                        ]
                                    ]
                                ];
                            }, $meta['priceMetadata'])
                        ];
                    }

                    return $metadataItem;
                }, $metadata)
            ]
        ];
    }
}
