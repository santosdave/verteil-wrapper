<?php

namespace Santosdave\VerteilWrapper\Responses;

class OrderChangeResponse extends BaseResponse
{
    public function getOrderId(): string
    {
        return $this->data['Response']['Order'][0]['OrderID']['value'] ?? '';
    }

    public function getStatus(): string
    {
        return $this->data['Response']['Order'][0]['OrderStatus'] ?? '';
    }

    public function getChangeFees(): array
    {
        $fees = [];
        
        if (isset($this->data['Response']['Order'][0]['ChangeFees'])) {
            foreach ($this->data['Response']['Order'][0]['ChangeFees'] as $fee) {
                $fees[] = [
                    'amount' => $fee['Amount']['value'] ?? 0.0,
                    'currency' => $fee['Amount']['Code'] ?? '',
                    'type' => $fee['Type'] ?? '',
                    'description' => $fee['Description'] ?? ''
                ];
            }
        }

        return $fees;
    }

    public function getTotalPrice(): float
    {
        return $this->data['Response']['Order'][0]['TotalOrderPrice']['SimpleCurrencyPrice']['value'] ?? 0.0;
    }

    public function getCurrency(): string
    {
        return $this->data['Response']['Order'][0]['TotalOrderPrice']['SimpleCurrencyPrice']['Code'] ?? '';
    }

    public function getModifiedSegments(): array
    {
        $segments = [];

        if (isset($this->data['Response']['Order'][0]['OrderItems'])) {
            foreach ($this->data['Response']['Order'][0]['OrderItems'] as $item) {
                if (isset($item['FlightItem'])) {
                    $segments[] = [
                        'segmentKey' => $item['FlightItem']['SegmentKey'] ?? '',
                        'status' => $item['FlightItem']['Status'] ?? '',
                        'departure' => [
                            'airport' => $item['FlightItem']['Departure']['AirportCode']['value'] ?? '',
                            'date' => $item['FlightItem']['Departure']['Date'] ?? '',
                            'time' => $item['FlightItem']['Departure']['Time'] ?? '',
                        ],
                        'arrival' => [
                            'airport' => $item['FlightItem']['Arrival']['AirportCode']['value'] ?? '',
                            'date' => $item['FlightItem']['Arrival']['Date'] ?? '',
                            'time' => $item['FlightItem']['Arrival']['Time'] ?? '',
                        ]
                    ];
                }
            }
        }

        return $segments;
    }

    public function getAddedServices(): array
    {
        $services = [];

        if (isset($this->data['Response']['Order'][0]['OrderItems'])) {
            foreach ($this->data['Response']['Order'][0]['OrderItems'] as $item) {
                if (isset($item['ServiceItem'])) {
                    $services[] = [
                        'serviceId' => $item['ServiceItem']['ServiceID'] ?? '',
                        'status' => $item['ServiceItem']['Status'] ?? '',
                        'description' => $item['ServiceItem']['ServiceDescription'] ?? '',
                        'price' => [
                            'amount' => $item['ServiceItem']['Price']['Amount']['value'] ?? 0.0,
                            'currency' => $item['ServiceItem']['Price']['Amount']['Code'] ?? ''
                        ]
                    ];
                }
            }
        }

        return $services;
    }

    public function getUpdatedPassengers(): array
    {
        $passengers = [];

        if (isset($this->data['Response']['DataLists']['PassengerList'])) {
            foreach ($this->data['Response']['DataLists']['PassengerList'] as $passenger) {
                $passengers[] = [
                    'reference' => $passenger['PassengerReference'] ?? '',
                    'type' => $passenger['PassengerType'] ?? '',
                    'name' => [
                        'given' => $passenger['Name']['Given'] ?? '',
                        'surname' => $passenger['Name']['Surname'] ?? ''
                    ],
                    'contact' => isset($passenger['Contact']) ? [
                        'email' => $passenger['Contact']['EmailAddress']['value'] ?? '',
                        'phone' => $passenger['Contact']['Phone']['Number'] ?? ''
                    ] : null,
                    'documents' => isset($passenger['Documents']) ? array_map(function($doc) {
                        return [
                            'type' => $doc['Type'] ?? '',
                            'number' => $doc['Number'] ?? '',
                            'issuingCountry' => $doc['IssuingCountry'] ?? '',
                            'expiryDate' => $doc['ExpiryDate'] ?? ''
                        ];
                    }, $passenger['Documents']) : []
                ];
            }
        }

        return $passengers;
    }

    public function getWarnings(): array
    {
        return isset($this->data['Response']['Warnings']) 
            ? array_map(function($warning) {
                return [
                    'code' => $warning['Code'] ?? '',
                    'message' => $warning['Message'] ?? '',
                    'type' => $warning['Type'] ?? ''
                ];
            }, $this->data['Response']['Warnings'])
            : [];
    }

    public function getTicketingDeadline(): ?string
    {
        return $this->data['Response']['Order'][0]['TicketingDeadline'] ?? null;
    }

    public function getPnrLocator(): ?string
    {
        if (isset($this->data['Response']['Order'][0]['BookingReferences'])) {
            foreach ($this->data['Response']['Order'][0]['BookingReferences'] as $ref) {
                if ($ref['Type'] === 'PNR') {
                    return $ref['ID'] ?? null;
                }
            }
        }
        return null;
    }
}