<?php

namespace Santosdave\VerteilWrapper\DataTypes;

class OrderCreate
{
    public static function create(array $params = []): array
    {
        $query = self::createQuery($params['query'] ?? []);

        if (!empty($params['payments'])) {
            $query['Payments'] = self::createPayments($params['payments']);
        }

        if (!empty($params['commission'])) {
            $query['Commission'] = $params['commission'];
        }

        if (!empty($params['metadata'])) {
            $query['Metadata'] = self::createMetadata($params['metadata']);
        }

        $result = ['Query' => $query];

        // Only add Party if it has values
        if (!empty($params['party'])) {
            $party = self::createParty($params['party']);
            if (!empty($party)) {
                $result['Party'] = $party;
            }
        }

        return $result;
    }

    protected static function createQuery(array $params): array
    {
        return [
            'OrderItems' => self::createOrderItems($params['orderItems'] ?? []),
            'DataLists' => self::createDataLists($params['dataLists'] ?? []),
            'Passengers' => self::createPassengers($params['passengers'] ?? [])
        ];
    }

    protected static function createOrderItems(array $params): array
    {
        $items = [];

        if (!empty($params['shoppingResponse'])) {
            $items['ShoppingResponse'] = array_filter([
                'Owner' => $params['shoppingResponse']['owner'] ?? '',
                'ResponseID' => [
                    'value' => $params['shoppingResponse']['responseId'] ?? ''
                ],
                'Offers' => [
                    'Offer' => array_map(function ($offer) {
                        return array_filter([
                            'OfferID' => array_filter([
                                'Owner' => $offer['owner'] ?? '',
                                'Channel' => $offer['channel'] ?? 'NDC',
                                'ObjectKey' => $offer['objectKey'] ?? '',
                                'value' => $offer['offerId'] ?? ''
                            ]),
                            'OfferItems' => [
                                'OfferItem' => array_map(function ($item) {
                                    return array_filter([
                                        'OfferItemID' => array_filter([
                                            'Owner' => $item['owner'] ?? '',
                                            'value' => $item['offerId'] ?? ''
                                        ])
                                    ]);
                                }, $offer['offerItems'] ?? [])
                            ]
                        ]);
                    }, $params['shoppingResponse']['offers']['Offer'] ?? [])
                ]
            ]);
        }

        if (!empty($params['offerItem'])) {
            $items['OfferItem'] = array_map(function ($item, $index) use ($params) {
                $isFirstPassenger = $index === 0;

                return array_filter([
                    'OfferItemID' => array_filter([
                        'Owner' => $item['owner'] ?? '',
                        'value' => $item['value'] ?? '',
                        'Channel' => $item['channel'] ?? 'NDC'
                    ]),
                    'OfferItemType' => self::createOfferItemType($item, $isFirstPassenger)
                ]);
            }, $params['offerItem'], array_keys($params['offerItem']));
        }

        return $items;
    }

    protected static function createOfferItemType(array $item, bool $isFirstPassenger): array
    {
        $offerItemType = [];

        // Handle DetailedFlightItem
        if (isset($item['detailedFlightItem'])) {
            $offerItemType['DetailedFlightItem'] = array_map(function ($flight) use ($isFirstPassenger) {
                $data = array_filter([
                    'Price' => self::createPrice($flight['price'] ?? []),
                    'OriginDestination' => self::createOriginDestination($flight['originDestination'] ?? [], $isFirstPassenger),
                    // 'FareDetail' => self::createFareDetail($flight['fareDetail'] ?? [])
                ]);

                if (!empty($flight['refs'])) {
                    $data['refs'] = $flight['refs'];
                }

                return $data;
            }, $item['detailedFlightItem']);
        }

        // Handle SeatItem
        if (isset($item['seatItem'])) {
            $offerItemType['SeatItem'] = array_map(function ($seat) {
                return [
                    'Price' => self::createPrice($seat['price'] ?? []),
                    'SeatAssociation' => array_map(function ($assoc) {
                        return [
                            'SegmentReferences' => [
                                'value' => $assoc['segmentRef'] ?? ''
                            ],
                            'TravelerReference' => $assoc['travelerRef'] ?? ''
                        ];
                    }, $seat['associations'] ?? []),
                    'Location' => self::createSeatLocation($seat['location'] ?? []),
                    'Descriptions' => isset($seat['descriptions']) ? [
                        'Description' => array_map(function ($desc) {
                            return [
                                'Text' => [
                                    'value' => $desc
                                ]
                            ];
                        }, $seat['descriptions'])
                    ] : null
                ];
            }, $item['seatItem']);
        }

        // Handle OtherItem (Ancillaries)
        if (isset($item['otherItem'])) {
            $offerItemType['OtherItem'] = array_map(function ($other) {
                return [
                    'refs' => $other['refs'] ?? [],
                    'Price' => [
                        'SimpleCurrencyPrice' => [
                            'value' => $other['price']['amount'] ?? 0,
                            'Code' => $other['price']['currency'] ?? 'INR'
                        ]
                    ]
                ];
            }, $item['otherItem']);
        }

        return $offerItemType;
    }


