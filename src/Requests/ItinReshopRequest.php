<?php

namespace Santosdave\VerteilWrapper\Requests;

use InvalidArgumentException;

class ItinReshopRequest extends BaseRequest
{
    /** @var array Order identification information */
    protected array $orderId;

    /** @var array Changed itinerary segments */
    protected array $itineraryChanges;

    /** @var array|null Pricing preferences and qualifiers */
    protected ?array $pricingQualifiers;

    /** @var array|null Party information for corporate bookings */
    protected ?array $party;

    /** @var array|null Metadata for response customization */
    protected ?array $metadata;

    public function __construct(
        array $orderId,
        array $itineraryChanges,
        ?array $pricingQualifiers = null,
        ?array $party = null,
        ?array $metadata = null,
        ?string $thirdPartyId = null,
        ?string $officeId = null
    ) {
        parent::__construct([
            'third_party_id' => $thirdPartyId,
            'office_id' => $officeId
        ]);

        $this->orderId = $orderId;
        $this->itineraryChanges = $itineraryChanges;
        $this->pricingQualifiers = $pricingQualifiers;
        $this->party = $party;
        $this->metadata = $metadata;
    }

    public function getEndpoint(): string
    {
        return '/entrygate/rest/request:itinReshop';
    }

    public function getHeaders(): array
    {
        return [
            'service' => 'ItinReshop',
            'ThirdpartyId' => $this->data['third_party_id'] ?? null,
            'OfficeId' => $this->data['office_id'] ?? null,
        ];
    }

    public function validate(): void
    {
        $this->validateOrderId();
        $this->validateItineraryChanges();

        if ($this->pricingQualifiers !== null) {
            $this->validatePricingQualifiers();
        }

        if ($this->party !== null) {
            $this->validateParty();
        }
    }

    protected function validateOrderId(): void
    {
        if (!isset($this->orderId['Owner']) || !isset($this->orderId['value'])) {
            throw new InvalidArgumentException('OrderID must contain Owner and value');
        }

        if (!preg_match('/^[A-Z]{2}$/', $this->orderId['Owner'])) {
            throw new InvalidArgumentException('Invalid airline code format');
        }

        if (!preg_match('/^[A-Z0-9]{4,8}$/', $this->orderId['value'])) {
            throw new InvalidArgumentException('Invalid booking reference format');
        }
    }

    protected function validateItineraryChanges(): void
    {
        if (empty($this->itineraryChanges)) {
            throw new InvalidArgumentException('At least one itinerary change is required');
        }

        foreach ($this->itineraryChanges as $change) {
            if (!isset($change['type'])) {
                throw new InvalidArgumentException('Change type is required');
            }

            switch ($change['type']) {
                case 'SEGMENT_CHANGE':
                    $this->validateSegmentChange($change);
                    break;
                case 'ROUTING_CHANGE':
                    $this->validateRoutingChange($change);
                    break;
                case 'DATE_CHANGE':
                    $this->validateDateChange($change);
                    break;
                default:
                    throw new InvalidArgumentException('Invalid change type');
            }
        }
    }

    protected function validateSegmentChange(array $change): void
    {
        if (!isset($change['oldSegment']) || !isset($change['newSegment'])) {
            throw new InvalidArgumentException('Both old and new segment details are required');
        }

        $this->validateSegmentDetails($change['oldSegment']);
        $this->validateSegmentDetails($change['newSegment']);
    }

    protected function validateRoutingChange(array $change): void
    {
        if (!isset($change['newRouting']) || empty($change['newRouting'])) {
            throw new InvalidArgumentException('New routing details are required');
        }

        foreach ($change['newRouting'] as $segment) {
            $this->validateSegmentDetails($segment);
        }
    }

