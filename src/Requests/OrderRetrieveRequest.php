<?php

namespace Santosdave\VerteilWrapper\Requests;

use InvalidArgumentException;

class OrderRetrieveRequest extends BaseRequest
{

    /** @var string The airline's IATA code that owns the order */
    public string $owner;

    /** @var string The airline's PNR/booking reference */
    public string $value;

    /** @var string|null The channel used for the request (NDC, Direct_Connect) */
    public ?string $channel;

    /** @var array Additional filters that might be needed */
    public array $filters;

    public function __construct(
        string $owner,
        string $value,
        ?string $channel = null,
        array $filters = [],
        ?string $thirdPartyId = null,
        ?string $officeId = null
    ) {
        parent::__construct([
            'third_party_id' => $thirdPartyId,
            'office_id' => $officeId
        ]);

        $this->owner = $owner;
        $this->value = $value;
        $this->channel = $channel;
        $this->filters = $filters;
    }

    public function getEndpoint(): string
    {
        return '/entrygate/rest/request:orderRetrieve';
    }

    public function getHeaders(): array
    {
        return [
            'service' => 'OrderRetrieve',
            'ThirdpartyId' => $this->data['third_party_id'] ?? null,
            'OfficeId' => $this->data['office_id'] ?? null,
        ];
    }

    public function validate(): void
    {
        $this->validateOwner();
        $this->validateValue();
        $this->validateChannel();
    }

    protected function validateOwner(): void
    {
        if (empty($this->owner)) {
            throw new InvalidArgumentException('Owner (Airline code) is required');
        }

        // Validate airline code format (2 letter IATA code)
        if (!preg_match('/^[A-Z]{2}$/', $this->owner)) {
            throw new InvalidArgumentException(
                'Invalid airline code format. Must be a 2-letter IATA code'
            );
        }
    }

    protected function validateValue(): void
    {
        if (empty($this->value)) {
            throw new InvalidArgumentException('PNR/Booking reference is required');
        }

        // Most airlines use 6 character PNRs, but some might use different lengths
        // Typically alphanumeric
        if (!preg_match('/^[A-Z0-9]{4,8}$/', $this->value)) {
            throw new InvalidArgumentException(
                'Invalid PNR/Booking reference format. Must be 4-8 alphanumeric characters'
            );
        }
    }

    protected function validateChannel(): void
    {
        if ($this->channel !== null) {
            $validChannels = ['NDC', 'Direct_Connect'];
            if (!in_array($this->channel, $validChannels)) {
                throw new InvalidArgumentException(
                    'Invalid channel. Must be one of: ' . implode(', ', $validChannels)
                );
            }
        }
    }

    public function toArray(): array
    {
        $orderID = array_filter([
            'Owner' => $this->owner,
            'value' => $this->value,
            'Channel' => $this->channel,
        ]);

        $query = [
            'Filters' => array_merge(
                ['OrderID' => $orderID],
                $this->filters
            )
        ];

        return [
            'Query' => $query
        ];
    }
}