    protected static function createPrice(array $price): array
    {
        return array_filter([
            'BaseAmount' => array_filter([
                'value' => $price['baseAmount'] ?? 0,
                'Code' => $price['currency'] ?? 'INR'
            ]),
            'Taxes' => array_filter([
                'Total' => array_filter([
                    'value' => $price['taxAmount'] ?? 0,
                    'Code' => $price['currency'] ?? 'INR'
                ])
            ])
        ]);
    }

    protected static function createOriginDestination(array $ods, bool $isFirstPassenger): array
    {
        return array_map(function ($od) use ($isFirstPassenger) {

            $odData = [
                'Flight' => array_map(function ($flight) use ($isFirstPassenger) {
                    $flightData = array_filter([
                        'Departure' => array_filter([
                            'AirportCode' => ['value' => $flight['departure']['airport'] ?? ''],
                            'Date' => $flight['departure']['date'] ?? '',
                            'Time' => $flight['departure']['time'] ?? null,
                            'Terminal' => isset($flight['departure']['terminal']) ? [
                                'Name' => $flight['departure']['terminal']
                            ] : null
                        ]),
                        'Arrival' => array_filter([
                            'AirportCode' => ['value' => $flight['arrival']['airport'] ?? ''],
                            'Date' => $flight['arrival']['date'] ?? null,
                            'Time' => $flight['arrival']['time'] ?? null,
                            'Terminal' => isset($flight['arrival']['terminal']) ? [
                                'Name' => $flight['arrival']['terminal']
                            ] : null
                        ]),
                        'MarketingCarrier' => array_filter([
                            'AirlineID' => ['value' => $flight['airline'] ?? ''],
                            'FlightNumber' => ['value' => $flight['flightNumber'] ?? '']
                        ])
                    ]);

                    // Only include SegmentKey for first passenger
                    if ($isFirstPassenger && isset($flight['segmentKey'])) {
                        $flightData['SegmentKey'] = $flight['segmentKey'];
                    }

                    // Only include OperatingCarrier if it has values
                    if (!empty($flight['operatingCarrier'])) {
                        $operatingCarrier = array_filter([
                            'AirlineID' => ['value' => $flight['operatingCarrier']['airline'] ?? ''],
                            'FlightNumber' => ['value' => $flight['operatingCarrier']['flightNumber'] ?? '']
                        ]);
                        if (!empty($operatingCarrier)) {
                            $flightData['OperatingCarrier'] = $operatingCarrier;
                        }
                    }

                    // Add ClassOfService without MarketingName if no marketing name data
                    if (isset($flight['classOfService'])) {
                        $classOfService = ['Code' => ['value' => $flight['classOfService']]];
                        if (!empty($flight['marketingName'])) {
                            $classOfService['MarketingName'] = array_filter([
                                'value' => $flight['marketingName']['value'],
                                'CabinDesignator' => $flight['marketingName']['cabinDesignator'] ?? null
                            ]);
                        }

                        if (!empty($flight['classOfServiceRefs'])) {
                            $classOfService['refs'] = $flight['classOfServiceRefs'];
                        }

                        $flightData['ClassOfService'] = array_filter($classOfService);
                    }

                    return array_filter($flightData);
                }, $od['flights'] ?? [])
            ];

            // Add OriginDestinationKey only for first passenger
            if ($isFirstPassenger && isset($od['originDestinationKey'])) {
                $odData['OriginDestinationKey'] = $od['originDestinationKey'];
            }

            return $odData;
        }, $ods);
    }