    protected function validateDateChange(array $change): void
    {
        if (!isset($change['segmentRef']) || !isset($change['newDate'])) {
            throw new InvalidArgumentException('Segment reference and new date are required');
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $change['newDate'])) {
            throw new InvalidArgumentException('Invalid date format. Must be YYYY-MM-DD');
        }
    }

    protected function validateSegmentDetails(array $segment): void
    {
        $required = ['origin', 'destination', 'departure', 'airline', 'flightNumber'];
        foreach ($required as $field) {
            if (!isset($segment[$field])) {
                throw new InvalidArgumentException("Missing required segment field: $field");
            }
        }

        // Validate airport codes
        if (!preg_match('/^[A-Z]{3}$/', $segment['origin']) || 
            !preg_match('/^[A-Z]{3}$/', $segment['destination'])) {
            throw new InvalidArgumentException('Invalid airport code format');
        }

        // Validate airline code
        if (!preg_match('/^[A-Z]{2}$/', $segment['airline'])) {
            throw new InvalidArgumentException('Invalid airline code format');
        }

        // Validate flight number
        if (!preg_match('/^\d{1,4}[A-Z]?$/', $segment['flightNumber'])) {
            throw new InvalidArgumentException('Invalid flight number format');
        }
    }

    protected function validatePricingQualifiers(): void
    {
        foreach ($this->pricingQualifiers as $qualifier) {
            if (!isset($qualifier['type'])) {
                throw new InvalidArgumentException('Qualifier type is required');
            }

            switch ($qualifier['type']) {
                case 'FARE_BASIS':
                    if (!isset($qualifier['code'])) {
                        throw new InvalidArgumentException('Fare basis code is required');
                    }
                    break;
                
                case 'CABIN':
                    if (!isset($qualifier['code']) || 
                        !in_array($qualifier['code'], ['F', 'C', 'J', 'Y'])) {
                        throw new InvalidArgumentException('Invalid cabin code');
                    }
                    break;

                case 'BRAND':
                    if (!isset($qualifier['brandId'])) {
                        throw new InvalidArgumentException('Brand ID is required');
                    }
                    break;

                default:
                    throw new InvalidArgumentException('Invalid qualifier type');
            }
        }
    }

    protected function validateParty(): void
    {
        if (!isset($this->party['type']) || !isset($this->party['code'])) {
            throw new InvalidArgumentException('Party type and code are required');
        }

        if (!in_array($this->party['type'], ['CORPORATE', 'TOUR', 'AGENCY'])) {
            throw new InvalidArgumentException('Invalid party type');
        }
    }

    public function toArray(): array
    {
        $data = [
            'Query' => [
                'OrderID' => $this->orderId,
                'ItineraryChanges' => array_map([$this, 'formatItineraryChange'], $this->itineraryChanges)
            ]
        ];

        if ($this->pricingQualifiers !== null) {
            $data['Query']['PricingQualifiers'] = $this->formatPricingQualifiers();
        }

        if ($this->party !== null) {
            $data['Party'] = $this->formatParty();
        }

        if ($this->metadata !== null) {
            $data['Metadata'] = $this->metadata;
        }

        return $data;
    }

    protected function formatItineraryChange(array $change): array
    {
        switch ($change['type']) {
            case 'SEGMENT_CHANGE':
                return $this->formatSegmentChange($change);
            case 'ROUTING_CHANGE':
                return $this->formatRoutingChange($change);
            case 'DATE_CHANGE':
                return $this->formatDateChange($change);
            default:
                return [];
        }
    }

    protected function formatSegmentChange(array $change): array
    {
        return [
            'Type' => 'SEGMENT_CHANGE',
            'OldSegment' => $this->formatSegmentDetails($change['oldSegment']),
            'NewSegment' => $this->formatSegmentDetails($change['newSegment'])
        ];
    }

    protected function formatRoutingChange(array $change): array
    {
        return [
            'Type' => 'ROUTING_CHANGE',
            'NewRouting' => array_map([$this, 'formatSegmentDetails'], $change['newRouting'])
        ];
    }

    protected function formatDateChange(array $change): array
    {
        return [
            'Type' => 'DATE_CHANGE',
            'SegmentReference' => $change['segmentRef'],
            'NewDepartureDate' => $change['newDate'],
            'NewDepartureTime' => $change['newTime'] ?? null
        ];
    }

    protected function formatSegmentDetails(array $segment): array
    {
        return [
            'Departure' => [
                'AirportCode' => ['value' => $segment['origin']],
                'Date' => $segment['departure']['date'],
                'Time' => $segment['departure']['time'] ?? null
            ],
            'Arrival' => [
                'AirportCode' => ['value' => $segment['destination']],
                'Date' => $segment['arrival']['date'] ?? null,
                'Time' => $segment['arrival']['time'] ?? null
            ],
            'MarketingCarrier' => [
                'AirlineID' => ['value' => $segment['airline']],
                'FlightNumber' => ['value' => $segment['flightNumber']]
            ],
            'OperatingCarrier' => isset($segment['operatingCarrier']) ? [
                'AirlineID' => ['value' => $segment['operatingCarrier']['airline']],
                'FlightNumber' => ['value' => $segment['operatingCarrier']['flightNumber']]
            ] : null,
            'Equipment' => isset($segment['aircraft']) ? [
                'AircraftCode' => $segment['aircraft']
            ] : null
        ];
    }

    protected function formatPricingQualifiers(): array
    {
        return array_map(function($qualifier) {
            switch ($qualifier['type']) {
                case 'FARE_BASIS':
                    return [
                        'FareBasisCode' => [
                            'Code' => $qualifier['code']
                        ]
                    ];
                
                case 'CABIN':
                    return [
                        'CabinType' => [
                            'Code' => $qualifier['code']
                        ]
                    ];

                case 'BRAND':
                    return [
                        'BrandID' => [
                            'value' => $qualifier['brandId']
                        ]
                    ];

                default:
                    return [];
            }
        }, $this->pricingQualifiers);
    }

    protected function formatParty(): array
    {
        return [
            'Sender' => [
                $this->party['type'] . 'Sender' => [
                    'Code' => $this->party['code'],
                    'Name' => $this->party['name'] ?? null,
                    'IATA' => isset($this->party['iata']) ? [
                        'value' => $this->party['iata']
                    ] : null
                ]
            ]
        ];
    }
}