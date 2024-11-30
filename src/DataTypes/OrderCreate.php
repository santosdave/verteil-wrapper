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

    public static function createParty(array $params): array
    {
        return [];
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
        return [];
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