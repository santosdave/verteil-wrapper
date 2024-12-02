<?php

namespace Santosdave\VerteilWrapper\Responses;

use Santosdave\VerteilWrapper\Responses\BaseResponse;

class ItinReshopResponse extends BaseResponse
{
    public function getCorrelationId(): string
    {
        return $this->data['Response']['CorrelationID'] ?? '';
    }

    public function getReshopResults(): array
    {
        $results = [];

        if (isset($this->data['Response']['ReshopResults'])) {
            $results = [
                'responseId' => $this->data['Response']['ReshopResults']['ResponseID']['value'] ?? '',
                'options' => $this->extractReshopOptions(),
                'penalties' => $this->extractPenalties(),
                'expirationDateTime' => $this->data['Response']['ReshopResults']['ExpirationDateTime'] ?? null
            ];
        }

        return $results;
    }

    protected function extractReshopOptions(): array
    {
        $options = [];

        if (isset($this->data['Response']['ReshopResults']['ReshopOptions'])) {
            foreach ($this->data['Response']['ReshopResults']['ReshopOptions'] as $option) {
                $options[] = [
                    'optionId' => $option['OptionID']['value'] ?? '',
                    'segments' => $this->extractSegments($option['Segments'] ?? []),
                    'pricing' => [
                        'original' => $this->extractPrice($option['OriginalPrice'] ?? []),
                        'new' => $this->extractPrice($option['NewPrice'] ?? []),
                        'difference' => $this->extractPrice($option['PriceDifference'] ?? []),
                        'changeFees' => $this->extractChangeFees($option['ChangeFees'] ?? [])
                    ],
                    'availability' => [
                        'status' => $option['AvailabilityStatus'] ?? '',
                        'seats' => $option['AvailableSeats'] ?? null
                    ],
                    'fareBasis' => $this->extractFareBasis($option['FareBasis'] ?? []),
                    'validatingCarrier' => $option['ValidatingCarrier']['AirlineID']['value'] ?? ''
                ];
            }
        }

        return $options;
    }

    protected function extractSegments(array $segments): array
    {
        return array_map(function ($segment) {
            return [
                'segmentKey' => $segment['SegmentKey'] ?? '',
                'status' => $segment['Status'] ?? '',
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
                'equipment' => [
                    'aircraftCode' => $segment['Equipment']['AircraftCode'] ?? null,
                    'aircraftName' => $segment['Equipment']['Name'] ?? null
                ],
                'cabinType' => [
                    'code' => $segment['CabinType']['Code'] ?? null,
                    'name' => $segment['CabinType']['Definition'] ?? null
                ],
                'duration' => $segment['JourneyDuration'] ?? null,
                'stops' => $this->extractStops($segment['Stops'] ?? []),
                'baggage' => $this->extractBaggageAllowance($segment['BaggageAllowance'] ?? [])
            ];
        }, $segments);
    }

    protected function extractStops(array $stops): array
    {
        return array_map(function ($stop) {
            return [
                'airport' => $stop['AirportCode']['value'] ?? '',
                'duration' => $stop['Duration'] ?? null,
                'arrivalTime' => $stop['ArrivalTime'] ?? null,
                'departureTime' => $stop['DepartureTime'] ?? null
            ];
        }, $stops);
    }

    protected function extractBaggageAllowance(array $baggage): array
    {
        return [
            'quantity' => $baggage['Quantity'] ?? null,
            'weight' => isset($baggage['Weight']) ? [
                'value' => $baggage['Weight']['Value'] ?? null,
                'unit' => $baggage['Weight']['Unit'] ?? null
            ] : null,
            'type' => $baggage['Type'] ?? null
        ];
    }

    protected function extractPrice(array $price): array
    {
        return [
            'totalAmount' => [
                'value' => $price['TotalAmount']['value'] ?? 0.0,
                'currency' => $price['TotalAmount']['Code'] ?? ''
            ],
            'baseAmount' => [
                'value' => $price['BaseAmount']['value'] ?? 0.0,
                'currency' => $price['BaseAmount']['Code'] ?? ''
            ],
            'taxes' => array_map(function ($tax) {
                return [
                    'code' => $tax['TaxCode'] ?? '',
                    'amount' => [
                        'value' => $tax['Amount']['value'] ?? 0.0,
                        'currency' => $tax['Amount']['Code'] ?? ''
                    ],
                    'description' => $tax['Description'] ?? null
                ];
            }, $price['Taxes']['Tax'] ?? []),
            'fees' => array_map(function ($fee) {
                return [
                    'code' => $fee['FeeCode'] ?? '',
                    'amount' => [
                        'value' => $fee['Amount']['value'] ?? 0.0,
                        'currency' => $fee['Amount']['Code'] ?? ''
                    ],
                    'description' => $fee['Description'] ?? null
                ];
            }, $price['Fees']['Fee'] ?? [])
        ];
    }

