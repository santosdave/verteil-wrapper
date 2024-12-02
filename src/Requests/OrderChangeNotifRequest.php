<?php

namespace Santosdave\VerteilWrapper\Requests;

use InvalidArgumentException;

class OrderChangeNotifRequest extends BaseRequest
{
    /** @var array Order identification information */
    protected array $orderId;

    /** @var array Change notification details */
    protected array $notification;

    /** @var array|null Related service impact details */
    protected ?array $serviceImpact;

    /** @var array|null Alternative options offered */
    protected ?array $alternatives;

    public function __construct(
        array $orderId,
        array $notification,
        ?array $serviceImpact = null,
        ?array $alternatives = null,
        ?string $thirdPartyId = null,
        ?string $officeId = null
    ) {
        parent::__construct([
            'third_party_id' => $thirdPartyId,
            'office_id' => $officeId
        ]);

        $this->orderId = $orderId;
        $this->notification = $notification;
        $this->serviceImpact = $serviceImpact;
        $this->alternatives = $alternatives;
    }

    public function getEndpoint(): string
    {
        return '/entrygate/rest/request:orderChangeNotif';
    }

    public function getHeaders(): array
    {
        return [
            'service' => 'OrderChangeNotif',
            'ThirdpartyId' => $this->data['third_party_id'] ?? null,
            'OfficeId' => $this->data['office_id'] ?? null,
        ];
    }

    public function validate(): void
    {
        $this->validateOrderId();
        $this->validateNotification();

        if ($this->serviceImpact !== null) {
            $this->validateServiceImpact();
        }

        if ($this->alternatives !== null) {
            $this->validateAlternatives();
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

    protected function validateNotification(): void
    {
        if (!isset($this->notification['type']) || !isset($this->notification['reason'])) {
            throw new InvalidArgumentException('Notification must contain type and reason');
        }

        $validTypes = ['SCHEDULE_CHANGE', 'FLIGHT_CANCEL', 'ROUTE_CHANGE', 'AIRCRAFT_CHANGE'];
        if (!in_array($this->notification['type'], $validTypes)) {
            throw new InvalidArgumentException('Invalid notification type');
        }

        if (isset($this->notification['severity'])) {
            $validSeverity = ['INFO', 'WARNING', 'CRITICAL'];
            if (!in_array($this->notification['severity'], $validSeverity)) {
                throw new InvalidArgumentException('Invalid severity level');
            }
        }

        if (isset($this->notification['affectedSegments'])) {
            foreach ($this->notification['affectedSegments'] as $segment) {
                if (!isset($segment['segmentRef'])) {
                    throw new InvalidArgumentException('Affected segments must contain segment reference');
                }
            }
        }
    }

    protected function validateServiceImpact(): void
    {
        foreach ($this->serviceImpact as $impact) {
            if (!isset($impact['serviceType']) || !isset($impact['status'])) {
                throw new InvalidArgumentException('Service impact must contain serviceType and status');
            }

            $validStatus = ['AFFECTED', 'CANCELLED', 'MODIFIED'];
            if (!in_array($impact['status'], $validStatus)) {
                throw new InvalidArgumentException('Invalid service impact status');
            }
        }
    }

    protected function validateAlternatives(): void
    {
        foreach ($this->alternatives as $alternative) {
            if (!isset($alternative['type']) || !isset($alternative['segments'])) {
                throw new InvalidArgumentException('Alternative must contain type and segments');
            }

            $validTypes = ['REROUTE', 'RESCHEDULE', 'REFUND'];
            if (!in_array($alternative['type'], $validTypes)) {
                throw new InvalidArgumentException('Invalid alternative type');
            }

            if (isset($alternative['segments'])) {
                foreach ($alternative['segments'] as $segment) {
                    $this->validateSegmentDetails($segment);
                }
            }
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

        if (
            !preg_match('/^[A-Z]{3}$/', $segment['origin']) ||
            !preg_match('/^[A-Z]{3}$/', $segment['destination'])
        ) {
            throw new InvalidArgumentException('Invalid airport code format');
        }

        if (
            !isset($segment['departure']['date']) ||
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $segment['departure']['date'])
        ) {
            throw new InvalidArgumentException('Invalid departure date format');
        }
    }

    public function toArray(): array
    {
        $data = [
            'Query' => [
                'OrderID' => $this->orderId,
                'Notification' => $this->formatNotification()
            ]
        ];

        if ($this->serviceImpact !== null) {
            $data['Query']['ServiceImpact'] = $this->formatServiceImpact();
        }

        if ($this->alternatives !== null) {
            $data['Query']['Alternatives'] = $this->formatAlternatives();
        }

        return $data;
    }

    protected function formatNotification(): array
    {
        $notification = [
            'Type' => $this->notification['type'],
            'Reason' => $this->notification['reason'],
            'Severity' => $this->notification['severity'] ?? 'INFO',
            'Description' => $this->notification['description'] ?? null,
            'Timestamp' => $this->notification['timestamp'] ?? date('Y-m-d\TH:i:s\Z')
        ];

        if (isset($this->notification['affectedSegments'])) {
            $notification['AffectedSegments'] = array_map(function ($segment) {
                return [
                    'SegmentRef' => $segment['segmentRef'],
                    'ImpactType' => $segment['impactType'] ?? null,
                    'Description' => $segment['description'] ?? null
                ];
            }, $this->notification['affectedSegments']);
        }

        return $notification;
    }

    protected function formatServiceImpact(): array
    {
        return array_map(function ($impact) {
            return [
                'ServiceType' => $impact['serviceType'],
                'Status' => $impact['status'],
                'Description' => $impact['description'] ?? null,
                'ServiceRef' => $impact['serviceRef'] ?? null
            ];
        }, $this->serviceImpact);
    }

    protected function formatAlternatives(): array
    {
        return array_map(function ($alternative) {
            return [
                'Type' => $alternative['type'],
                'Description' => $alternative['description'] ?? null,
                'ValidityPeriod' => isset($alternative['validity']) ? [
                    'StartDate' => $alternative['validity']['start'],
                    'EndDate' => $alternative['validity']['end']
                ] : null,
                'Segments' => isset($alternative['segments']) ?
                    array_map([$this, 'formatSegmentDetails'], $alternative['segments']) : null,
                'PriceDifference' => isset($alternative['priceDifference']) ? [
                    'Amount' => [
                        'value' => $alternative['priceDifference']['amount'],
                        'Code' => $alternative['priceDifference']['currency']
                    ]
                ] : null
            ];
        }, $this->alternatives);
    }

    protected function formatSegmentDetails(array $segment): array
    {
        return [
            'Departure' => [
                'AirportCode' => ['value' => $segment['origin']],
                'Date' => $segment['departure']['date'],
                'Time' => $segment['departure']['time'] ?? null,
                'Terminal' => isset($segment['departure']['terminal']) ? [
                    'Name' => $segment['departure']['terminal']
                ] : null
            ],
            'Arrival' => [
                'AirportCode' => ['value' => $segment['destination']],
                'Date' => $segment['arrival']['date'] ?? null,
                'Time' => $segment['arrival']['time'] ?? null,
                'Terminal' => isset($segment['arrival']['terminal']) ? [
                    'Name' => $segment['arrival']['terminal']
                ] : null
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
}
