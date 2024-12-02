<?php

namespace Santosdave\VerteilWrapper\Responses;

class FlightPriceResponse extends BaseResponse
{
    public function getPricedOffers(): array
    {
        $offers = [];
        
        if (isset($this->data['Response']['OffersGroup']['AirlineOffers'])) {
            foreach ($this->data['Response']['OffersGroup']['AirlineOffers'] as $airlineOffer) {
                $offers[] = [
                    'owner' => $airlineOffer['Owner'] ?? '',
                    'offerId' => $airlineOffer['OfferID']['value'] ?? '',
                    'items' => $this->extractOfferItems($airlineOffer['OfferItems'] ?? []),
                    'totalPrice' => $this->extractPrice($airlineOffer['TotalPrice'] ?? [])
                ];
            }
        }

        return $offers;
    }

    protected function extractOfferItems(array $items): array
    {
        return array_map(function($item) {
            return [
                'itemId' => $item['OfferItemID']['value'] ?? '',
                'passengerRefs' => $item['PassengerRefs'] ?? [],
                'services' => $this->extractServices($item['Services'] ?? []),
                'price' => $this->extractPrice($item['Price'] ?? []),
                'fareDetails' => $this->extractFareDetails($item['FareDetail'] ?? [])
            ];
        }, $items);
    }

    protected function extractServices(array $services): array
    {
        return array_map(function($service) {
            return [
                'serviceId' => $service['ServiceID']['value'] ?? '',
                'segmentRefs' => $service['SegmentRefs'] ?? [],
                'passengerRefs' => $service['PassengerRefs'] ?? [],
                'type' => $service['ServiceDefinitionRef']['ServiceDefinitionID'] ?? null
            ];
        }, $services);
    }

    protected function extractPrice(array $price): array
    {
        return [
            'total' => [
                'amount' => $price['TotalAmount']['value'] ?? 0.0,
                'currency' => $price['TotalAmount']['Code'] ?? ''
            ],
            'base' => [
                'amount' => $price['BaseAmount']['value'] ?? 0.0,
                'currency' => $price['BaseAmount']['Code'] ?? ''
            ],
            'taxes' => array_map(function($tax) {
                return [
                    'code' => $tax['TaxCode'] ?? '',
                    'amount' => $tax['Amount']['value'] ?? 0.0,
                    'currency' => $tax['Amount']['Code'] ?? ''
                ];
            }, $price['Taxes']['Tax'] ?? []),
            'fees' => array_map(function($fee) {
                return [
                    'code' => $fee['FeeCode'] ?? '',
                    'amount' => $fee['Amount']['value'] ?? 0.0,
                    'currency' => $fee['Amount']['Code'] ?? ''
                ];
            }, $price['Fees']['Fee'] ?? [])
        ];
    }

    protected function extractFareDetails(array $fareDetails): array
    {
        return array_map(function($detail) {
            return [
                'fareBasisCode' => $detail['FareBasisCode']['Code'] ?? '',
                'fareType' => $detail['FareTypeCode'] ?? '',
                'priceClassRef' => $detail['PriceClassRef'] ?? '',
                'conditions' => array_map(function($condition) {
                    return [
                        'type' => $condition['Type'] ?? '',
                        'value' => $condition['Value'] ?? '',
                        'description' => $condition['Description'] ?? null
                    ];
                }, $detail['FareRules']['FareRule'] ?? []),
                'baggageAllowance' => $this->extractBaggageAllowance($detail['BaggageAllowance'] ?? [])
            ];
        }, $fareDetails);
    }

    protected function extractBaggageAllowance(array $baggage): array
    {
        $allowance = [];

        if (isset($baggage['WeightAllowance'])) {
            $allowance['weight'] = [
                'value' => $baggage['WeightAllowance']['MaximumWeight']['Value'] ?? 0,
                'unit' => $baggage['WeightAllowance']['MaximumWeight']['UOM'] ?? 'KG'
            ];
        }

        if (isset($baggage['PieceAllowance'])) {
            $allowance['pieces'] = [
                'quantity' => $baggage['PieceAllowance']['TotalQuantity'] ?? 0,
                'type' => $baggage['PieceAllowance']['Type'] ?? null
            ];
        }

        return $allowance;
    }

    public function getDataLists(): array
    {
        return [
            'fareComponents' => $this->extractFareComponents(),
            'penaltyComponents' => $this->extractPenaltyComponents(),
            'serviceDefinitions' => $this->extractServiceDefinitions()
        ];
    }

    protected function extractFareComponents(): array
    {
        $components = [];
        
        if (isset($this->data['Response']['DataLists']['FareComponentList'])) {
            foreach ($this->data['Response']['DataLists']['FareComponentList'] as $component) {
                $components[] = [
                    'fareComponentId' => $component['FareComponentID']['value'] ?? '',
                    'priceClassRef' => $component['PriceClassRef'] ?? '',
                    'segmentRefs' => $component['SegmentRefs'] ?? [],
                    'fareAmount' => [
                        'amount' => $component['Price']['FareAmount']['value'] ?? 0.0,
                        'currency' => $component['Price']['FareAmount']['Code'] ?? ''
                    ]
                ];
            }
        }

        return $components;
    }

    protected function extractPenaltyComponents(): array
    {
        return isset($this->data['Response']['DataLists']['PenaltyList']) ? 
            array_map(function($penalty) {
                return [
                    'type' => $penalty['PenaltyType'] ?? '',
                    'amount' => [
                        'value' => $penalty['Amount']['value'] ?? 0.0,
                        'currency' => $penalty['Amount']['Code'] ?? ''
                    ],
                    'description' => $penalty['Description'] ?? null,
                    'applicability' => $penalty['PenaltyApplicability'] ?? null
                ];
            }, $this->data['Response']['DataLists']['PenaltyList']) : [];
    }

    protected function extractServiceDefinitions(): array
    {
        return isset($this->data['Response']['DataLists']['ServiceDefinitionList']) ?
            array_map(function($service) {
                return [
                    'id' => $service['ServiceDefinitionID']['value'] ?? '',
                    'code' => $service['ServiceCode']['Code'] ?? '',
                    'name' => $service['Name'] ?? '',
                    'description' => $service['Descriptions'][0]['Text'] ?? '',
                    'media' => isset($service['MediaObjects']) ? array_map(function($media) {
                        return [
                            'id' => $media['ID'] ?? '',
                            'url' => $media['URI'] ?? '',
                            'type' => $media['MediaType'] ?? ''
                        ];
                    }, $service['MediaObjects']) : []
                ];
            }, $this->data['Response']['DataLists']['ServiceDefinitionList']) : [];
    }

    public function getCorrelationId(): ?string
    {
        return $this->data['Response']['CorrelationID'] ?? null;
    }
}