    protected function extractChangeFees(array $fees): array
    {
        return array_map(function ($fee) {
            return [
                'type' => $fee['Type'] ?? '',
                'amount' => [
                    'value' => $fee['Amount']['value'] ?? 0.0,
                    'currency' => $fee['Amount']['Code'] ?? ''
                ],
                'description' => $fee['Description'] ?? null,
                'applicability' => $fee['Applicability'] ?? null,
                'restrictions' => $this->extractRestrictions($fee['Restrictions'] ?? [])
            ];
        }, $fees);
    }

    protected function extractRestrictions(array $restrictions): array
    {
        return array_map(function ($restriction) {
            return [
                'type' => $restriction['Type'] ?? '',
                'description' => $restriction['Description'] ?? '',
                'applicability' => $restriction['Applicability'] ?? null
            ];
        }, $restrictions);
    }

    protected function extractFareBasis(array $fareBasis): array
    {
        return [
            'code' => $fareBasis['Code'] ?? '',
            'fareType' => $fareBasis['FareType'] ?? null,
            'publicFare' => $fareBasis['PublicFare'] ?? true,
            'rules' => array_map(function ($rule) {
                return [
                    'type' => $rule['Type'] ?? '',
                    'description' => $rule['Description'] ?? '',
                    'details' => $rule['Details'] ?? null
                ];
            }, $fareBasis['Rules']['Rule'] ?? [])
        ];
    }

    protected function extractPenalties(): array
    {
        $penalties = [];

        if (isset($this->data['Response']['ReshopResults']['Penalties'])) {
            foreach ($this->data['Response']['ReshopResults']['Penalties'] as $penalty) {
                $penalties[] = [
                    'type' => $penalty['Type'] ?? '',
                    'applicationType' => $penalty['ApplicationType'] ?? '',
                    'amount' => [
                        'value' => $penalty['Amount']['value'] ?? 0.0,
                        'currency' => $penalty['Amount']['Code'] ?? ''
                    ],
                    'description' => $penalty['Description'] ?? null,
                    'restrictions' => $this->extractRestrictions($penalty['Restrictions'] ?? [])
                ];
            }
        }

        return $penalties;
    }

    public function getWarnings(): array
    {
        return isset($this->data['Response']['Warnings']) ?
            array_map(function ($warning) {
                return [
                    'code' => $warning['Code'] ?? '',
                    'type' => $warning['Type'] ?? '',
                    'description' => $warning['Description'] ?? '',
                    'severity' => $warning['Severity'] ?? 'Info'
                ];
            }, $this->data['Response']['Warnings']) : [];
    }

    public function getDataLists(): array
    {
        return [
            'airports' => $this->extractAirports(),
            'airlines' => $this->extractAirlines(),
            'equipment' => $this->extractEquipment()
        ];
    }

    protected function extractAirports(): array
    {
        return isset($this->data['Response']['DataLists']['AirportList']) ?
            array_map(function ($airport) {
                return [
                    'code' => $airport['AirportCode']['value'] ?? '',
                    'name' => $airport['Name'] ?? '',
                    'cityCode' => $airport['CityCode'] ?? null,
                    'countryCode' => $airport['CountryCode'] ?? null,
                    'terminal' => $airport['Terminal'] ?? null
                ];
            }, $this->data['Response']['DataLists']['AirportList']) : [];
    }

    protected function extractAirlines(): array
    {
        return isset($this->data['Response']['DataLists']['CarrierList']) ?
            array_map(function ($carrier) {
                return [
                    'code' => $carrier['AirlineID']['value'] ?? '',
                    'name' => $carrier['Name'] ?? '',
                    'alliance' => $carrier['Alliance'] ?? null
                ];
            }, $this->data['Response']['DataLists']['CarrierList']) : [];
    }

    protected function extractEquipment(): array
    {
        return isset($this->data['Response']['DataLists']['EquipmentList']) ?
            array_map(function ($equipment) {
                return [
                    'code' => $equipment['AircraftCode'] ?? '',
                    'name' => $equipment['Name'] ?? '',
                    'type' => $equipment['AircraftType'] ?? null
                ];
            }, $this->data['Response']['DataLists']['EquipmentList']) : [];
    }
}