    protected static function createFareDetail(array $fareDetail): array
    {
        if (empty($fareDetail)) {
            return [];
        }

        $fareComponent = array_filter([
            'FareBasis' => array_filter([
                'FareBasisCode' => [
                    'Code' => $fareDetail['fareBasisCode'] ?? ''
                ],
                'RBD' => $fareDetail['rbd'] ?? null
            ])
        ]);

        // Only include refs if they exist
        if (!empty($fareDetail['refs'])) {
            $fareComponent['refs'] = $fareDetail['refs'];
        }

        // Only include FareRules if they contain data
        if (!empty($fareDetail['fareRules'])) {
            $fareRules = array_filter([
                'Penalty' => $fareDetail['fareRules']['penalty'] ?? null
            ]);
            if (!empty($fareRules)) {
                $fareComponent['FareRules'] = $fareRules;
            }
        }

        return ['FareComponent' => array_filter($fareComponent)];
    }

    protected static function createSeatLocation(array $location): array
    {
        return [
            'Column' => $location['column'] ?? '',
            'Row' => [
                'Number' => [
                    'value' => $location['row'] ?? ''
                ]
            ],
            'Characteristics' => isset($location['characteristics']) ? [
                'Characteristic' => array_map(function ($char) {
                    return [
                        'Code' => $char['code'] ?? '',
                        'Remarks' => isset($char['remarks']) ? [
                            'Remark' => array_map(function ($remark) {
                                return ['value' => $remark];
                            }, $char['remarks'])
                        ] : null
                    ];
                }, $location['characteristics'])
            ] : null
        ];
    }


    protected static function createDataLists(array $params): array
    {
        $dataLists = [];

        // Create FareList

        if (!empty($params['fares'])) {
            $dataLists['FareList'] = [
                'FareGroup' => array_map(function ($fare) {
                    $fareGroup = [
                        'ListKey' => $fare['listKey'],
                        'FareBasisCode' => [
                            'Code' => $fare['code']
                        ],
                        // 'Fare' => isset($fare['fareCode']) ? [
                        //     'FareCode' => [
                        //         'Code' => $fare['fareCode']
                        //     ],
                        //     'FareDetail' => [
                        //         'Remarks' => [
                        //             'Remark' => [
                        //                 ['value' => 'PUBL']
                        //             ]
                        //         ]
                        //     ]
                        // ] : null
                    ];

                    if (isset($fare['refs']) && !empty($fare['refs'])) {
                        $fareGroup['refs'] = is_array($fare['refs']) ? $fare['refs'] : [$fare['refs']];
                    }

                    return $fareGroup;
                }, $params['fares'])
            ];
        }


        // Create ServiceList
        if (isset($params['services'])) {
            $dataLists['ServiceList'] = [
                'Service' => array_map(function ($service) {
                    return [
                        'ServiceID' => [
                            'Owner' => $service['owner'] ?? '',
                            'value' => $service['serviceId'] ?? ''
                        ],
                        'Name' => [
                            'value' => $service['name'] ?? ''
                        ],
                        'Descriptions' => isset($service['descriptions']) ? [
                            'Description' => array_map(function ($desc) {
                                return [
                                    'Text' => [
                                        'value' => $desc
                                    ]
                                ];
                            }, $service['descriptions'])
                        ] : null,
                        'Price' => array_map(function ($price) {
                            return [
                                'Total' => [
                                    'value' => $price['amount'] ?? 0,
                                    'Code' => $price['currency'] ?? 'INR'
                                ]
                            ];
                        }, $service['prices'] ?? []),
                        'PricedInd' => $service['priced'] ?? false
                    ];
                }, $params['services'])
            ];
        }

        return $dataLists;
    }

