<?php

namespace Santosdave\VerteilWrapper\Responses;

use Carbon\Carbon;

class FlightPriceResponse extends BaseResponse
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
        return [
            'success' => $this->isSuccessful(),
            'response_id' => $this->getResponseId(),
            'correlation_id' => $this->getCorrelationId(),
            'timestamp' => $this->getTimestamp(),
            'priced_offers' => $this->getPricedOffers(),
            'data_lists' => [
                'flights' => $this->getFlightList(),
                'flight_segments' => $this->getFlightSegments(),
                'origin_destinations' => $this->getOriginDestinations(),
                'travelers' => $this->getTravelers(),
                'services' => $this->getServices(),
                'baggage' => $this->getBaggageAllowance(),
                'price_classes' => $this->getPriceClasses(),
                'fares' => $this->getFares(),
                'penalties' => $this->getPenalties()
            ],
            'metadata' => [
                'currency' => $this->extractCurrencyMetadata(),
                'price' => $this->extractPriceMetadata(),
                'payment_card' => $this->extractPaymentCardMetadata()
            ],
            'payments' => $this->getPayments(),
            'warnings' => $this->getWarnings(),
            'errors' => $this->getErrors(),
            'statistics' => $this->getStatistics()
        ];
    }

    /**
     * Get priced offers from response
     */
    public function getPricedOffers(): array
    {
        $offers = [];
        $pricedOffers = $this->data['PricedFlightOffers']['PricedFlightOffer'] ?? [];

        foreach ($pricedOffers as $offer) {
            $offers[] = [
                'offer_id' => [
                    'value' => $offer['OfferID']['value'] ?? null,
                    'owner' => $offer['OfferID']['Owner'] ?? null,
                    'object_key' => $offer['OfferID']['ObjectKey'] ?? null,
                    'channel' => $offer['OfferID']['Channel'] ?? 'NDC'
                ],
                'offer_items' => $this->formatOfferItems($offer['OfferPrice'] ?? []),
                'time_limits' => $this->formatTimeLimits($offer['TimeLimits'] ?? [])
            ];
        }

        return $offers;
    }

    /**
     * Format offer items
     */
    protected function formatOfferItems(array $offerPrices): array
    {
        $items = [];
        foreach ($offerPrices as $price) {
            $items[] = [
                'offer_item_id' => $price['OfferItemID'] ?? null,
                'associations' => $this->formatAssociations($price['RequestedDate']['Associations'] ?? []),
                'price_detail' => $this->formatPriceDetail($price['RequestedDate']['PriceDetail'] ?? []),
                'fare_detail' => $this->formatFareDetail($price['FareDetail'] ?? []),
                'commission' => $this->formatCommission($price['Commission'] ?? [])
            ];
        }
        return $items;
    }

    /**
     * Format associations including traveler, flight and service references
     */
    protected function formatAssociations(array $associations): array
    {
        return array_map(function ($association) {
            return [
                'travelers' => $association['AssociatedTraveler']['TravelerReferences'] ?? [],
                'flights' => [
                    'segments' => $this->formatFlightSegmentReferences($association['ApplicableFlight']['FlightSegmentReference'] ?? []),
                    'references' => $association['ApplicableFlight']['FlightReferences']['value'] ?? [],
                    'origin_destination_refs' => $association['ApplicableFlight']['OriginDestinationReferences'] ?? []
                ],
                'price_class' => $association['PriceClass']['PriceClassReference'] ?? null,
                'services' => $this->formatAssociatedServices($association['AssociatedService'] ?? [])
            ];
        }, $associations);
    }


    /**
     * Format flight segment references including class of service and baggage details
     */
    protected function formatFlightSegmentReferences(array $references): array
    {
        return array_map(function ($ref) {
            return [
                'reference' => $ref['ref'] ?? null,
                'class_of_service' => [
                    'code' => $ref['ClassOfService']['Code']['value'] ?? null,
                    'marketing_name' => [
                        'value' => $ref['ClassOfService']['MarketingName']['value'] ?? null,
                        'cabin_designator' => $ref['ClassOfService']['MarketingName']['CabinDesignator'] ?? null
                    ],
                    'class_of_service_refs' => $ref['ClassOfService']['refs'] ?? null,
                ],
                'baggage' => [
                    'carry_on_refs' => $ref['BagDetailAssociation']['CarryOnReferences'] ?? [],
                    'checked_refs' => $ref['BagDetailAssociation']['CheckedBagReferences'] ?? [],
                    'disclosure_refs' => $ref['BagDetailAssociation']['BagDisclosureReferences'] ?? null
                ]
            ];
        }, $references);
    }

    /**
     * Format associated services including seat assignments
     */
    protected function formatAssociatedServices(array $services): array
    {
        if (empty($services)) {
            return [];
        }

        return [
            'references' => $services['ServiceReferences'] ?? [],
            'seat_assignments' => array_map(function ($assignment) {
                return [
                    'location' => [
                        'column' => $assignment['Seat']['Location']['Column'] ?? null,
                        'row' => $assignment['Seat']['Location']['Row']['Number']['value'] ?? null,
                        'characteristics' => $this->formatSeatCharacteristics($assignment['Seat']['Location']['Characteristics'] ?? [])
                    ]
                ];
            }, $services['SeatAssignment'] ?? [])
        ];
    }

    /**
     * Format price details including base amount, taxes, fees and total
     */
    protected function formatPriceDetail(array $detail): array
    {
        return [
            'base_amount' => $this->formatAmount($detail['BaseAmount'] ?? []),
            'taxes' => $this->formatTaxes($detail['Taxes'] ?? []),
            'fees' => $this->formatFees($detail['Fees'] ?? []),
            'surcharges' => $this->formatSurcharges($detail['Surcharges'] ?? []),
            'total_amount' => [
                'value' => (float)($detail['TotalAmount']['SimpleCurrencyPrice']['value'] ?? 0),
                'currency' => $detail['TotalAmount']['SimpleCurrencyPrice']['Code'] ?? null
            ],
            'discounts' => $this->formatDiscounts($detail['Discount'] ?? [])
        ];
    }

    protected function getPayments(): array
    {
        $payments = $this->data['Payments']['Payment'] ?? [];
        return array_map(function ($payment) {
            return [
                'payment_surcharge' => [
                    'precise_amount' => isset($payment['PaymentSurcharge']['preciseAmount']) ?
                        $this->formatAmount($payment['PaymentSurcharge']['preciseAmount']) : null,
                    'percentage_range' => [
                        'min' => $payment['PaymentSurcharge']['percentageRangeMin']['value'] ?? null,
                        'max' => $payment['PaymentSurcharge']['percentageRangeMax']['value'] ?? null
                    ]
                ]
            ];
        }, $payments);
    }


    /**
     * Format taxes including total and breakdown
     */
    protected function formatTaxes(array $taxes): array
    {
        return [
            'total' => $this->formatAmount($taxes['Total'] ?? []),
            'breakdown' => array_map(function ($tax) {
                return [
                    'code' => $tax['TaxCode'] ?? null,
                    'amount' => $this->formatAmount($tax['Amount'] ?? []),
                    'description' => $tax['Description'] ?? null
                ];
            }, $taxes['Breakdown']['Tax'] ?? [])
        ];
    }

    /**
     * Format fees including total and breakdown
     */
    protected function formatFees(array $fees): array
    {
        if (empty($fees)) {
            return [];
        }

        return [
            'total' => $this->formatAmount($fees['Total'] ?? []),
            'breakdown' => array_map(function ($fee) {
                return [
                    'code' => $fee['FeeCode'] ?? null,
                    'amount' => $this->formatAmount($fee['Amount'] ?? []),
                    'name' => $fee['FeeName'] ?? null,
                    'owner' => $fee['FeeOwner'] ?? null,
                    'percentage' => $fee['FeePercent'] ?? null,
                    'refundable' => $fee['RefundInd'] ?? false
                ];
            }, $fees['Breakdown']['Fee'] ?? [])
        ];
    }

    /**
     * Format surcharges including total and breakdown
     */
    protected function formatSurcharges(array $surcharges): array
    {
        if (empty($surcharges)) {
            return [];
        }

        $formattedSurcharges = [];
        foreach ($surcharges['Surcharge'] ?? [] as $surcharge) {
            $formattedSurcharges[] = [
                'total' => $this->formatAmount($surcharge['Total'] ?? []),
                'breakdown' => array_map(function ($fee) {
                    return [
                        'amount' => $this->formatAmount($fee['Amount'] ?? []),
                        'designator' => $fee['Designator'] ?? null,
                        'description' => $fee['Description'] ?? null,
                        'owner' => $fee['FeeOwner'] ?? null,
                        'percentage' => $fee['FeePercent'] ?? null
                    ];
                }, $surcharge['Breakdown']['Fee'] ?? [])
            ];
        }
        return $formattedSurcharges;
    }

    /**
     * Format discounts
     */
    protected function formatDiscounts(array $discounts): array
    {
        return array_map(function ($discount) {
            return [
                'amount' => $this->formatAmount($discount['DiscountAmount'] ?? []),
                'percentage' => $discount['DiscountPercent'] ?? null,
                'owner' => $discount['discountOwner'] ?? null,
                'code' => $discount['discountCode'] ?? null,
                'name' => $discount['discountName'] ?? null,
                'pre_discounted_amount' => $this->formatAmount($discount['preDiscountedAmount'] ?? [])
            ];
        }, $discounts);
    }

    /**
     * Format fare details including components and rules
     */
    protected function formatFareDetail(array $detail): array
    {
        if (empty($detail)) {
            return [];
        }

        return [
            'components' => array_map(function ($component) {
                return [
                    'fare_basis' => [
                        'code' => $component['FareBasis']['FareBasisCode']['Code'] ?? null,
                        'rbd' => $component['FareBasis']['RBD'] ?? null
                    ],
                    'rules' => $this->formatFareRules($component['FareRules'] ?? []),
                    'segment_refs' => $component['refs'] ?? []
                ];
            }, $detail['FareComponent'] ?? [])
        ];
    }

    /**
     * Format fare rules including penalties
     */
    protected function formatFareRules(array $rules): array
    {
        if (empty($rules)) {
            return [];
        }

        return [
            'penalty_refs' => $rules['Penalty']['refs'] ?? [],
            'change_fees' => $rules['ChangeFees'] ?? [],
            'cancellation_fees' => $rules['CancellationFees'] ?? [],
            'corporate_fare' => isset($rules['CorporateFare']) ? [
                'account' => [
                    'code' => $rules['CorporateFare']['Account']['Code'] ?? null,
                    'value' => $rules['CorporateFare']['Account']['value'] ?? null
                ],
                'name' => $rules['CorporateFare']['Name'] ?? null,
                'type' => $rules['CorporateFare']['Type'] ?? null
            ] : null
        ];
    }

    /**
     * Format commission information
     */
    protected function formatCommission(array $commission): array
    {
        return array_map(function ($comm) {
            return [
                'amount' => $this->formatAmount($comm['Amount'] ?? []),
                'percentage' => isset($comm['Percentage']) ?
                    $this->formatAmount($comm['Percentage']) : null,
                'code' => $comm['Code'] ?? null,
                // Add owner and type info
                'owner' => $comm['Owner'] ?? null,
                'type' => $comm['Type'] ?? null
            ];
        }, $commission);
    }

    /**
     * Get flight segments from response
     */
    public function getFlightSegments(): array
    {
        $segments = $this->data['DataLists']['FlightSegmentList']['FlightSegment'] ?? [];
        return array_map(function ($segment) {
            return [
                'segment_key' => $segment['SegmentKey'] ?? null,
                'departure' => $this->formatDepartureArrival($segment['Departure'] ?? []),
                'arrival' => $this->formatDepartureArrival($segment['Arrival'] ?? []),
                'marketing_carrier' => $this->formatCarrier($segment['MarketingCarrier'] ?? []),
                'operating_carrier' => $this->formatCarrier($segment['OperatingCarrier'] ?? []),
                'equipment' => [
                    'code' => $segment['Equipment']['AircraftCode']['value'] ?? null,
                    'name' => $segment['Equipment']['Name'] ?? null
                ],
                'duration' => $this->parseDuration($segment['FlightDetail']['FlightDuration']['Value'] ?? null),
                'stops' => $this->formatStops($segment['FlightDetail']['Stops'] ?? [])
            ];
        }, $segments);
    }

    /**
     * Get flight list from response
     */
    public function getFlightList(): array
    {
        $flights = $this->data['DataLists']['FlightList']['Flight'] ?? [];
        return array_map(function ($flight) {
            return [
                'flight_key' => $flight['FlightKey'] ?? null,
                'segment_refs' => $flight['SegmentReferences']['value'] ?? [],
                'journey' => [
                    'time' => $this->parseDuration($flight['Journey']['Time'] ?? null),
                    'distance' => [
                        'value' => $flight['Journey']['Distance']['Value'] ?? null,
                        'unit' => $flight['Journey']['Distance']['UOM'] ?? null
                    ]
                ]
            ];
        }, $flights);
    }

    /**
     * Get origin destinations from response
     */
    public function getOriginDestinations(): array
    {
        $ods = $this->data['DataLists']['OriginDestinationList']['OriginDestination'] ?? [];
        return array_map(function ($od) {
            return [
                'key' => $od['OriginDestinationKey'] ?? null,
                'departure_code' => $od['DepartureCode']['value'] ?? null,
                'arrival_code' => $od['ArrivalCode']['value'] ?? null,
                'flight_refs' => $od['FlightReferences']['value'] ?? []
            ];
        }, $ods);
    }


    /**
     * Get travelers from response
     */
    public function getTravelers(): array
    {
        $travelers = [];

        // Handle anonymous travelers
        $anonymousTravelers = $this->data['DataLists']['AnonymousTravelerList']['AnonymousTraveler'] ?? [];
        foreach ($anonymousTravelers as $traveler) {
            $travelers[] = [
                'type' => 'anonymous',
                'object_key' => $traveler['ObjectKey'] ?? null,
                'ptc' => $traveler['PTC']['value'] ?? null,
                'age' => isset($traveler['Age']) ? [
                    'value' => $traveler['Age']['Value']['value'] ?? null,
                    'birth_date' => $this->formatDateTime($traveler['Age']['BirthDate']['value'] ?? null)
                ] : null
            ];
        }

        // Handle recognized travelers
        $recognizedTravelers = $this->data['DataLists']['RecognizedTravelerList']['RecognizedTraveler'] ?? [];
        foreach ($recognizedTravelers as $traveler) {
            $travelers[] = [
                'type' => 'recognized',
                'object_key' => $traveler['ObjectKey'] ?? null,
                'ptc' => $traveler['PTC']['value'] ?? null,
                'name' => isset($traveler['Name']) ? [
                    'given' => array_map(function ($given) {
                        return $given['value'] ?? null;
                    }, (array)($traveler['Name']['Given'] ?? [])),
                    'surname' => $traveler['Name']['Surname']['value'] ?? null,
                    'title' => $traveler['Name']['Title'] ?? null
                ] : null,
                'frequent_flyer' => array_map(function ($fqtv) {
                    return [
                        'airline_id' => $fqtv['AirlineID']['value'] ?? null,
                        'account_number' => $fqtv['Account']['Number'][0]['value'] ?? null,
                        'program_id' => $fqtv['ProgramID'] ?? null
                    ];
                }, $traveler['FQTVs'] ?? [])
            ];
        }

        return $travelers;
    }

    /**
     * Get baggage allowance information
     */
    public function getBaggageAllowance(): array
    {
        return [
            'checked' => $this->getCheckedBaggageAllowance(),
            'carry_on' => $this->getCarryOnBaggageAllowance(),
            'disclosures' => $this->getBaggageDisclosures()
        ];
    }

    /**
     * Get checked baggage allowance
     */
    protected function getCheckedBaggageAllowance(): array
    {
        $allowances = $this->data['DataLists']['CheckedBagAllowanceList']['CheckedBagAllowance'] ?? [];
        return array_map(function ($allowance) {
            return [
                'list_key' => $allowance['ListKey'] ?? null,
                'piece_allowance' => $this->formatPieceAllowance($allowance['PieceAllowance'] ?? []),
                'weight_allowance' => $this->formatWeightAllowance($allowance['WeightAllowance'] ?? []),
                'description' => $this->formatBaggageDescription($allowance['AllowanceDescription'] ?? [])
            ];
        }, $allowances);
    }

    /**
     * Get carry-on baggage allowance
     */
    protected function getCarryOnBaggageAllowance(): array
    {
        $allowances = $this->data['DataLists']['CarryOnAllowanceList']['CarryOnAllowance'] ?? [];
        return array_map(function ($allowance) {
            return [
                'list_key' => $allowance['ListKey'] ?? null,
                'piece_allowance' => $this->formatPieceAllowance($allowance['PieceAllowance'] ?? []),
                'weight_allowance' => $this->formatWeightAllowance($allowance['WeightAllowance'] ?? []),
                'description' => $this->formatBaggageDescription($allowance['AllowanceDescription'] ?? [])
            ];
        }, $allowances);
    }

    /**
     * Get baggage disclosures
     */
    protected function getBaggageDisclosures(): array
    {
        $disclosures = $this->data['DataLists']['BagDisclosureList']['BagDisclosure'] ?? [];
        return array_map(function ($disclosure) {
            return [
                'list_key' => $disclosure['ListKey'] ?? null,
                'type' => $this->determineBaggageType($disclosure['ListKey']),
                'descriptions' => array_map(function ($desc) {
                    return $desc['Text']['value'] ?? null;
                }, $disclosure['Descriptions']['Description'] ?? []),
                'rule' => $disclosure['BagRule'] ?? null
            ];
        }, $disclosures);
    }

    /**
     * Format piece allowance information
     */
    protected function formatPieceAllowance(array $allowance): array
    {
        return array_map(function ($piece) {
            return [
                'applicable_party' => $piece['ApplicableParty'] ?? null,
                'total_quantity' => $piece['TotalQuantity'] ?? 0,
                'measurements' => array_map(function ($measurement) {
                    return [
                        'quantity' => $measurement['Quantity'] ?? 0,
                        'weight' => $measurement['Weight'] ?? null,
                        'dimensions' => $measurement['Dimensions'] ?? null
                    ];
                }, $piece['PieceMeasurements'] ?? []),
                'combination_type' => $piece['PieceAllowanceCombination'] ?? null
            ];
        }, $allowance);
    }

    /**
     * Format weight allowance information
     */
    protected function formatWeightAllowance(array $allowance): array
    {
        if (empty($allowance)) {
            return [];
        }

        return [
            'applicable_party' => $allowance['ApplicableParty'] ?? null,
            'maximum_weights' => array_map(function ($weight) {
                return [
                    'value' => $weight['Value'] ?? 0,
                    'unit' => $weight['UOM'] ?? null
                ];
            }, $allowance['MaximumWeight'] ?? [])
        ];
    }

    /**
     * Format baggage description
     */
    protected function formatBaggageDescription(array $description): array
    {
        if (empty($description)) {
            return [];
        }

        return [
            'applicable_party' => $description['ApplicableParty'] ?? null,
            'descriptions' => array_map(function ($desc) {
                return $desc['Text']['value'] ?? null;
            }, $description['Descriptions']['Description'] ?? [])
        ];
    }

    /**
     * Get price classes from response
     */
    public function getPriceClasses(): array
    {
        $priceClasses = $this->data['DataLists']['PriceClassList']['PriceClass'] ?? [];
        return array_map(function ($priceClass) {
            return [
                'object_key' => $priceClass['ObjectKey'] ?? null,
                'name' => $priceClass['Name'] ?? null,
                'code' => $priceClass['Code'] ?? null,
                'display_order' => $priceClass['DisplayOrder'] ?? null,
                'descriptions' => array_map(function ($desc) {
                    return [
                        'text' => $desc['Text']['value'] ?? null,
                        'category' => $desc['Category'] ?? null,
                        'media' => isset($desc['Media']) ? $this->formatMedia($desc['Media']) : null
                    ];
                }, $priceClass['Descriptions']['Description'] ?? [])
            ];
        }, $priceClasses);
    }

    /**
     * Get services from response
     */
    public function getServices(): array
    {
        $services = $this->data['DataLists']['ServiceList']['Service'] ?? [];
        return array_map(function ($service) {
            return [
                'object_key' => $service['ObjectKey'] ?? null,
                'service_id' => [
                    'value' => $service['ServiceID']['value'] ?? null,
                    'owner' => $service['ServiceID']['Owner'] ?? null
                ],
                'name' => $service['Name']['value'] ?? null,
                'descriptions' => array_map(function ($desc) {
                    return $desc['Text']['value'] ?? null;
                }, $service['Descriptions']['Description'] ?? []),
                'price' => isset($service['Price']) ? array_map(function ($price) {
                    return [
                        'total' => $this->formatAmount($price['Total'] ?? [])
                    ];
                }, $service['Price']) : []
            ];
        }, $services);
    }

    /**
     * Get fares from response
     */
    public function getFares(): array
    {
        $fares = $this->data['DataLists']['FareList']['FareGroup'] ?? [];
        return array_map(function ($fare) {
            return [
                'list_key' => $fare['ListKey'] ?? null,
                'fare_basis_code' => $fare['FareBasisCode']['Code'] ?? null,
                'fare' => [
                    'code' => $fare['Fare']['FareCode']['Code'] ?? null,
                    'type' => $this->extractFareType($fare['Fare']['FareDetail']['Remarks'] ?? [])
                ],
                'refs' => $fare['refs'] ?? []
            ];
        }, $fares);
    }

    /**
     * Get penalties from response
     */
    public function getPenalties(): array
    {
        $penalties = $this->data['DataLists']['PenaltyList']['Penalty'] ?? [];
        return array_map(function ($penalty) {
            return [
                'object_key' => $penalty['ObjectKey'] ?? null,
                'details' => array_map(function ($detail) {
                    return [
                        'type' => $detail['Type'] ?? null,
                        'application' => [
                            'code' => $detail['Application']['Code'] ?? null
                        ],
                        'amounts' => $this->formatPenaltyAmounts($detail['Amounts'] ?? [])
                    ];
                }, $penalty['Details']['Detail'] ?? []),
                'indicators' => [
                    'cancel_fee' => $penalty['CancelFeeInd'] ?? false,
                    'change_allowed' => $penalty['ChangeAllowedInd'] ?? false,
                    'refundable' => $penalty['RefundableInd'] ?? false,
                    'upgrade_fee' => $penalty['UpgradeFeeInd'] ?? false,
                    'change_fee' => $penalty['ChangeFeeInd'] ?? false
                ]
            ];
        }, $penalties);
    }

    /**
     * Format penalty amounts
     */
    protected function formatPenaltyAmounts(array $amounts): array
    {
        return array_map(function ($amount) {
            return [
                'value' => $this->formatAmount($amount['CurrencyAmountValue'] ?? []),
                'application' => $amount['AmountApplication'] ?? null,
                'remarks' => array_map(function ($remark) {
                    return $remark['value'] ?? null;
                }, $amount['ApplicableFeeRemarks']['Remark'] ?? [])
            ];
        }, $amounts['Amount'] ?? []);
    }

    /**
     * Format departure/arrival information
     */
    protected function formatDepartureArrival(array $point): array
    {
        return [
            'airport' => [
                'code' => $point['AirportCode']['value'] ?? null,
                'name' => $point['AirportName'] ?? null
            ],
            'terminal' => isset($point['Terminal']) ? [
                'name' => $point['Terminal']['Name'] ?? null
            ] : null,
            'time' => $point['Time'] ?? null,
            'date' => $this->formatDateTime($point['Date'] ?? null),
            'change_of_day' => $point['ChangeOfDay'] ?? 0
        ];
    }

    /**
     * Format carrier information
     */
    protected function formatCarrier(array $carrier): array
    {
        if (empty($carrier)) {
            return [];
        }

        return [
            'airline_id' => $carrier['AirlineID']['value'] ?? null,
            'name' => $carrier['Name'] ?? null,
            'flight_number' => $carrier['FlightNumber']['value'] ?? null
        ];
    }

    /**
     * Format stops information
     */
    protected function formatStops(array $stops): array
    {
        if (empty($stops)) {
            return [
                'count' => 0,
                'locations' => []
            ];
        }

        return [
            'count' => $stops['StopQuantity'] ?? 0,
            'locations' => array_map(function ($location) {
                return [
                    'airport' => $location['AirportCode']['value'] ?? null,
                    'arrival_time' => $location['ArrivalTime'] ?? null,
                    'departure_time' => $location['DepartureTime'] ?? null,
                    'duration' => $location['Duration'] ?? null,
                    'arrival_date' => $this->formatDateTime($location['ArrivalDate'] ?? null),
                    'departure_date' => $this->formatDateTime($location['DepartureDate'] ?? null)
                ];
            }, $stops['StopLocations']['StopLocation'] ?? [])
        ];
    }

    /**
     * Format time limits
     */
    protected function formatTimeLimits(array $limits): array
    {
        if (empty($limits)) {
            return [];
        }

        // Add guaranteed flag and price info
        return [
            'payment' => [
                'datetime' => $this->formatDateTime($limits['Payment']['DateTime'] ?? null)
            ],
            'offer_expiration' => [
                'datetime' => $this->formatDateTime($limits['OfferExpiration']['DateTime'] ?? null),
                'guaranteed' => $limits['OfferExpiration']['Guaranteed'] ?? false,
                'price' => isset($limits['OfferExpiration']['Price']) ?
                    $this->formatPrice($limits['OfferExpiration']['Price']) : null
            ]
        ];
    }

    /**
     * Get warnings from response
     */
    public function getWarnings(): array
    {
        $warnings = $this->data['Warnings']['Warning'] ?? [];
        return array_map(function ($warning) {
            return [
                'text' => $warning['value'] ?? null,
                'owner' => $warning['Owner'] ?? null
            ];
        }, $warnings);
    }

    /**
     * Get errors from response
     */
    public function getErrors(): array
    {
        $errors = $this->data['Errors']['Error'] ?? [];
        return array_map(function ($error) {
            return [
                'code' => $error['Code'] ?? null,
                'short_text' => $error['ShortText'] ?? null,
                'message' => $error['value'] ?? null,
                'owner' => $error['Owner'] ?? null,
                'reason' => $error['Reason'] ?? null
            ];
        }, $errors);
    }


    /**
     * Extract currency metadata
     */
    protected function extractCurrencyMetadata(): array
    {
        $metadata = [];

        if (isset($this->data['Metadata']['Other']['OtherMetadata'])) {
            foreach ($this->data['Metadata']['Other']['OtherMetadata'] as $meta) {
                if (isset($meta['CurrencyMetadatas']['CurrencyMetadata'])) {
                    foreach ($meta['CurrencyMetadatas']['CurrencyMetadata'] as $currency) {
                        $metadata[$currency['MetadataKey']] = [
                            'decimals' => $currency['Decimals'] ?? 2
                        ];
                    }
                }
            }
        }

        return $metadata;
    }

    /**
     * Extract price metadata
     */
    protected function extractPriceMetadata(): array
    {
        $metadata = [];

        if (isset($this->data['Metadata']['Other']['OtherMetadata'])) {
            foreach ($this->data['Metadata']['Other']['OtherMetadata'] as $meta) {
                if (isset($meta['PriceMetadatas']['PriceMetadata'])) {
                    foreach ($meta['PriceMetadatas']['PriceMetadata'] as $price) {
                        $metadata[$price['MetadataKey']] = [
                            'augmentation_points' => $this->formatAugmentationPoints($price['AugmentationPoint']['AugPoint'] ?? [])
                        ];
                    }
                }
            }
        }

        return $metadata;
    }

    /**
     * Extract payment card metadata
     */
    protected function extractPaymentCardMetadata(): array
    {
        $metadata = [];

        if (isset($this->data['Metadata']['Other']['OtherMetadata'])) {
            foreach ($this->data['Metadata']['Other']['OtherMetadata'] as $meta) {
                if (isset($meta['PaymentCardMetadatas']['PaymentCardMetadata'])) {
                    foreach ($meta['PaymentCardMetadatas']['PaymentCardMetadata'] as $card) {
                        $metadata[] = [
                            'name' => $card['CardName'] ?? null,
                            'type' => $card['CardType'] ?? null,
                            'code' => $card['CardCode'] ?? null,
                            'key' => $card['MetadataKey'] ?? null,
                            'fields' => $this->formatCardFields($card['CardFields'] ?? [])
                        ];
                    }
                }
            }
        }

        return $metadata;
    }

    /**
     * Format augmentation points
     */
    protected function formatAugmentationPoints(array $points): array
    {
        $formatted = [];
        foreach ($points as $point) {
            if (isset($point['any']['VdcAugPoint'])) {
                foreach ($point['any']['VdcAugPoint'] as $vdcPoint) {
                    $formatted[] = [
                        'key' => $vdcPoint['Key'] ?? null,
                        'value' => $vdcPoint['Value'] ?? null
                    ];
                }
            }
        }
        return $formatted;
    }

    /**
     * Format payment card fields
     */
    protected function formatCardFields(array $fields): array
    {
        if (!isset($fields['FieldName'])) {
            return [];
        }

        return [
            'name' => $fields['FieldName']['value'] ?? null,
            'mandatory' => $fields['FieldName']['Mandatory'] ?? false
        ];
    }

    /**
     * Get response ID
     */
    public function getResponseId(): ?string
    {
        return $this->data['ShoppingResponseID']['ResponseID']['value'] ?? null;
    }

    /**
     * Get correlation ID
     */
    public function getCorrelationId(): ?string
    {
        return $this->data['CorrelationID'] ?? null;
    }

    /**
     * Get response timestamp
     */
    protected function getTimestamp(): ?string
    {
        return isset($this->data['Metadata']['Timestamp']) ?
            $this->formatDateTime($this->data['Metadata']['Timestamp']) : null;
    }

    /**
     * Check if response is successful
     */
    public function isSuccessful(): bool
    {
        return empty($this->getErrors()) && !empty($this->getPricedOffers());
    }

    /**
     * Get response statistics
     */
    protected function getStatistics(): array
    {
        $offers = $this->getPricedOffers();
        $segments = $this->getFlightSegments();

        return [
            'total_offers' => count($offers),
            'total_segments' => count($segments),
            'response_time' => $this->calculateResponseTime(),
            'timestamp' => $this->getTimestamp()
        ];
    }


    /**
     * Format complete price information including base amount, taxes, and total
     * 
     * @param array $price Price data
     * @return array|null Formatted price data
     */
    protected function formatPrice(array $price): ?array
    {
        if (empty($price)) {
            return null;
        }

        return [
            'total_amount' => isset($price['TotalAmount']) ? [
                'value' => (float)($price['TotalAmount']['value'] ?? 0),
                'currency' => $price['TotalAmount']['Code'] ?? null
            ] : null,
            'base_amount' => isset($price['BaseAmount']) ? [
                'value' => (float)($price['BaseAmount']['value'] ?? 0),
                'currency' => $price['BaseAmount']['Code'] ?? null
            ] : null,
            'taxes' => isset($price['Taxes']) ? [
                'total' => [
                    'value' => (float)($price['Taxes']['Total']['value'] ?? 0),
                    'currency' => $price['Taxes']['Total']['Code'] ?? null
                ],
                'breakdown' => array_map(function ($tax) {
                    return [
                        'tax_code' => $tax['TaxCode'] ?? null,
                        'amount' => [
                            'value' => (float)($tax['Amount']['value'] ?? 0),
                            'currency' => $tax['Amount']['Code'] ?? null
                        ],
                        'description' => $tax['Description'] ?? null
                    ];
                }, $price['Taxes']['Breakdown']['Tax'] ?? [])
            ] : null,
            'fees' => isset($price['Fees']) ? [
                'total' => [
                    'value' => (float)($price['Fees']['Total']['value'] ?? 0),
                    'currency' => $price['Fees']['Total']['Code'] ?? null
                ],
                'breakdown' => array_map(function ($fee) {
                    return [
                        'amount' => [
                            'value' => (float)($fee['Amount']['value'] ?? 0),
                            'currency' => $fee['Amount']['Code'] ?? null
                        ],
                        'code' => $fee['FeeCode'] ?? null,
                        'name' => $fee['FeeName'] ?? null,
                        'owner' => $fee['FeeOwner'] ?? null,
                        'percentage' => (float)($fee['FeePercent'] ?? 0),
                        'refundable' => $fee['RefundInd'] ?? false
                    ];
                }, $price['Fees']['Breakdown']['Fee'] ?? [])
            ] : null
        ];
    }

    /**
     * Format monetary amount
     */
    protected function formatAmount(array $amount): ?array
    {
        if (empty($amount)) {
            return null;
        }

        return [
            'value' => (float)($amount['value'] ?? 0),
            'currency' => $amount['Code'] ?? null
        ];
    }

    /**
     * Format seat characteristics
     */
    protected function formatSeatCharacteristics(array $characteristics): array
    {
        if (empty($characteristics)) {
            return [];
        }

        return array_map(function ($char) {
            return [
                'code' => $char['Code'] ?? null,
                'remarks' => array_map(function ($remark) {
                    return $remark['value'] ?? null;
                }, $char['Remarks']['Remark'] ?? [])
            ];
        }, $characteristics['Characteristic'] ?? []);
    }

    /**
     * Parse ISO 8601 duration
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
     * Format date time
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
     * Extract fare type from remarks
     */
    protected function extractFareType(array $remarks): ?string
    {
        if (empty($remarks)) {
            return null;
        }

        foreach ($remarks['Remark'] ?? [] as $remark) {
            if (isset($remark['value'])) {
                return $remark['value'];
            }
        }

        return null;
    }

    /**
     * Determine baggage type from list key
     */
    protected function determineBaggageType(string $listKey): string
    {
        if (strpos($listKey, 'CKBAG') !== false) {
            return 'checked';
        }
        if (strpos($listKey, 'HANDBAG') !== false) {
            return 'carry_on';
        }
        return 'unknown';
    }

    /**
     * Initialize currency decimals from metadata
     */
    protected function initializeCurrencyDecimals(): void
    {
        $metadata = $this->extractCurrencyMetadata();
        foreach ($metadata as $currency => $data) {
            $this->currencyDecimals[$currency] = $data['decimals'];
        }
    }

    /**
     * Build media references mapping
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
     * Format media links
     */
    protected function formatMediaLinks(array $links): array
    {
        $formatted = [];
        foreach ($links as $link) {
            $formatted[$link['Size'] ?? 'default'] = [
                'url' => $link['Url'] ?? null,
                'type' => $link['Type'] ?? null,
                'size' => $link['Size'] ?? null
            ];
        }
        return $formatted;
    }

    protected function calculateResponseTime(): ?float
    {
        if (isset($this->data['Metadata']['ProcessingTime'])) {
            return (float) $this->data['Metadata']['ProcessingTime'];
        }
        return null;
    }
}
