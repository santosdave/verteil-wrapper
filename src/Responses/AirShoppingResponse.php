<?php

namespace Santosdave\VerteilWrapper\Responses;

use Illuminate\Support\Collection;
use Carbon\Carbon;

class AirShoppingResponse extends BaseResponse
{

    /**
     * Currency decimal places mapping
     * Default decimals if not specified in metadata
     */
    protected array $currencyDecimals = [
        'USD' => 2,
        'EUR' => 2,
        'GBP' => 2,
        'CHF' => 2,
        'INR' => 0,
        'JPY' => 0,
        'AED' => 0,
    ];

    /**
     * Media references mapping
     */
    protected array $mediaReferences = [];


    public function __construct(array $data)
    {
        parent::__construct($data);
        $this->initializeCurrencyDecimals();
        $this->buildMediaReferences();
    }

    public function toArray(): array
    {
        $data = $this->data ?? null;
        if (!$data) {
            return [];
        }

        return [
            'document' => $this->getDocumentInfo(),
            'success' => $this->isSuccessful(),
            'offers' => $this->getOffers(),
            'data_lists' => [
                'flights' => $this->getFlightList(),
                'flight_segments' => $this->getFlightSegments(),
                'origin_destinations' => $this->getOriginDestinations(),
                'anonymous_travelers' => $this->getAnonymousTravelers(),
                'disclosures' => $this->getDisclosures(),
                'price_classes' => $this->getPriceClasses(),
                'fares' => $this->getFares(),
                'penalties' => $this->getPenalties(),
                'baggage_allowance' => $this->getBaggageAllowance()
            ],
            'metadata' => [
                'shopping' => $this->getShoppingMetadata(),
                'currency' => $this->extractCurrencyMetadata(),
                'other' => $this->data['Metadata']['Other'] ?? []
            ],
            'statistics' => $this->getResponseStats(),
            'warnings' => $this->getWarnings(),
            'errors' => $this->getDetailedErrors(),
            'response_id' => $this->getResponseId(),
            'response_timestamp' => $this->getResponseTimestamp(),
            'trip_duration' => $this->getTotalTripDuration()

        ];
    }

    /**
     * Required sections in a valid response
     */
    protected const REQUIRED_SECTIONS = [
        'OffersGroup',
        'DataLists',
        'Metadata'
    ];


    /**
     * Get document information
     *
     * @return array{referenceVersion: ?string, name: ?string}
     */
    public function getDocumentInfo(): array
    {
        return [
            'referenceVersion' => $this->safeArrayAccess($this->data['Document'] ?? [], 'ReferenceVersion'),
            'name' => $this->safeArrayAccess($this->data['Document'] ?? [], 'Name')
        ];
    }


    /**
     * Get all offers from the response
     *
     * @return array
     */
    public function getOffers(): array
    {
        $offers = [];
        $airlineOffers = $this->data['OffersGroup']['AirlineOffers'] ?? [];

        foreach ($airlineOffers as $airlineOffer) {
            $owner = $airlineOffer['Owner']['value'] ?? null;
            foreach ($airlineOffer['AirlineOffer'] ?? [] as $offer) {
                $offers[] = $this->formatOffer($offer, $owner);
            }
        }

        return $offers;
    }

    /**
     * Get flight segments from the response
     *
     * @return array
     */
    public function getFlightSegments(): array
    {
        $segments = $this->data['DataLists']['FlightSegmentList']['FlightSegment'] ?? [];
        return array_map([$this, 'formatFlightSegment'], $segments);
    }

    /**
     * Get baggage allowance information
     *
     * @return array
     */
    public function getBaggageAllowance(): array
    {
        $checkedBaggage = $this->getCheckedBaggageAllowance();
        $carryOnBaggage = $this->getCarryOnBaggageAllowance();

        return [
            'checked' => $checkedBaggage,
            'carryOn' => $carryOnBaggage
        ];
    }

    /**
     * Get pricing class information
     *
     * @return array
     */
    public function getPriceClasses(): array
    {
        $priceClasses = $this->data['DataLists']['PriceClassList']['PriceClass'] ?? [];
        return array_map([$this, 'formatPriceClass'], $priceClasses);
    }

    /**
     * Get fare information
     *
     * @return array
     */
    public function getFares(): array
    {
        $fares = $this->data['DataLists']['FareList']['FareGroup'] ?? [];
        return array_map([$this, 'formatFare'], $fares);
    }

    /**
     * Get response metadata
     *
     * @return array
     */
    public function getMetadata(): array
    {
        return [
            'shopping' => $this->data['Metadata']['Shopping'] ?? [],
            'currency' => $this->extractCurrencyMetadata(),
            'other' => $this->data['Metadata']['Other'] ?? []
        ];
    }

    /**
     * Get response error information if any
     *
     * @return array
     */
    public function getErrors(): array
    {
        return array_map(function ($error) {
            return [
                'code' => $error['Code'] ?? null,
                'type' => $error['Type'] ?? null,
                'message' => $error['value'] ?? null,
                'shortText' => $error['ShortText'] ?? null,
                'owner' => $error['Owner'] ?? null,
                'reason' => $error['Reason'] ?? null
            ];
        }, $this->data['Errors']['Error'] ?? []);
    }

