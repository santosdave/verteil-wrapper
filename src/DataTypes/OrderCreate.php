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
        return [
            'ShoppingResponse' => [
                'Owner' => $params['owner'] ?? '',
                'ResponseID' => [
                    'value' => $params['responseId'] ?? ''
                ],
                'Offers' => [
                    'Offer' => array_map(function ($offer) {
                        return [
                            'OfferID' => [
                                'Owner' => $offer['owner'] ?? '',
                                'Channel' => $offer['channel'] ?? 'NDC',
                                'ObjectKey' => $offer['objectKey'] ?? '',
                                'value' => $offer['offerId'] ?? ''
                            ],
                            'OfferItems' => [
                                'OfferItem' => array_map(function ($item) {
                                    return [
                                        'OfferItemID' => [
                                            'Owner' => $item['owner'] ?? '',
                                            'value' => $item['offerId'] ?? ''
                                        ]
                                    ];
                                }, $offer['offerItems'] ?? [])
                            ]
                        ];
                    }, $params['offers'] ?? [])
                ]
            ],
            'OfferItem' => array_map(function ($item) {
                return [
                    'OfferItemID' => [
                        'Owner' => $item['owner'] ?? '',
                        'value' => $item['value'] ?? '',
                        'refs' => $item['refs'] ?? [],
                        'Channel' => $item['channel'] ?? 'NDC'
                    ],
                    'OfferItemType' => self::createOfferItemType($item)
                ];
            }, $params['offerItems'] ?? [])
        ];
    }

    protected static function createOfferItemType(array $item): array
    {
        $offerItemType = [];

        // Handle DetailedFlightItem
        if (isset($item['detailedFlightItem'])) {
            $offerItemType['DetailedFlightItem'] = array_map(function ($flight) {
                return [
                    'Price' => self::createPrice($flight['price'] ?? []),
                    'OriginDestination' => self::createOriginDestination($flight['originDestination'] ?? []),
                    'refs' => $flight['refs'] ?? [],
                    'FareDetail' => self::createFareDetail($flight['fareDetail'] ?? [])
                ];
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
        return [
            'BaseAmount' => [
                'value' => $price['baseAmount'] ?? 0,
                'Code' => $price['currency'] ?? 'INR'
            ],
            'Taxes' => [
                'Total' => [
                    'value' => $price['taxAmount'] ?? 0,
                    'Code' => $price['currency'] ?? 'INR'
                ]
            ]
        ];
    }

    protected static function createOriginDestination(array $ods): array
    {
        return array_map(function ($od) {
            return [
                'Flight' => array_map(function ($flight) {
                    return [
                        'SegmentKey' => $flight['segmentKey'] ?? '',
                        'Departure' => [
                            'AirportCode' => ['value' => $flight['departure']['airport'] ?? ''],
                            'Date' => $flight['departure']['date'] ?? '',
                            'Time' => $flight['departure']['time'] ?? null,
                            'Terminal' => isset($flight['departure']['terminal']) ? [
                                'Name' => $flight['departure']['terminal']
                            ] : null
                        ],
                        'Arrival' => [
                            'AirportCode' => ['value' => $flight['arrival']['airport'] ?? ''],
                            'Date' => $flight['arrival']['date'] ?? null,
                            'Time' => $flight['arrival']['time'] ?? null,
                            'Terminal' => isset($flight['arrival']['terminal']) ? [
                                'Name' => $flight['arrival']['terminal']
                            ] : null
                        ],
                        'MarketingCarrier' => [
                            'AirlineID' => ['value' => $flight['airline'] ?? ''],
                            'FlightNumber' => ['value' => $flight['flightNumber'] ?? '']
                        ],
                        'OperatingCarrier' => isset($flight['operatingCarrier']) ? [
                            'AirlineID' => ['value' => $flight['operatingCarrier']['airline'] ?? ''],
                            'FlightNumber' => ['value' => $flight['operatingCarrier']['flightNumber'] ?? '']
                        ] : null,
                        'Equipment' => isset($flight['aircraft']) ? [
                            'AircraftCode' => ['value' => $flight['aircraft']]
                        ] : null,
                        'ClassOfService' => isset($flight['classOfService']) ? [
                            'Code' => ['value' => $flight['classOfService']],
                            'MarketingName' => isset($flight['marketingName']) ? [
                                'value' => $flight['marketingName']['value'],
                                'CabinDesignator' => $flight['marketingName']['cabinDesignator'] ?? null
                            ] : null
                        ] : null
                    ];
                }, $od['flights'] ?? [])
            ];
        }, $ods);
    }

    protected static function createFareDetail(array $fareDetail): array
    {
        if (empty($fareDetail)) {
            return [];
        }

        return [
            'FareComponent' => [
                'refs' => $fareDetail['refs'] ?? [],
                'FareBasis' => [
                    'FareBasisCode' => [
                        'Code' => $fareDetail['fareBasisCode'] ?? ''
                    ],
                    'RBD' => $fareDetail['rbd'] ?? null
                ],
                'FareRules' => isset($fareDetail['fareRules']) ? [
                    'Penalty' => $fareDetail['fareRules']['penalty'] ?? null
                ] : null
            ]
        ];
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
        if (isset($params['fares'])) {
            $dataLists['FareList'] = [
                'FareGroup' => array_map(function ($fare) {
                    return [
                        'ListKey' => $fare['listKey'] ?? '',
                        'FareBasisCode' => [
                            'Code' => $fare['code'] ?? ''
                        ],
                        'refs' => isset($fare['refs']) ?
                            (is_array($fare['refs']) ? $fare['refs'] : [$fare['refs']]) :
                            [],
                        'Fare' => isset($fare['fareCode']) ? [
                            'FareCode' => [
                                'Code' => $fare['fareCode']
                            ]
                        ] : null
                    ];
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
                $passengerData = [
                    'ObjectKey' => $passenger['objectKey'] ?? '',
                    'PTC' => [
                        'value' => $passenger['passengerType'] ?? ''
                    ],
                    'Gender' => [
                        'value' => $passenger['gender'] ?? ''
                    ],
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
                if (isset($payment['surcharge'])) {
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
        if (isset($payment['card'])) {
            return [
                'PaymentCard' => array_filter([
                    'CardNumber' => [
                        'value' => $payment['card']['number'] ?? ''
                    ],
                    'SeriesCode' => isset($payment['card']['cvv']) ? [
                        'value' => $payment['card']['cvv']
                    ] : null,
                    'CardType' => 'Credit',
                    'CardCode' => $payment['card']['brand'] ?? 'VI',
                    'EffectiveExpireDate' => [
                        'Expiration' => $payment['card']['expiryDate'] ?? ''
                    ],
                    'CardHolderName' => isset($payment['card']['holderName']) ? [
                        'value' => $payment['card']['holderName'],
                        'refs' => $payment['card']['holderRefs'] ?? []
                    ] : null,
                    'CardHolderBillingAddress' => isset($payment['card']['billingAddress']) ? [
                        'Street' => $payment['card']['billingAddress']['street'] ?? '',
                        'PostalCode' => $payment['card']['billingAddress']['postalCode'] ?? '',
                        'CityName' => $payment['card']['billingAddress']['city'] ?? '',
                        'CountryCode' => [
                            'value' => $payment['card']['billingAddress']['countryCode'] ?? ''
                        ],
                        'CountrySubDivisionCode' => $payment['card']['billingAddress']['stateCode'] ?? null,
                        'BuildingRoom' => $payment['card']['billingAddress']['buildingRoom'] ?? null
                    ] : null,
                    'ProductTypeCode' => $payment['card']['productType'] ?? 'P',
                    'SecurePaymentVersion' => [
                        'PaymentTrxChannelCode' => 'MO'
                    ],
                    'Amount' => [
                        'value' => $payment['amount'] ?? 0,
                        'Code' => $payment['currency'] ?? 'INR'
                    ]
                ])
            ];
        }

        if (isset($payment['cash']) && $payment['cash']) {
            return [
                'Cash' => [
                    'CashInd' => true
                ]
            ];
        }

        if (isset($payment['other'])) {
            return [
                'Other' => [
                    'Remarks' => [
                        'Remark' => array_map(function ($remark) {
                            return ['value' => $remark];
                        }, (array)$payment['other']['remarks'])
                    ]
                ]
            ];
        }

        return [];
    }

    protected static function createMetadata(array $params): ?array
    {
        if (empty($params)) {
            return null;
        }

        $metadata = [];

        // Add passenger metadata if present
        if (isset($params['passengerMetadata'])) {
            $metadata['PassengerMetadata'] = array_map(function ($meta) {
                return [
                    'AugmentationPoint' => [
                        'AugPoint' => array_map(function ($point) {
                            return [
                                'any' => [
                                    'VdcAugPoint' => array_map(function ($value) {
                                        return [
                                            'Values' => $value
                                        ];
                                    }, $point['values'] ?? [])
                                ]
                            ];
                        }, $meta['augmentationPoints'] ?? [])
                    ]
                ];
            }, $params['passengerMetadata']);
        }

        // Add other metadata if present
        if (isset($params['other'])) {
            $metadata['Other'] = [
                'OtherMetadata' => array_map(function ($meta) {
                    $metadataItem = [];

                    // Add payment form metadata
                    if (isset($meta['paymentFormMetadata'])) {
                        $metadataItem['PaymentFormMetadatas'] = [
                            'PaymentFormMetadata' => array_map(function ($payment) {
                                return [
                                    'Text' => $payment['text'] ?? '',
                                    'MetadataKey' => $payment['key'] ?? '',
                                ];
                            }, $meta['paymentFormMetadata'])
                        ];
                    }

                    // Add price metadata
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

                    // Add currency metadata with enhanced attributes
                    if (isset($meta['currencyMetadata'])) {
                        $metadataItem['CurrencyMetadatas'] = [
                            'CurrencyMetadata' => array_map(function ($currency) {
                                return array_filter([
                                    'MetadataKey' => $currency['key'] ?? null,
                                    'Decimals' => $currency['decimals'] ?? 0,
                                ]);
                            }, $meta['currencyMetadata'])
                        ];
                    }

                    return $metadataItem;
                }, $params['other'])
            ];
        }

        return $metadata;
    }
}
