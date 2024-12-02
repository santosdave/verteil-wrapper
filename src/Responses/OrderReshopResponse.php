<?php

namespace Santosdave\VerteilWrapper\Responses;

use Santosdave\VerteilWrapper\Responses\BaseResponse;

class OrderReshopResponse extends BaseResponse
{
    public function getResponseId(): string
    {
        return $this->data['Response']['ReshopResults']['ResponseID']['value'] ?? '';
    }

    public function getReshopOffers(): array
    {
        $offers = [];

        if (isset($this->data['Response']['ReshopResults']['Offers'])) {
            foreach ($this->data['Response']['ReshopResults']['Offers'] as $offer) {
                $offers[] = [
                    'offerId' => $offer['OfferID']['value'] ?? '',
                    'owner' => $offer['OfferID']['Owner'] ?? '',
                    'items' => $this->extractOfferItems($offer['OfferItems'] ?? []),
                    'price' => $this->extractPrice($offer['TotalPrice'] ?? []),
                    'priceDifference' => $this->extractPrice($offer['PriceDifference'] ?? []),
                    'penalties' => $this->extractPenalties($offer['Penalties'] ?? [])
                ];
            }
        }

        return $offers;
    }

    protected function extractOfferItems(array $items): array
    {
        $offerItems = [];

        foreach ($items as $item) {
            $offerItems[] = [
                'itemId' => $item['OfferItemID']['value'] ?? '',
                'type' => $item['OfferItemType'] ?? '',
                'flights' => $this->extractFlights($item['FlightItem'] ?? []),
                'services' => $this->extractServices($item['ServiceItem'] ?? [])
            ];
        }

        return $offerItems;
    }

    protected function extractFlights(array $flightItem): array
    {
        $flights = [];

        if (!empty($flightItem)) {
            foreach ($flightItem as $flight) {
                $flights[] = [
                    'segmentKey' => $flight['SegmentKey'] ?? '',
                    'departure' => [
                        'airport' => $flight['Departure']['AirportCode']['value'] ?? '',
                        'date' => $flight['Departure']['Date'] ?? '',
                        'time' => $flight['Departure']['Time'] ?? '',
                        'terminal' => $flight['Departure']['Terminal']['Name'] ?? null
                    ],
                    'arrival' => [
                        'airport' => $flight['Arrival']['AirportCode']['value'] ?? '',
                        'date' => $flight['Arrival']['Date'] ?? '',
                        'time' => $flight['Arrival']['Time'] ?? '',
                        'terminal' => $flight['Arrival']['Terminal']['Name'] ?? null
                    ],
                    'marketing' => [
                        'airline' => $flight['MarketingCarrier']['AirlineID']['value'] ?? '',
                        'flightNumber' => $flight['MarketingCarrier']['FlightNumber']['value'] ?? ''
                    ],
                    'operating' => isset($flight['OperatingCarrier']) ? [
                        'airline' => $flight['OperatingCarrier']['AirlineID']['value'] ?? '',
                        'flightNumber' => $flight['OperatingCarrier']['FlightNumber']['value'] ?? ''
                    ] : null,
                    'equipment' => $flight['Equipment']['AircraftCode'] ?? null,
                    'duration' => $flight['JourneyDuration'] ?? null
                ];
            }
        }

        return $flights;
    }

    protected function extractServices(array $serviceItem): array
    {
        $services = [];

        if (!empty($serviceItem)) {
            foreach ($serviceItem as $service) {
                $services[] = [
                    'serviceId' => $service['ServiceID']['value'] ?? '',
                    'code' => $service['ServiceCode']['Code'] ?? '',
                    'name' => $service['Name'] ?? '',
                    'description' => $service['Descriptions'][0]['Text'] ?? '',
                    'price' => $this->extractPrice($service['Price'] ?? [])
                ];
            }
        }

        return $services;
    }

    protected function extractPrice(array $price): ?array
    {
        if (empty($price)) {
            return null;
        }

        return [
            'amount' => $price['TotalAmount']['value'] ?? 0.0,
            'currency' => $price['TotalAmount']['Code'] ?? '',
            'baseAmount' => $price['BaseAmount']['value'] ?? 0.0,
            'taxes' => array_map(function ($tax) {
                return [
                    'code' => $tax['TaxCode'] ?? '',
                    'amount' => $tax['Amount']['value'] ?? 0.0,
                    'currency' => $tax['Amount']['Code'] ?? ''
                ];
            }, $price['Taxes']['Tax'] ?? [])
        ];
    }

    protected function extractPenalties(array $penalties): array
    {
        return array_map(function ($penalty) {
            return [
                'type' => $penalty['PenaltyType'] ?? '',
                'amount' => $penalty['Amount']['value'] ?? 0.0,
                'currency' => $penalty['Amount']['Code'] ?? '',
                'description' => $penalty['Description'] ?? ''
            ];
        }, $penalties);
    }