    /**
     * Calculate total trip duration across all segments
     *
     * @return array{hours: int, minutes: int, total_minutes: int}|null
     */
    public function getTotalTripDuration(): ?array
    {
        $segments = $this->getFlightSegments();
        if (empty($segments)) {
            return null;
        }

        $totalMinutes = 0;
        foreach ($segments as $segment) {
            $duration = $segment['duration'] ?? null;
            if ($duration) {
                $totalMinutes += $duration['total_minutes'];
            }
        }

        return [
            'hours' => (int) floor($totalMinutes / 60),
            'minutes' => $totalMinutes % 60,
            'total_minutes' => $totalMinutes
        ];
    }


    /**
     * Get offers filtered by specific criteria
     *
     * @param array $criteria
     * @return array
     */

    /**
     * Get response statistics
     *
     * @return array
     */
    public function getResponseStats(): array
    {
        $offers = $this->getOffers();
        $segments = $this->getFlightSegments();

        $prices = array_map(function ($offer) {
            return $offer['totalPrice']['amount'] ?? 0;
        }, $offers);

        return [
            'total_offers' => count($offers),
            'total_segments' => count($segments),
            'unique_airlines' => count(array_unique(array_column($offers, 'owner'))),
            'price_range' => [
                'min' => $prices ? min($prices) : 0,
                'max' => $prices ? max($prices) : 0,
                'avg' => $prices ? array_sum($prices) / count($prices) : 0
            ],
            'response_timestamp' => $this->getResponseTimestamp()
        ];
    }



    /**
     * Get warnings from the response
     *
     * @return array
     */
    public function getWarnings(): array
    {
        return array_map(function ($warning) {
            return [
                'message' => $warning['value'] ?? null,
                'owner' => $warning['Owner'] ?? null
            ];
        }, $this->data['Warnings']['Warning'] ?? []);
    }

    /**
     * Get response ID
     *
     * @return string|null
     */
    public function getResponseId(): ?string
    {
        return $this->data['ShoppingResponseID']['ResponseID']['value'] ?? null;
    }

    /**
     * Check if the response is successful
     *
     * @return bool
     */
    public function isSuccessful(): bool
    {
        return isset($this->data['Success']) ||
            (!empty($this->getOffers()) && empty($this->getErrors()));
    }

