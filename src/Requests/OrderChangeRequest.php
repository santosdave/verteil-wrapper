<?php

namespace Santosdave\VerteilWrapper\Requests;

use InvalidArgumentException;

class OrderChangeRequest extends BaseRequest
{
    /** @var array Order identification information */
    protected array $orderId;

    /** @var array Changes to be applied to the order */
    protected array $changes;

    /** @var array|null Updated passenger information */
    protected ?array $passengers;

    /** @var array|null Payment information for additional charges */
    protected ?array $payments;

    /** @var string|null Correlation ID from previous OrderReshop */
    protected ?string $correlationId;

    public function __construct(
        array $orderId,
        array $changes,
        ?array $passengers = null,
        ?array $payments = null,
        ?string $correlationId = null,
        ?string $thirdPartyId = null,
        ?string $officeId = null
    ) {
        parent::__construct([
            'third_party_id' => $thirdPartyId,
            'office_id' => $officeId
        ]);

        $this->orderId = $orderId;
        $this->changes = $changes;
        $this->passengers = $passengers;
        $this->payments = $payments;
        $this->correlationId = $correlationId;
    }

    public function getEndpoint(): string
    {
        return '/entrygate/rest/request:orderChange';
    }

    public function getHeaders(): array
    {
        return [
            'service' => 'OrderChange',
            'ThirdpartyId' => $this->data['third_party_id'] ?? null,
            'OfficeId' => $this->data['office_id'] ?? null,
        ];
    }

    public function validate(): void
    {
        $this->validateOrderId();
        $this->validateChanges();
        
        if ($this->passengers !== null) {
            $this->validatePassengers();
        }

        if ($this->payments !== null) {
            $this->validatePayments();
        }
    }

    protected function validateOrderId(): void
    {
        if (!isset($this->orderId['Owner']) || !isset($this->orderId['value'])) {
            throw new InvalidArgumentException('OrderID must contain Owner and value');
        }

        // Validate airline code format
        if (!preg_match('/^[A-Z]{2}$/', $this->orderId['Owner'])) {
            throw new InvalidArgumentException('Invalid airline code format in OrderID Owner');
        }

        // Validate PNR format
        if (!preg_match('/^[A-Z0-9]{4,8}$/', $this->orderId['value'])) {
            throw new InvalidArgumentException('Invalid PNR format in OrderID value');
        }
    }

    protected function validateChanges(): void
    {
        if (empty($this->changes)) {
            throw new InvalidArgumentException('At least one change is required');
        }

        foreach ($this->changes as $change) {
            if (!isset($change['type'])) {
                throw new InvalidArgumentException('Change type is required');
            }

            switch ($change['type']) {
                case 'FLIGHT_CHANGE':
                    $this->validateFlightChange($change);
                    break;
                case 'PASSENGER_INFO':
                    $this->validatePassengerInfoChange($change);
                    break;
                case 'ADD_SERVICE':
                    $this->validateServiceChange($change);
                    break;
                case 'SEAT_CHANGE':
                    $this->validateSeatChange($change);
                    break;
                default:
                    throw new InvalidArgumentException('Invalid change type');
            }
        }
    }

    protected function validateFlightChange(array $change): void
    {
        if (!isset($change['segments']) || empty($change['segments'])) {
            throw new InvalidArgumentException('Flight segments are required for flight change');
        }

        foreach ($change['segments'] as $segment) {
            if (
                !isset($segment['origin']) || !isset($segment['destination']) ||
                !isset($segment['departureDate']) || !isset($segment['flightNumber'])
            ) {
                throw new InvalidArgumentException('Invalid flight segment structure');
            }
        }
    }

    protected function validatePassengerInfoChange(array $change): void
    {
        if (!isset($change['passengerReference']) || !isset($change['updates'])) {
            throw new InvalidArgumentException('Passenger reference and updates are required');
        }

        foreach ($change['updates'] as $update) {
            if (!isset($update['field']) || !isset($update['value'])) {
                throw new InvalidArgumentException('Invalid passenger update structure');
            }
        }
    }

    protected function validateServiceChange(array $change): void
    {
        if (!isset($change['serviceCode']) || !isset($change['passengerReferences'])) {
            throw new InvalidArgumentException('Service code and passenger references are required');
        }
    }

    protected function validateSeatChange(array $change): void
    {
        if (
            !isset($change['segmentReference']) || !isset($change['passengerReference']) ||
            !isset($change['seatNumber'])
        ) {
            throw new InvalidArgumentException('Invalid seat change structure');
        }
    }

    protected function validatePassengers(): void
    {
        foreach ($this->passengers as $passenger) {
            if (!isset($passenger['reference']) || !isset($passenger['type'])) {
                throw new InvalidArgumentException('Invalid passenger structure');
            }

            if (isset($passenger['document'])) {
                $this->validatePassengerDocument($passenger['document']);
            }
        }
    }