    public function getDataLists(): array
    {
        $dataLists = [];

        if (isset($this->data['Response']['DataLists'])) {
            $dataLists = [
                'segments' => $this->extractSegmentList($this->data['Response']['DataLists']['FlightSegmentList'] ?? []),
                'baggage' => $this->extractBaggageAllowances($this->data['Response']['DataLists']['BaggageAllowanceList'] ?? []),
                'services' => $this->extractServiceDefinitions($this->data['Response']['DataLists']['ServiceDefinitionList'] ?? [])
            ];
        }

        return $dataLists;
    }

    protected function extractSegmentList(array $segmentList): array
    {
        return array_map(function ($segment) {
            return [
                'segmentKey' => $segment['SegmentKey'] ?? '',
                'departure' => [
                    'airport' => $segment['Departure']['AirportCode']['value'] ?? '',
                    'terminal' => $segment['Departure']['Terminal']['Name'] ?? null,
                    'date' => $segment['Departure']['Date'] ?? '',
                    'time' => $segment['Departure']['Time'] ?? ''
                ],
                'arrival' => [
                    'airport' => $segment['Arrival']['AirportCode']['value'] ?? '',
                    'terminal' => $segment['Arrival']['Terminal']['Name'] ?? null,
                    'date' => $segment['Arrival']['Date'] ?? '',
                    'time' => $segment['Arrival']['Time'] ?? ''
                ],
                'marketing' => [
                    'airline' => $segment['MarketingCarrier']['AirlineID']['value'] ?? '',
                    'flightNumber' => $segment['MarketingCarrier']['FlightNumber']['value'] ?? ''
                ],
                'operating' => isset($segment['OperatingCarrier']) ? [
                    'airline' => $segment['OperatingCarrier']['AirlineID']['value'] ?? '',
                    'flightNumber' => $segment['OperatingCarrier']['FlightNumber']['value'] ?? ''
                ] : null,
                'equipment' => $segment['Equipment']['AircraftCode'] ?? null,
                'duration' => $segment['JourneyDuration'] ?? null,
                'cabinType' => $segment['CabinType']['Code'] ?? null
            ];
        }, $segmentList['FlightSegment'] ?? []);
    }

    protected function extractBaggageAllowances(array $baggageList): array
    {
        return array_map(function ($baggage) {
            return [
                'id' => $baggage['BaggageAllowanceID']['value'] ?? '',
                'type' => $baggage['TypeCode'] ?? '',
                'weight' => isset($baggage['WeightAllowance']) ? [
                    'value' => $baggage['WeightAllowance']['MaximumWeight']['Value'] ?? 0,
                    'unit' => $baggage['WeightAllowance']['MaximumWeight']['UOM'] ?? ''
                ] : null,
                'pieces' => isset($baggage['PieceAllowance']) ?
                    $baggage['PieceAllowance']['TotalQuantity'] : null
            ];
        }, $baggageList['BaggageAllowance'] ?? []);
    }

    protected function extractServiceDefinitions(array $serviceList): array
    {
        return array_map(function ($service) {
            return [
                'id' => $service['ServiceDefinitionID']['value'] ?? '',
                'code' => $service['ServiceCode']['Code'] ?? '',
                'name' => $service['Name'] ?? '',
                'description' => $service['Descriptions'][0]['Text'] ?? '',
                'media' => isset($service['MediaObjects']) ? array_map(function ($media) {
                    return [
                        'id' => $media['ID'] ?? '',
                        'url' => $media['URI'] ?? '',
                        'type' => $media['MediaType'] ?? ''
                    ];
                }, $service['MediaObjects']) : []
            ];
        }, $serviceList['ServiceDefinition'] ?? []);
    }

    public function getAlternateDateOptions(): array
    {
        $options = [];

        if (isset($this->data['Response']['AlternateDateOptions'])) {
            foreach ($this->data['Response']['AlternateDateOptions'] as $option) {
                $options[] = [
                    'date' => $option['Date'] ?? '',
                    'price' => $this->extractPrice($option['Price'] ?? []),
                    'availability' => $option['AvailabilityStatus'] ?? ''
                ];
            }
        }

        return $options;
    }

    public function getWarnings(): array
    {
        return isset($this->data['Response']['Warnings']) ?
            array_map(function ($warning) {
                return [
                    'code' => $warning['Code'] ?? '',
                    'type' => $warning['Type'] ?? '',
                    'description' => $warning['Description'] ?? ''
                ];
            }, $this->data['Response']['Warnings']) : [];
    }

    public function getCorrelationId(): ?string
    {
        return $this->data['Response']['CorrelationID'] ?? null;
    }

    public function getExpirationDateTime(): ?string
    {
        return $this->data['Response']['ReshopResults']['ExpirationDateTime'] ?? null;
    }
}