    /**
     * Enhanced error handling with detailed error information
     *
     * @return array
     */
    public function getDetailedErrors(): array
    {
        $errors = $this->getErrors();
        return array_map(function ($error) {
            return [
                'code' => $error['code'],
                'type' => $error['type'],
                'message' => $error['message'],
                'severity' => $this->determineErrorSeverity($error),
                'suggestion' => $this->getErrorSuggestion($error),
                'owner' => $error['owner'],
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }, $errors);
    }

    /**
     * Get origin-destination information
     *
     * @return array
     */
    public function getOriginDestinations(): array
    {
        $odList = $this->data['DataLists']['OriginDestinationList']['OriginDestination'] ?? [];
        return array_map([$this, 'formatOriginDestination'], $odList);
    }


    /**
     * Get anonymous traveler information
     *
     * @return array
     */
    public function getAnonymousTravelers(): array
    {
        $travelers = $this->data['DataLists']['AnonymousTravelerList']['AnonymousTraveler'] ?? [];
        return array_map(function ($traveler) {
            return [
                'objectKey' => $traveler['ObjectKey'] ?? null,
                'ptc' => $traveler['PTC']['value'] ?? null,
                'age' => [
                    'value' => $traveler['Age']['Value']['value'] ?? null,
                    'birthDate' => $this->formatDateTime($traveler['Age']['BirthDate']['value'] ?? null)
                ]
            ];
        }, $travelers);
    }

    /**
     * Get disclosure information
     *
     * @return array
     */
    public function getDisclosures(): array
    {
        $disclosures = $this->data['DataLists']['DisclosureList']['Disclosures'] ?? [];
        return array_map(function ($disclosure) {
            return [
                'listKey' => $disclosure['ListKey'] ?? null,
                'descriptions' => array_map(function ($desc) {
                    return $desc['Text']['value'] ?? null;
                }, $disclosure['Description'] ?? [])
            ];
        }, $disclosures);
    }


    /**
     * Get penalty information
     *
     * @return array
     */
    public function getPenalties(): array
    {
        $penalties = $this->data['DataLists']['PenaltyList']['Penalty'] ?? [];
        return array_map(function ($penalty) {
            return [
                'objectKey' => $penalty['ObjectKey'] ?? null,
                'details' => array_map(function ($detail) {
                    return [
                        'type' => $detail['Type'] ?? null,
                        'application' => [
                            'code' => $detail['Application']['Code'] ?? null
                        ],
                        'amounts' => $this->formatPenaltyAmounts($detail['Amounts'] ?? [])
                    ];
                }, $penalty['Details']['Detail'] ?? []),
                'changeFeeInd' => $penalty['ChangeFeeInd'] ?? false,
                'changeAllowedInd' => $penalty['ChangeAllowedInd'] ?? false,
                'refundableInd' => $penalty['RefundableInd'] ?? false
            ];
        }, $penalties);
    }

    /**
     * Determine error severity
     *
     * @param array $error
     * @return string
     */
    protected function determineErrorSeverity(array $error): string
    {
        $criticalCodes = ['710', 'INTERNAL_ERROR'];
        $warningCodes = ['325', 'VALIDATION_FAILURE'];

        if (in_array($error['code'], $criticalCodes)) {
            return 'critical';
        }
        if (in_array($error['code'], $warningCodes)) {
            return 'warning';
        }
        return 'info';
    }

    /**
     * Get suggestion for error resolution
     *
     * @param array $error
     * @return string
     */
    protected function getErrorSuggestion(array $error): string
    {
        $suggestions = [
            '710' => 'Check fare availability and search criteria',
            '325' => 'Verify flight availability for the selected route',
            'VALIDATION_FAILURE' => 'Review and correct input parameters',
            'INTERNAL_ERROR' => 'Retry request or contact support',
        ];

        return $suggestions[$error['code']] ?? 'Contact support for assistance';
    }


    /**
     * Get flight list information
     *
     * @return array
     */
    public function getFlightList(): array
    {
        $flights = $this->data['DataLists']['FlightList']['Flight'] ?? [];
        return array_map(function ($flight) {
            return [
                'flightKey' => $flight['FlightKey'] ?? null,
                'journey' => [
                    'time' => $this->parseDuration($flight['Journey']['Time'] ?? null),
                    'distance' => [
                        'value' => $flight['Journey']['Distance']['Value'] ?? null,
                        'unit' => $flight['Journey']['Distance']['UOM'] ?? null
                    ]
                ],
                'segmentReferences' => [
                    'values' => $flight['SegmentReferences']['value'] ?? [],
                    'onPoint' => $flight['SegmentReferences']['OnPoint'] ?? null,
                    'offPoint' => $flight['SegmentReferences']['OffPoint'] ?? null
                ]
            ];
        }, $flights);
    }

    /**
     * Format shopping metadata
     *
     * @return array
     */
    public function getShoppingMetadata(): array
    {
        $metadata = $this->data['Metadata']['Shopping']['ShopMetadataGroup']['Offer'] ?? [];
        return [
            'offerMetadata' => array_map(function ($meta) {
                return [
                    'metadataKey' => $meta['OfferMetadatas']['OfferMetadata'][0]['MetadataKey'] ?? null,
                    'augmentationPoints' => $this->formatAugmentationPoints(
                        $meta['OfferMetadatas']['OfferMetadata'][0]['AugmentationPoint']['AugPoint'] ?? []
                    )
                ];
            }, $metadata['disclosureMetadatasOrOfferMetadatasOrOfferInstructionMetadatas'] ?? [])
        ];
    }


    /**
     * Format penalty amounts
     *
     * @param array $amounts
     * @return array
     */
    protected function formatPenaltyAmounts(array $amounts): array
    {
        return array_map(function ($amount) {
            return [
                'amount' => [
                    'value' => (float) ($amount['CurrencyAmountValue']['value'] ?? 0),
                    'currency' => $amount['CurrencyAmountValue']['Code'] ?? null
                ],
                'application' => $amount['AmountApplication'] ?? null,
                'remarks' => array_map(function ($remark) {
                    return $remark['value'] ?? null;
                }, $amount['ApplicableFeeRemarks']['Remark'] ?? [])
            ];
        }, $amounts['Amount'] ?? []);
    }

    /**
     * Get response timestamp
     *
     * @return string|null
     */
    protected function getResponseTimestamp(): ?string
    {
        return $this->safeArrayAccess($this->data['Metadata'], 'Timestamp');
    }

    /**
     * Format augmentation points
     *
     * @param array $augPoints
     * @return array
     */
    protected function formatAugmentationPoints(array $augPoints): array
    {
        $formatted = [];
        foreach ($augPoints as $point) {
            if (isset($point['any']['VdcAugPoint'])) {
                foreach ($point['any']['VdcAugPoint'] as $vdcPoint) {
                    $formatted[$point['Key']][] = [
                        'key' => $vdcPoint['Key'] ?? null,
                        'values' => $vdcPoint['Values'] ?? []
                    ];
                }
            }
        }
        return $formatted;
    }

    /**
     * Extract fare type from fare data
     *
     * @param array $fare
     * @return string|null
     */
    protected function extractFareType(array $fare): ?string
    {
        $remarks = $fare['Fare']['FareDetail']['Remarks']['Remark'] ?? [];
        foreach ($remarks as $remark) {
            if (isset($remark['value'])) {
                return $remark['value'];
            }
        }
        return null;
    }

    /**
     * Format associations
     *
     * @param array $association
     * @return array
     */
    protected function formatAssociations(array $association): array
    {
        return [
            'priceClass' => [
                'reference' => $association['PriceClass']['PriceClassReference'] ?? null
            ],
            'applicableFlight' => [
                'flightRefs' => $association['ApplicableFlight']['FlightReferences']['value'] ?? [],
                'segmentRefs' => array_map(
                    [$this, 'formatFlightSegmentReference'],
                    $association['ApplicableFlight']['FlightSegmentReference'] ?? []
                ),
                'originDestinationRefs' => $association['ApplicableFlight']['OriginDestinationReferences'] ?? []
            ]
        ];
    }

    /**
     * Format flight segment reference
     *
     * @param array $segmentRef
     * @return array
     */
    protected function formatFlightSegmentReference(array $segmentRef): array
    {
        return [
            'ref' => $segmentRef['ref'] ?? null,
            'classOfService' => [
                'code' => $segmentRef['ClassOfService']['Code']['value'] ?? null,
                'marketingName' => [
                    'value' => $segmentRef['ClassOfService']['MarketingName']['value'] ?? null,
                    'cabinDesignator' => $segmentRef['ClassOfService']['MarketingName']['CabinDesignator'] ?? null
                ],
                'seatsLeft' => $segmentRef['ClassOfService']['Code']['SeatsLeft'] ?? null
            ]
        ];
    }

    /**
     * Format individual offer
     *
     * @param array $offer
     * @param string|null $owner
     * @return array
     */
    protected function formatOffer(array $offer, ?string $owner): array
    {
        return [
            'offerId' => $offer['OfferID']['value'] ?? null,
            'owner' => $owner ?? ($offer['OfferID']['Owner'] ?? null),
            'channel' => $offer['OfferID']['Channel'] ?? 'NDC',
            'totalPrice' => $this->formatPrice($offer['TotalPrice'] ?? []),
            'pricedOffer' => $this->formatPricedOffer($offer['PricedOffer'] ?? []),
            'timeLimit' => $this->formatTimeLimit($offer['TimeLimits'] ?? []),
            'commission' => $this->formatCommission($offer['Commission'] ?? []),
            'references' => $offer['refs'] ?? []
        ];
    }

    /**
     * Format priced offer information
     *
     * @param array $pricedOffer
     * @return array
     */
    protected function formatPricedOffer(array $pricedOffer): array
    {
        return [
            'associations' => array_map([$this, 'formatAssociations'], $pricedOffer['Associations'] ?? []),
            'offerPrice' => array_map(function ($price) {
                return [
                    'requestedDate' => [
                        'associations' => $this->formatRequestedDateAssociations($price['RequestedDate']['Associations'] ?? []),
                        'priceDetail' => $this->formatPriceDetail($price['RequestedDate']['PriceDetail'] ?? [])
                    ],
                    'fareDetail' => $this->formatFareDetail($price['FareDetail'] ?? []),
                    'offerItemId' => $price['OfferItemID'] ?? null
                ];
            }, $pricedOffer['OfferPrice'] ?? [])
        ];
    }

    /**
     * Format price class information
     *
     * @param array $priceClass
     * @return array
     */
    protected function formatPriceClass(array $priceClass): array
    {
        return [
            'objectKey' => $priceClass['ObjectKey'] ?? null,
            'name' => $priceClass['Name'] ?? null,
            'code' => $priceClass['Code'] ?? null,
            'displayOrder' => $priceClass['DisplayOrder'] ?? null,
            'descriptions' => array_map(function ($description) {
                $formatted = [
                    'text' => $description['Text']['value'] ?? null,
                    'category' => $description['Category'] ?? null
                ];

                if (isset($description['Media'])) {
                    $formatted['media'] = $this->formatMedia($description['Media']);
                }

                return $formatted;
            }, $priceClass['Descriptions']['Description'] ?? [])
        ];
    }

    /**
     * Format fare information
     *
     * @param array $fare
     * @return array
     */
    protected function formatFare(array $fare): array
    {
        return [
            'listKey' => $fare['ListKey'] ?? null,
            'fareBasisCode' => $fare['FareBasisCode']['Code'] ?? null,
            'fareCode' => $fare['Fare']['FareCode']['Code'] ?? null,
            'fareType' => $this->extractFareType($fare),
            'fareDetail' => $this->formatFareDetail($fare['Fare']['FareDetail'] ?? []),
            'references' => $fare['refs'] ?? []
        ];
    }

    /**
     * Format time limit information
     *
     * @param array $timeLimit
     * @return array|null
     */
    protected function formatTimeLimit(array $timeLimit): ?array
    {
        if (empty($timeLimit)) {
            return null;
        }

        return [
            'expirationDateTime' => $this->formatDateTime($timeLimit['OfferExpiration']['DateTime'] ?? null),
            'price' => $this->formatPrice($timeLimit['Price'] ?? []),
            'guaranteed' => $timeLimit['Guaranteed'] ?? false
        ];
    }


    /**
     * Format commission information
     *
     * @param array $commission
     * @return array
     */
    protected function formatCommission(array $commission): array
    {
        return array_map(function ($comm) {
            return [
                'amount' => [
                    'value' => (float) ($comm['Amount']['value'] ?? 0),
                    'currency' => $comm['Amount']['code'] ?? null
                ],
                'percentage' => $comm['Percentage'] ?? null,
                'code' => $comm['Code'] ?? null
            ];
        }, $commission);
    }

    /**
     * Format piece allowance
     *
     * @param array $pieceAllowance
     * @return array
     */
    protected function formatPieceAllowance(array $pieceAllowance): array
    {
        return array_map(function ($allowance) {
            return [
                'applicableParty' => $allowance['ApplicableParty'] ?? null,
                'totalQuantity' => $allowance['TotalQuantity'] ?? 0,
                'measurements' => array_map(function ($measurement) {
                    return [
                        'quantity' => $measurement['Quantity'] ?? 0,
                        'weight' => $measurement['Weight'] ?? null,
                        'dimensions' => $measurement['Dimensions'] ?? null
                    ];
                }, $allowance['PieceMeasurements'] ?? []),
                'combination' => $allowance['PieceAllowanceCombination'] ?? null,
                'applicableBag' => $allowance['ApplicableBag'] ?? null
            ];
        }, $pieceAllowance);
    }


    /**
     * Format requested date associations
     *
     * @param array $associations
     * @return array
     */
    protected function formatRequestedDateAssociations(array $associations): array
    {
        return array_map(function ($association) {
            return [
                'associatedTraveler' => [
                    'references' => $association['AssociatedTraveler']['TravelerReferences'] ?? []
                ],
                'applicableFlight' => [
                    'flightReferences' => [
                        'value' => $association['ApplicableFlight']['FlightReferences']['value'] ?? []
                    ],
                    'segmentReferences' => array_map(function ($segment) {
                        return [
                            'ref' => $segment['ref'] ?? null,
                            'baggage' => [
                                'carryOn' => $segment['BagDetailAssociation']['CarryOnReferences'] ?? [],
                                'checked' => $segment['BagDetailAssociation']['CheckedBagReferences'] ?? []
                            ]
                        ];
                    }, $association['ApplicableFlight']['FlightSegmentReference'] ?? []),
                    'originDestinationReferences' => $association['ApplicableFlight']['OriginDestinationReferences'] ?? []
                ]
            ];
        }, $associations);
    }

    /**
     * Format price detail information
     *
     * @param array $priceDetail
     * @return array
     */
    protected function formatPriceDetail(array $priceDetail): array
    {
        return [
            'baseAmount' => [
                'amount' => (float) ($priceDetail['BaseAmount']['value'] ?? 0),
                'currency' => $priceDetail['BaseAmount']['Code'] ?? null
            ],
            'taxes' => [
                'total' => [
                    'amount' => (float) ($priceDetail['Taxes']['Total']['value'] ?? 0),
                    'currency' => $priceDetail['Taxes']['Total']['Code'] ?? null
                ],
                'breakdown' => array_map(function ($tax) {
                    return [
                        'taxCode' => $tax['TaxCode'] ?? null,
                        'amount' => [
                            'value' => (float) ($tax['Amount']['value'] ?? 0),
                            'currency' => $tax['Amount']['Code'] ?? null
                        ],
                        'description' => $tax['Description'] ?? null
                    ];
                }, $priceDetail['Taxes']['Breakdown']['Tax'] ?? [])
            ],
            'surcharges' => $this->formatSurcharges($priceDetail['Surcharges'] ?? []),
            'fees' => $this->formatFees($priceDetail['Fees'] ?? []),
            'totalAmount' => [
                'amount' => (float) ($priceDetail['TotalAmount']['SimpleCurrencyPrice']['value'] ?? 0),
                'currency' => $priceDetail['TotalAmount']['SimpleCurrencyPrice']['Code'] ?? null
            ],
            'discounts' => array_map(function ($discount) {
                return [
                    'discountOwner' => $discount['discountOwner'] ?? null,
                    'discountCode' => $discount['discountCode'] ?? null,
                    'discountName' => $discount['discountName'] ?? null,
                    'description' => $discount['Description'] ?? null,
                    'application' => $discount['Application'] ?? null,
                    'amount' => [
                        'value' => (float) ($discount['DiscountAmount']['value'] ?? 0),
                        'currency' => $discount['DiscountAmount']['Code'] ?? null
                    ],
                    'percentage' => (float) ($discount['DiscountPercent'] ?? 0),
                    'preDiscountedAmount' => [
                        'value' => (float) ($discount['preDiscountedAmount']['value'] ?? 0),
                        'currency' => $discount['preDiscountedAmount']['Code'] ?? null
                    ]
                ];
            }, $priceDetail['Discount'] ?? [])
        ];
    }


    /**
     * Format fare detail information
     *
     * @param array $fareDetail
     * @return array
     */
    protected function formatFareDetail(array $fareDetail): array
    {
        if (empty($fareDetail)) {
            return [];
        }

        return [
            'fareComponents' => array_map(function ($component) {
                return [
                    'refs' => $component['refs'] ?? [],
                    'segmentReference' => [
                        'value' => $component['SegmentReference']['value'] ?? null
                    ],
                    'fareRules' => [
                        'corporate' => $this->formatCorporateFare($component['FareRules']['CorporateFare'] ?? []),
                        'penalties' => array_map(function ($ref) {
                            return $ref;
                        }, $component['FareRules']['Penalty']['refs'] ?? [])
                    ]
                ];
            }, $fareDetail['FareComponent'] ?? []),
            'remarks' => array_map(function ($remark) {
                return [
                    'code' => $remark['Code'] ?? null,
                    'value' => $remark['value'] ?? null,
                    'type' => $remark['Type'] ?? null
                ];
            }, $fareDetail['Remarks']['Remark'] ?? []),
            'fareIndicators' => [
                'refundable' => $fareDetail['FareIndicators']['Refundable'] ?? false,
                'changeable' => $fareDetail['FareIndicators']['Changeable'] ?? false,
                'upgradeable' => $fareDetail['FareIndicators']['Upgradeable'] ?? false
            ]
        ];
    }

    /**
     * Format media information
     *
     * @param array $media
     * @return array
     */
    protected function formatMedia(array $media): array
    {
        return array_map(function ($item) {
            $mediaRef = null;

            // Handle MediaRef structure
            if (isset($item['MediaRef']['ref'])) {
                $mediaRef = $this->mediaReferences[$item['MediaRef']['ref']] ?? null;
            }

            if ($mediaRef) {
                return [
                    'reference' => $item['MediaRef']['ref'],
                    'links' => $mediaRef['links'] ?? [],
                    'descriptions' => $mediaRef['descriptions'] ?? []
                ];
            }

            // Handle direct media structure if no reference
            return [
                'links' => isset($item['MediaLinks']) ?
                    $this->formatMediaLinks($item['MediaLinks']) : [],
                'descriptions' => array_map(function ($desc) {
                    return $desc['Text']['value'] ?? null;
                }, $item['Descriptions']['Description'] ?? [])
            ];
        }, $media);
    }

    /**
     * Format surcharges
     *
     * @param array $surcharges
     * @return array
     */
    protected function formatSurcharges(array $surcharges): array
    {
        return array_map(function ($surcharge) {
            return [
                'total' => [
                    'amount' => (float) ($surcharge['Total']['value'] ?? 0),
                    'currency' => $surcharge['Total']['Code'] ?? null
                ],
                'breakdown' => array_map(function ($fee) {
                    return [
                        'amount' => [
                            'value' => (float) ($fee['Amount']['value'] ?? 0),
                            'currency' => $fee['Amount']['Code'] ?? null
                        ],
                        'designator' => $fee['Designator'] ?? null,
                        'description' => $fee['Description'] ?? null,
                        'feeOwner' => $fee['FeeOwner'] ?? null,
                        'feePercent' => (float) ($fee['FeePercent'] ?? 0)
                    ];
                }, $surcharge['Breakdown']['Fee'] ?? [])
            ];
        }, $surcharges['Surcharge'] ?? []);
    }

    /**
     * Format fees
     *
     * @param array $fees
     * @return array
     */
    protected function formatFees(array $fees): array
    {
        if (!isset($fees['Total']) || !isset($fees['Breakdown'])) {
            return [];
        }

        return [
            'total' => [
                'amount' => (float) ($fees['Total']['value'] ?? 0),
                'currency' => $fees['Total']['Code'] ?? null
            ],
            'breakdown' => array_map(function ($fee) {
                return [
                    'amount' => [
                        'value' => (float) ($fee['Amount']['value'] ?? 0),
                        'currency' => $fee['Amount']['Code'] ?? null
                    ],
                    'feeName' => $fee['FeeName'] ?? null,
                    'feeCode' => $fee['FeeCode'] ?? null,
                    'feeOwner' => $fee['FeeOwner'] ?? null,
                    'feePercent' => (float) ($fee['FeePercent'] ?? 0),
                    'refundInd' => $fee['RefundInd'] ?? false
                ];
            }, $fees['Breakdown']['Fee'] ?? [])
        ];
    }

    /**
     * Format corporate fare information
     *
     * @param array $corporateFare
     * @return array|null
     */
    protected function formatCorporateFare(array $corporateFare): ?array
    {
        if (empty($corporateFare)) {
            return null;
        }

        return [
            'account' => [
                'value' => $corporateFare['Account']['value'] ?? null,
                'code' => $corporateFare['Account']['Code'] ?? null
            ],
            'name' => $corporateFare['Name'] ?? null,
            'type' => $corporateFare['Type'] ?? null
        ];
    }


    /**
     * Format weight allowance
     *
     * @param array $weightAllowance
     * @return array
     */
    protected function formatWeightAllowance(array $weightAllowance): array
    {
        return [
            'applicableParty' => $weightAllowance['ApplicableParty'] ?? null,
            'maximumWeight' => array_map(function ($weight) {
                return [
                    'value' => (float) ($weight['Value'] ?? 0),
                    'unit' => $weight['UOM'] ?? null
                ];
            }, $weightAllowance['MaximumWeight'] ?? [])
        ];
    }

    /**
     * Format allowance description
     *
     * @param array $description
     * @return array
     */
    protected function formatAllowanceDescription(array $description): array
    {
        return [
            'applicableParty' => $description['ApplicableParty'] ?? null,
            'descriptions' => array_map(function ($desc) {
                return $desc['Text']['value'] ?? null;
            }, $description['Descriptions']['Description'] ?? [])
        ];
    }



    /**
     * Format flight segment
     *
     * @param array $segment
     * @return array
     */
    protected function formatFlightSegment(array $segment): array
    {
        return [
            'segmentKey' => $segment['SegmentKey'] ?? null,
            'departure' => [
                'airport' => $segment['Departure']['AirportCode']['value'] ?? null,
                'terminal' => $segment['Departure']['Terminal']['Name'] ?? null,
                'date' => $this->formatDateTime($segment['Departure']['Date'] ?? null),
                'time' => $segment['Departure']['Time'] ?? null,
                'airportName' => $segment['Departure']['AirportName'] ?? null
            ],
            'arrival' => [
                'airport' => $segment['Arrival']['AirportCode']['value'] ?? null,
                'terminal' => $segment['Arrival']['Terminal']['Name'] ?? null,
                'date' => $this->formatDateTime($segment['Arrival']['Date'] ?? null),
                'time' => $segment['Arrival']['Time'] ?? null,
                'airportName' => $segment['Arrival']['AirportName'] ?? null,
                'changeOfDay' => $segment['Arrival']['ChangeOfDay'] ?? 0
            ],
            'marketing' => [
                'carrier' => $segment['MarketingCarrier']['AirlineID']['value'] ?? null,
                'flightNumber' => $segment['MarketingCarrier']['FlightNumber']['value'] ?? null,
                'name' => $segment['MarketingCarrier']['Name'] ?? null
            ],
            'operating' => $this->formatOperatingCarrier($segment['OperatingCarrier'] ?? []),
            'equipment' => [
                'code' => $segment['Equipment']['AircraftCode']['value'] ?? null,
                'name' => $segment['Equipment']['Name'] ?? null
            ],
            'duration' => $this->parseDuration($segment['FlightDetail']['FlightDuration']['Value'] ?? null),
            'stops' => $this->formatStops($segment['FlightDetail']['Stops'] ?? [])
        ];
    }

    /**
     * Format price information
     *
     * @param array $price
     * @return array
     */
    protected function formatPrice(array $price): array
    {
        if (isset($price['SimpleCurrencyPrice'])) {
            $currency = $price['SimpleCurrencyPrice']['Code'] ?? 'INR';
            return [
                'amount' => $this->formatAmount((float)($price['SimpleCurrencyPrice']['value'] ?? 0), $currency),
                'currency' => $currency
            ];
        }

        $currency = $price['TotalAmount']['Code'] ?? 'INR';

        return [
            'amount' => $this->formatAmount((float)($price['TotalAmount']['value'] ?? 0), $currency),
            'currency' => $currency,
            'base' => [
                'amount' => $this->formatAmount((float)($price['BaseAmount']['value'] ?? 0), $currency),
                'currency' => $currency
            ],
            'taxes' => $this->formatTaxes($price['Taxes'] ?? [], $currency)
        ];
    }

    /**
     * Format taxes
     *
     * @param array $taxes
     * @return array
     */
    protected function formatTaxes(array $taxes, string $currency): array
    {
        $taxDetails = [];
        foreach ($taxes['Tax'] ?? [] as $tax) {
            $taxCurrency = $tax['Amount']['Code'] ?? $currency;
            $taxDetails[] = [
                'code' => $tax['TaxCode'] ?? null,
                'amount' => $this->formatAmount((float)($tax['Amount']['value'] ?? 0), $taxCurrency),
                'currency' => $taxCurrency
            ];
        }
        return $taxDetails;
    }

    /**
     * Format checked baggage allowance
     *
     * @return array
     */
    protected function getCheckedBaggageAllowance(): array
    {
        $allowances = $this->data['DataLists']['CheckedBagAllowanceList']['CheckedBagAllowance'] ?? [];
        return array_map(function ($allowance) {
            return [
                'listKey' => $allowance['ListKey'] ?? null,
                'pieceAllowance' => $this->formatPieceAllowance($allowance['PieceAllowance'] ?? []),
                'weightAllowance' => $this->formatWeightAllowance($allowance['WeightAllowance'] ?? []),
                'description' => $this->formatAllowanceDescription($allowance['AllowanceDescription'] ?? [])
            ];
        }, $allowances);
    }

    /**
     * Format carry-on baggage allowance
     *
     * @return array
     */
    protected function getCarryOnBaggageAllowance(): array
    {
        $allowances = $this->data['DataLists']['CarryOnAllowanceList']['CarryOnAllowance'] ?? [];
        return array_map(function ($allowance) {
            return [
                'listKey' => $allowance['ListKey'] ?? null,
                'pieceAllowance' => $this->formatPieceAllowance($allowance['PieceAllowance'] ?? []),
                'weightAllowance' => $this->formatWeightAllowance($allowance['WeightAllowance'] ?? []),
                'description' => $this->formatAllowanceDescription($allowance['AllowanceDescription'] ?? [])
            ];
        }, $allowances);
    }

    /**
     * Format origin-destination information
     *
     * @param array $od
     * @return array
     */
    protected function formatOriginDestination(array $od): array
    {
        return [
            'key' => $od['OriginDestinationKey'] ?? null,
            'origin' => $od['DepartureCode']['value'] ?? null,
            'destination' => $od['ArrivalCode']['value'] ?? null,
            'flightReferences' => $od['FlightReferences']['value'] ?? []
        ];
    }

    /**
     * Format operating carrier information
     *
     * @param array $carrier
     * @return array|null
     */
    protected function formatOperatingCarrier(array $carrier): ?array
    {
        if (empty($carrier)) {
            return null;
        }

        return [
            'code' => $carrier['AirlineID']['value'] ?? null,
            'name' => $carrier['Name'] ?? null,
            'flightNumber' => $carrier['FlightNumber']['value'] ?? null,
            'disclosures' => $carrier['Disclosures']['Description'][0]['Text']['value'] ?? null
        ];
    }

    /**
     * Format flight stops information
     *
     * @param array $stops
     * @return array
     */
    protected function formatStops(array $stops): array
    {
        return [
            'count' => $stops['StopQuantity'] ?? 0,
            'locations' => array_map(function ($location) {
                return [
                    'airport' => $location['AirportCode']['value'] ?? null,
                    'arrivalTime' => $location['ArrivalTime'] ?? null,
                    'departureTime' => $location['DepartureTime'] ?? null,
                    'duration' => $location['Duration'] ?? null
                ];
            }, $stops['StopLocations']['StopLocation'] ?? [])
        ];
    }

    /**
     * Parse ISO 8601 duration
     *
     * @param string|null $duration
     * @return array|null
     */
    protected function parseDuration(?string $duration): ?array
    {
        if (!$duration) {
            return null;
        }

        try {
            preg_match('/PT(\d+H)?(\d+M)?/', $duration, $matches);
            $hours = isset($matches[1]) ? (int)rtrim($matches[1], 'H') : 0;
            $minutes = isset($matches[2]) ? (int)rtrim($matches[2], 'M') : 0;

            return [
                'hours' => $hours,
                'minutes' => $minutes,
                'total_minutes' => ($hours * 60) + $minutes
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Safely access array elements with default value
     *
     * @param array $array
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function safeArrayAccess(array $array, string $key, $default = null)
    {
        return $array[$key] ?? $default;
    }

    /**
     * Format date time
     *
     * @param string|null $datetime
     * @return string|null
     */
    protected function formatDateTime(?string $datetime): ?string
    {
        if (!$datetime) {
            return null;
        }

        try {
            return Carbon::parse($datetime)->format('Y-m-d\TH:i:s\Z');
        } catch (\Exception $e) {
            return $datetime;
        }
    }

    /**
     * Extract currency metadata
     *
     * @return array
     */
    protected function extractCurrencyMetadata(): array
    {
        $currencyMetadata = $this->data['Metadata']['Other']['OtherMetadata'] ?? [];
        $currencies = [];

        foreach ($currencyMetadata as $metadata) {
            if (isset($metadata['CurrencyMetadatas']['CurrencyMetadata'])) {
                foreach ($metadata['CurrencyMetadatas']['CurrencyMetadata'] as $currency) {
                    $currencies[$currency['MetadataKey']] = [
                        'decimals' => $currency['Decimals'] ?? 2
                    ];
                }
            }
        }

        return $currencies;
    }

    /**
     * Initialize currency decimals from metadata
     */
    protected function initializeCurrencyDecimals(): void
    {
        if (isset($this->data['Metadata']['Other']['OtherMetadata'])) {
            foreach ($this->data['Metadata']['Other']['OtherMetadata'] as $metadata) {
                if (isset($metadata['CurrencyMetadatas']['CurrencyMetadata'])) {
                    foreach ($metadata['CurrencyMetadatas']['CurrencyMetadata'] as $currency) {
                        // Extract currency code from metadata key (e.g. "LHG-EUR" -> "EUR")
                        if (preg_match('/[A-Z]{3}$/', $currency['MetadataKey'], $matches)) {
                            $this->currencyDecimals[$matches[0]] = $currency['Decimals'];
                        }
                    }
                }
            }
        }
    }

    /**
     * Build media reference mapping
     */
    protected function buildMediaReferences(): void
    {
        if (isset($this->data['DataLists']['MediaList']['Media'])) {
            foreach ($this->data['DataLists']['MediaList']['Media'] as $media) {
                if (isset($media['ListKey'])) {
                    $this->mediaReferences[$media['ListKey']] = [
                        'links' => $this->formatMediaLinks($media['MediaLinks'] ?? []),
                        'descriptions' => array_map(function ($desc) {
                            return $desc['Text']['value'] ?? null;
                        }, $media['Descriptions']['Description'] ?? [])
                    ];
                }
            }
        }
    }

    /**
     * Format media links by size
     */
    protected function formatMediaLinks(array $mediaLinks): array
    {
        $formattedLinks = [];

        foreach ($mediaLinks as $link) {
            $formattedLinks[$link['Size']] = [
                'url' => $link['Url'] ?? null,
                'type' => $link['Type'] ?? null,
                'size' => $link['Size'] ?? null
            ];
        }

        return $formattedLinks;
    }

    /**
     * Format monetary amount with correct decimals
     */
    protected function formatAmount(float $amount, string $currency): float
    {
        $decimals = $this->currencyDecimals[$currency] ?? 2;
        return round($amount, $decimals);
    }
}