    protected static function createPassengers(array $passengers): array
    {
        return [
            'Passenger' => array_map(function ($passenger) {
                // Add required information
                $passengerData = [
                    'ObjectKey' => $passenger['objectKey'] ?? '',
                    'Name' => [
                        'Given' => array_map(function ($given) {
                            return ['value' => $given];
                        }, (array)($passenger['name']['given'] ?? [])),
                        'Surname' => [
                            'value' => $passenger['name']['surname'] ?? ''
                        ],
                        'Title' => $passenger['name']['title'] ?? null
                    ]
                ];

                // Add passenger type information
                if (isset($passenger['passengerType'])) {
                    $passengerData['PTC'] = [
                        'value' => $passenger['passengerType'] ?? ''
                    ];
                }
                // Add gender information
                if (isset($passenger['gender'])) {
                    $passengerData['Gender'] = [
                        'value' => $passenger['gender'] ?? ''
                    ];
                }
                // Add age information
                if (isset($passenger['birthDate'])) {
                    $passengerData['Age'] = [
                        'BirthDate' => [
                            'value' => $passenger['birthDate'] ?? ''
                        ],
                    ];
                }

                // Add passenger association for infant  information
                if (isset($passenger['passengerAssociation'])) {
                    $passengerData['PassengerAssociation'] =  $passenger['passengerAssociation'];
                }

                // Add contact information
                if (isset($passenger['contacts'])) {
                    $passengerData['Contacts'] = [
                        'Contact' => [[
                            'PhoneContact' => [
                                'Number' => [[
                                    'CountryCode' => $passenger['contacts']['phone']['countryCode'] ?? '',
                                    'value' => $passenger['contacts']['phone']['number'] ?? ''
                                ]]
                            ],
                            'EmailContact' => [
                                'Address' => [
                                    'value' => $passenger['contacts']['email'] ?? ''
                                ]
                            ],
                            'AddressContact' => [
                                'Street' => [$passenger['contacts']['address']['street'] ?? ''],
                                'PostalCode' => $passenger['contacts']['address']['postalCode'] ?? '',
                                'CityName' => $passenger['contacts']['address']['city'] ?? '',
                                'CountryCode' => [
                                    'value' => $passenger['contacts']['address']['countryCode'] ?? ''
                                ]
                            ]
                        ]]
                    ];
                }

                // Add document information
                if (isset($passenger['document'])) {
                    $passengerData['PassengerIDInfo'] = [
                        'PassengerDocument' => [[
                            'Type' => $passenger['document']['type'] ?? '',
                            'ID' => $passenger['document']['number'] ?? '',
                            'CountryOfIssuance' => $passenger['document']['issuingCountry'] ?? '',
                            'DateOfExpiration' => $passenger['document']['expiryDate'] ?? null,
                            'CountryOfResidence' => $passenger['document']['countryOfResidence'] ?? null,
                            'DateOfIssue' => $passenger['document']['dateOfIssue'] ?? null
                        ]]
                    ];
                }

                // Add frequent flyer information
                if (isset($passenger['frequentFlyer'])) {
                    $passengerData['FQTVs'] = [
                        'TravelerFQTV_Information' => array_map(function ($fqtv) {
                            return [
                                'AirlineID' => [
                                    'value' => $fqtv['airlineCode'] ?? ''
                                ],
                                'Account' => [
                                    'Number' => [
                                        'value' => $fqtv['accountNumber'] ?? ''
                                    ]
                                ],
                                'ProgramID' => $fqtv['programId'] ?? null
                            ];
                        }, (array)$passenger['frequentFlyer'])
                    ];
                }

                return $passengerData;
            }, $passengers)
        ];
    }


    protected static function createParty(array $params): ?array
    {
        if (empty($params)) {
            return null;
        }

        return [
            'Sender' => [
                'CorporateSender' => array_filter([
                    'CorporateCode' => $params['corporateCode'] ?? null,
                    'Name' => $params['name'] ?? null,
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
                ])
            ]
        ];
    }

    protected static function createPayments(array $payments): ?array
    {
        if (empty($payments)) {
            return null;
        }

        return [
            'Payment' => array_map(function ($payment) {
                $paymentData = [
                    'Amount' => [
                        'Code' => $payment['currency'] ?? 'INR',
                        'value' => $payment['amount'] ?? 0
                    ]
                ];

                // Add payment surcharge if present
                if (isset($payment['surcharge']) && $payment['surcharge']['amount'] > 0) {
                    $paymentData['Surcharge'] = [
                        'Code' => $payment['surcharge']['currency'] ?? 'INR',
                        'value' => $payment['surcharge']['amount'] ?? 0
                    ];
                }

                // Add payment method
                $paymentData['Method'] = self::createPaymentMethod($payment);

                return $paymentData;
            }, $payments)
        ];
    }