    protected function validatePassengerDocument(array $document): void
    {
        $required = ['type', 'number', 'issuingCountry', 'expiryDate'];
        foreach ($required as $field) {
            if (!isset($document[$field])) {
                throw new InvalidArgumentException("Missing required document field: $field");
            }
        }
    }

    protected function validatePayments(): void
    {
        foreach ($this->payments as $payment) {
            if (!isset($payment['amount']) || !isset($payment['currency'])) {
                throw new InvalidArgumentException('Invalid payment structure');
            }

            if (isset($payment['card'])) {
                $this->validatePaymentCard($payment['card']);
            }
        }
    }

    protected function validatePaymentCard(array $card): void
    {
        $required = ['number', 'expiryDate', 'securityCode', 'holderName'];
        foreach ($required as $field) {
            if (!isset($card[$field])) {
                throw new InvalidArgumentException("Missing required card field: $field");
            }
        }
    }

    public function toArray(): array
    {
        $data = [
            'Query' => [
                'OrderID' => $this->orderId,
                'Changes' => array_map(function ($change) {
                    return $this->formatChange($change);
                }, $this->changes)
            ]
        ];

        if ($this->passengers !== null) {
            $data['Passengers'] = $this->formatPassengers();
        }

        if ($this->payments !== null) {
            $data['Payments'] = $this->formatPayments();
        }

        if ($this->correlationId !== null) {
            $data['CorrelationID'] = $this->correlationId;
        }

        return $data;
    }

    protected function formatChange(array $change): array
    {
        switch ($change['type']) {
            case 'FLIGHT_CHANGE':
                return $this->formatFlightChange($change);
            case 'PASSENGER_INFO':
                return $this->formatPassengerInfoChange($change);
            case 'ADD_SERVICE':
                return $this->formatServiceChange($change);
            case 'SEAT_CHANGE':
                return $this->formatSeatChange($change);
            default:
                return [];
        }
    }

    protected function formatFlightChange(array $change): array
    {
        return [
            'ChangeType' => 'FLIGHT_CHANGE',
            'Segments' => array_map(function ($segment) {
                return [
                    'Departure' => [
                        'AirportCode' => ['value' => $segment['origin']],
                        'Date' => $segment['departureDate'],
                        'Time' => $segment['departureTime'] ?? null
                    ],
                    'Arrival' => [
                        'AirportCode' => ['value' => $segment['destination']]
                    ],
                    'MarketingCarrier' => [
                        'AirlineID' => ['value' => $segment['airlineCode']],
                        'FlightNumber' => ['value' => $segment['flightNumber']]
                    ]
                ];
            }, $change['segments'])
        ];
    }

    protected function formatPassengerInfoChange(array $change): array
    {
        return [
            'ChangeType' => 'PASSENGER_INFO',
            'PassengerReference' => $change['passengerReference'],
            'Updates' => array_map(function ($update) {
                return [
                    'Field' => $update['field'],
                    'Value' => $update['value']
                ];
            }, $change['updates'])
        ];
    }

    protected function formatServiceChange(array $change): array
    {
        return [
            'ChangeType' => 'ADD_SERVICE',
            'ServiceCode' => $change['serviceCode'],
            'PassengerReferences' => $change['passengerReferences']
        ];
    }

    protected function formatSeatChange(array $change): array
    {
        return [
            'ChangeType' => 'SEAT_CHANGE',
            'SegmentReference' => $change['segmentReference'],
            'PassengerReference' => $change['passengerReference'],
            'SeatNumber' => $change['seatNumber']
        ];
    }

    protected function formatPassengers(): array
    {
        return [
            'Passenger' => array_map(function ($passenger) {
                return [
                    'ObjectKey' => $passenger['reference'],
                    'PTC' => ['value' => $passenger['type']],
                    'PassengerIDInfo' => isset($passenger['document']) ? [
                        'PassengerDocument' => [[
                            'Type' => $passenger['document']['type'],
                            'ID' => $passenger['document']['number'],
                            'CountryOfIssuance' => $passenger['document']['issuingCountry'],
                            'DateOfExpiration' => $passenger['document']['expiryDate']
                        ]]
                    ] : null
                ];
            }, $this->passengers)
        ];
    }

    protected function formatPayments(): array
    {
        return [
            'Payment' => array_map(function ($payment) {
                return [
                    'Amount' => [
                        'value' => $payment['amount'],
                        'Code' => $payment['currency']
                    ],
                    'Method' => isset($payment['card']) ? [
                        'PaymentCard' => [
                            'CardNumber' => ['value' => $payment['card']['number']],
                            'SeriesCode' => ['value' => $payment['card']['securityCode']],
                            'CardHolderName' => ['value' => $payment['card']['holderName']],
                            'EffectiveExpireDate' => ['value' => $payment['card']['expiryDate']]
                        ]
                    ] : null
                ];
            }, $this->payments)
        ];
    }
}