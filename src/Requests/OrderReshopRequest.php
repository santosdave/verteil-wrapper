<?php

namespace Santosdave\VerteilWrapper\Requests;

use InvalidArgumentException;

class OrderReshopRequest extends BaseRequest
{
    /** @var array Order identification information */
    protected array $orderId;

    /** @var array|null Reshop qualifiers and filters */
    protected ?array $qualifiers;

    /** @var array|null Specific segments to reshop */
    protected ?array $segments;

    /** @var array|null Passenger references for partial reshop */
    protected ?array $passengerRefs;

    /** @var bool|null Flag to indicate if alternative dates should be searched */
    protected ?bool $searchAlternateDates;

    public function __construct(
        array $orderId,
        ?array $qualifiers = null,
        ?array $segments = null,
        ?array $passengerRefs = null,
        ?bool $searchAlternateDates = null,
        ?string $thirdPartyId = null,
        ?string $officeId = null
    ) {
        parent::__construct([
            'third_party_id' => $thirdPartyId,
            'office_id' => $officeId
        ]);

        $this->orderId = $orderId;
        $this->qualifiers = $qualifiers;
        $this->segments = $segments;
        $this->passengerRefs = $passengerRefs;
        $this->searchAlternateDates = $searchAlternateDates;
    }

    public function getEndpoint(): string
    {
        return '/entrygate/rest/request:orderReshop';
    }

    public function getHeaders(): array
    {
        return [
            'service' => 'OrderReshop',
            'ThirdpartyId' => $this->data['third_party_id'] ?? null,
            'OfficeId' => $this->data['office_id'] ?? null,
        ];
    }

    public function validate(): void
    {
        $this->validateOrderId();
        
        if ($this->qualifiers !== null) {
            $this->validateQualifiers();
        }

        if ($this->segments !== null) {
            $this->validateSegments();
        }

        if ($this->passengerRefs !== null) {
            $this->validatePassengerRefs();
        }
    }

    protected function validateOrderId(): void
    {
        if (!isset($this->orderId['Owner']) || !isset($this->orderId['value'])) {
            throw new InvalidArgumentException('OrderID must contain Owner and value');
        }

        if (!preg_match('/^[A-Z]{2}$/', $this->orderId['Owner'])) {
            throw new InvalidArgumentException('Invalid airline code format in OrderID Owner');
        }

        if (!preg_match('/^[A-Z0-9]{4,8}$/', $this->orderId['value'])) {
            throw new InvalidArgumentException('Invalid PNR format in OrderID value');
        }
    }

    protected function validateQualifiers(): void
    {
        foreach ($this->qualifiers as $qualifier) {
            if (!isset($qualifier['type'])) {
                throw new InvalidArgumentException('Qualifier type is required');
            }

            switch ($qualifier['type']) {
                case 'CABIN':
                    $this->validateCabinQualifier($qualifier);
                    break;
                case 'FARE':
                    $this->validateFareQualifier($qualifier);
                    break;
                case 'SERVICE':
                    $this->validateServiceQualifier($qualifier);
                    break;
                default:
                    throw new InvalidArgumentException('Invalid qualifier type');
            }
        }
    }

    protected function validateCabinQualifier(array $qualifier): void
    {
        if (!isset($qualifier['cabin'])) {
            throw new InvalidArgumentException('Cabin preference is required');
        }

        $validCabins = ['F', 'C', 'J', 'S', 'Y', 'M'];
        if (!in_array($qualifier['cabin'], $validCabins)) {
            throw new InvalidArgumentException('Invalid cabin code');
        }
    }

    protected function validateFareQualifier(array $qualifier): void
    {
        if (!isset($qualifier['fareBasis'])) {
            throw new InvalidArgumentException('Fare basis code is required');
        }
    }

    protected function validateServiceQualifier(array $qualifier): void
    {
        if (!isset($qualifier['serviceCode'])) {
            throw new InvalidArgumentException('Service code is required');
        }
    }

    protected function validateSegments(): void
    {
        foreach ($this->segments as $segment) {
            if (!isset($segment['segmentKey'])) {
                throw new InvalidArgumentException('Segment key is required');
            }

            if (isset($segment['newFlight'])) {
                $this->validateFlightDetails($segment['newFlight']);
            }
        }
    }

    protected function validateFlightDetails(array $flight): void
    {
        $required = ['origin', 'destination', 'departureDate', 'airlineCode', 'flightNumber'];
        foreach ($required as $field) {
            if (!isset($flight[$field])) {
                throw new InvalidArgumentException("Missing required flight field: $field");
            }
        }
    }

    protected function validatePassengerRefs(): void
    {
        if (empty($this->passengerRefs)) {
            throw new InvalidArgumentException('At least one passenger reference is required');
        }

        foreach ($this->passengerRefs as $ref) {
            if (!isset($ref['value'])) {
                throw new InvalidArgumentException('Invalid passenger reference format');
            }
        }
    }

    public function toArray(): array
    {
        $data = [
            'Query' => [
                'OrderID' => $this->orderId
            ]
        ];

        if ($this->qualifiers !== null) {
            $data['Query']['Qualifiers'] = $this->formatQualifiers();
        }

        if ($this->segments !== null) {
            $data['Query']['Segments'] = $this->formatSegments();
        }

        if ($this->passengerRefs !== null) {
            $data['Query']['PassengerRefs'] = $this->formatPassengerRefs();
        }

        if ($this->searchAlternateDates !== null) {
            $data['Query']['SearchAlternateDates'] = $this->searchAlternateDates;
        }

        return $data;
    }

    protected function formatQualifiers(): array
    {
        return array_map(function($qualifier) {
            switch ($qualifier['type']) {
                case 'CABIN':
                    return [
                        'CabinPreference' => [
                            'CabinType' => [
                                'Code' => $qualifier['cabin']
                            ]
                        ]
                    ];
                case 'FARE':
                    return [
                        'FarePreference' => [
                            'FareBasisCode' => [
                                'Code' => $qualifier['fareBasis']
                            ],
                            'PreferenceLevel' => $qualifier['preferenceLevel'] ?? null
                        ]
                    ];
                case 'SERVICE':
                    return [
                        'ServicePreference' => [
                            'ServiceCode' => $qualifier['serviceCode'],
                            'ServiceDefinitionID' => $qualifier['serviceDefinitionId'] ?? null
                        ]
                    ];
                default:
                    return [];
            }
        }, $this->qualifiers);
    }

    protected function formatSegments(): array
    {
        return array_map(function($segment) {
            $formattedSegment = [
                'SegmentKey' => $segment['segmentKey']
            ];

            if (isset($segment['newFlight'])) {
                $formattedSegment['NewFlight'] = [
                    'Departure' => [
                        'AirportCode' => ['value' => $segment['newFlight']['origin']],
                        'Date' => $segment['newFlight']['departureDate'],
                        'Time' => $segment['newFlight']['departureTime'] ?? null
                    ],
                    'Arrival' => [
                        'AirportCode' => ['value' => $segment['newFlight']['destination']],
                        'Date' => $segment['newFlight']['arrivalDate'] ?? null,
                        'Time' => $segment['newFlight']['arrivalTime'] ?? null
                    ],
                    'MarketingCarrier' => [
                        'AirlineID' => ['value' => $segment['newFlight']['airlineCode']],
                        'FlightNumber' => ['value' => $segment['newFlight']['flightNumber']]
                    ]
                ];
            }

            return $formattedSegment;
        }, $this->segments);
    }

    protected function formatPassengerRefs(): array
    {
        return array_map(function($ref) {
            return ['value' => $ref['value']];
        }, $this->passengerRefs);
    }
}