    protected static function createPaymentMethod(array $payment): array
    {
        $method = [];

        if (isset($payment['card'])) {
            $paymentCard = [
                'CardNumber' => [
                    'value' => $payment['card']['number'] ?? ''
                ],
                'SeriesCode' => isset($payment['card']['cvv']) ? [
                    'value' => $payment['card']['cvv']
                ] : null,
                'CardType' =>  $payment['card']['type'] ?? 'Credit',
                'CardCode' => $payment['card']['brand'] ?? 'VI',
                'EffectiveExpireDate' => [
                    'Expiration' => $payment['card']['expiryDate'] ?? ''
                ],
                'Amount' => [
                    'value' => $payment['amount'] ?? 0,
                    'Code' => $payment['currency'] ?? 'INR'
                ]
            ];

            // Add CardHolderName if present
            if (isset($payment['card']['holderName'])) {
                $paymentCard['CardHolderName'] = [
                    'value' => $payment['card']['holderName'],
                    'refs' => $payment['card']['holderRefs'] ?? ['Payer']
                ];
            }

            // Modified billing address structure
            if (isset($payment['card']['billingAddress'])) {
                $paymentCard['CardHolderBillingAddress'] = [
                    'Street' => [$payment['card']['billingAddress']['street']],
                    'PostalCode' => $payment['card']['billingAddress']['postalCode'] ?? '',
                    'CityName' => $payment['card']['billingAddress']['city'] ?? '',
                    'CountryCode' => [
                        'value' => $payment['card']['billingAddress']['countryCode'] ?? ''
                    ]
                ];
            }

            // Keep SecurePaymentVersion
            $paymentCard['SecurePaymentVersion'] = [
                'PaymentTrxChannelCode' => 'MO'
            ];

            $method['PaymentCard'] = $paymentCard;
        }




        if (isset($payment['cash']) && $payment['cash']) {
            $method['Cash'] = [
                'CashInd' => true
            ];
        }

        if (isset($payment['other'])) {
            $method['Other'] = [
                'Remarks' => [
                    'Remark' => array_map(function ($remark) {
                        return ['value' => $remark];
                    }, (array)$payment['other']['remarks'])
                ]
            ];
        }

        return $method;
    }

    protected static function createMetadata(array $params): ?array
    {
        if (empty($params)) {
            return null;
        }
        $metadata = [];

        // Handle Other metadata
        if (!empty($params['other'])) {
            $metadata['Other'] = [
                'OtherMetadata' => array_map(function ($meta) {
                    $metadataItem = [];

                    // Add CurrencyMetadatas if present
                    if (isset($meta['currencyMetadata'])) {
                        $metadataItem['CurrencyMetadatas'] = [
                            'CurrencyMetadata' => array_map(function ($currency) {
                                return [
                                    'MetadataKey' => $currency['key'],
                                    'Decimals' => $currency['decimals']
                                ];
                            }, $meta['currencyMetadata'])
                        ];
                    }

                    // Modified PriceMetadatas structure
                    if (isset($meta['priceMetadata'])) {
                        $metadataItem['PriceMetadatas'] = [
                            'PriceMetadata' => array_map(function ($price) {
                                return [
                                    'MetadataKey' => $price['key'],
                                    'AugmentationPoint' => [
                                        'AugPoint' => [
                                            [
                                                'any' => [
                                                    'VdcAugPoint' => [
                                                        [
                                                            'Value' => $price['value']
                                                        ]
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ];
                            }, $meta['priceMetadata'])
                        ];
                    }

                    return $metadataItem;
                }, $params['other'])
            ];
        }

        // Handle PassengerMetadata
        if (!empty($params['passengerMetadata'])) {
            $metadata['PassengerMetadata'] = array_map(function ($passenger) {
                return [
                    'AugmentationPoint' => [
                        'AugPoint' => array_map(function ($point) {
                            return [
                                'any' => [
                                    'VdcAugPoint' => [
                                        'Value' => $point['value']
                                    ]
                                ]
                            ];
                        }, $passenger['augmentationPoints'] ?? [])
                    ],
                    'refs' => $passenger['refs'] ?? []
                ];
            }, $params['passengerMetadata']);
        }

        return $metadata;
    }
}
