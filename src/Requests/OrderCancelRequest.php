<?php

namespace Santosdave\VerteilWrapper\Requests;

use InvalidArgumentException;

class OrderCancelRequest extends BaseRequest
{
    /** @var array Order identification information */
    public array $orderId;

    /** @var array|null Expected refund amount for ticketed bookings */
    public ?array $expectedRefundAmount;

    /** @var array|null Metadata containing payment/currency information */
    public ?array $metadata;

    /** @var string|null Correlation ID from previous ItinReshop response */
    public ?string $correlationId;

    public function __construct(
        array $orderId,
        ?array $expectedRefundAmount = null,
        ?array $metadata = null,
        ?string $correlationId = null,
        ?string $thirdPartyId = null,
        ?string $officeId = null
    ) {
        parent::__construct([
            'third_party_id' => $thirdPartyId,
            'office_id' => $officeId
        ]);

        $this->orderId = $orderId;
        $this->expectedRefundAmount = $expectedRefundAmount;
        $this->metadata = $metadata;
        $this->correlationId = $correlationId;
    }

    public function getEndpoint(): string
    {
        return '/entrygate/rest/request:orderCancel';
    }

    public function getHeaders(): array
    {
        return [
            'service' => 'OrderCancel',
            'ThirdpartyId' => $this->data['third_party_id'] ?? null,
            'OfficeId' => $this->data['office_id'] ?? null,
        ];
    }

    public function validate(): void
    {
        $this->validateOrderId();

        if ($this->expectedRefundAmount !== null) {
            $this->validateExpectedRefundAmount();
        }

        if ($this->metadata !== null) {
            $this->validateMetadata();
        }
    }

    protected function validateOrderId(): void
    {
        if (empty($this->orderId)) {
            throw new InvalidArgumentException('OrderID is required');
        }

        foreach ($this->orderId as $order) {
            if (!isset($order['Owner']) || !isset($order['value'])) {
                throw new InvalidArgumentException('OrderID must contain Owner and value');
            }

            // Validate airline code format
            if (!preg_match('/^[A-Z]{2}$/', $order['Owner'])) {
                throw new InvalidArgumentException(
                    'Invalid airline code format in OrderID Owner. Must be a 2-letter IATA code'
                );
            }

            // Validate PNR format (typically 6 characters, but some airlines might use different lengths)
            if (!preg_match('/^[A-Z0-9]{4,8}$/', $order['value'])) {
                throw new InvalidArgumentException(
                    'Invalid PNR format in OrderID value. Must be 4-8 alphanumeric characters'
                );
            }

            // Validate refs if present
            if (isset($order['refs'])) {
                foreach ($order['refs'] as $ref) {
                    if (!isset($ref['Ref'])) {
                        throw new InvalidArgumentException('Invalid refs structure in OrderID');
                    }
                }
            }

            // Validate channel if present
            if (isset($order['Channel'])) {
                $validChannels = ['NDC', 'Direct_Connect'];
                if (!in_array($order['Channel'], $validChannels)) {
                    throw new InvalidArgumentException(
                        'Invalid channel in OrderID. Must be NDC or Direct_Connect'
                    );
                }
            }
        }
    }

    protected function validateExpectedRefundAmount(): void
    {
        if (!isset($this->expectedRefundAmount['Total'])) {
            throw new InvalidArgumentException('Total is required in ExpectedRefundAmount');
        }

        $total = $this->expectedRefundAmount['Total'];
        if (!isset($total['value']) || !isset($total['Code'])) {
            throw new InvalidArgumentException('ExpectedRefundAmount Total must contain value and Code');
        }

        // Validate amount is positive
        if ($total['value'] <= 0) {
            throw new InvalidArgumentException('ExpectedRefundAmount Total value must be positive');
        }

        // Validate currency code format (3 letters)
        if (!preg_match('/^[A-Z]{3}$/', $total['Code'])) {
            throw new InvalidArgumentException(
                'Invalid currency code format in ExpectedRefundAmount. Must be a 3-letter code'
            );
        }
    }

    protected function validateMetadata(): void
    {
        if (!isset($this->metadata['Other']['OtherMetadata'])) {
            throw new InvalidArgumentException('Invalid Metadata structure');
        }

        foreach ($this->metadata['Other']['OtherMetadata'] as $metadata) {
            // Validate PriceMetadatas if present
            if (isset($metadata['PriceMetadatas'])) {
                $this->validatePriceMetadata($metadata['PriceMetadatas']);
            }

            // Validate CurrencyMetadatas if present
            if (isset($metadata['CurrencyMetadatas'])) {
                $this->validateCurrencyMetadata($metadata['CurrencyMetadatas']);
            }
        }
    }

    protected function validatePriceMetadata(array $priceMetadatas): void
    {
        if (!isset($priceMetadatas['PriceMetadata'])) {
            throw new InvalidArgumentException('PriceMetadata is required in PriceMetadatas');
        }

        foreach ($priceMetadatas['PriceMetadata'] as $metadata) {
            if (!isset($metadata['AugmentationPoint']) || !isset($metadata['MetadataKey'])) {
                throw new InvalidArgumentException('Invalid PriceMetadata structure');
            }
        }
    }

    protected function validateCurrencyMetadata(array $currencyMetadatas): void
    {
        if (!isset($currencyMetadatas['CurrencyMetadata'])) {
            throw new InvalidArgumentException('CurrencyMetadata is required in CurrencyMetadatas');
        }

        foreach ($currencyMetadatas['CurrencyMetadata'] as $metadata) {
            if (!isset($metadata['MetadataKey']) || !isset($metadata['Decimals'])) {
                throw new InvalidArgumentException('Invalid CurrencyMetadata structure');
            }

            // Validate decimals is non-negative
            if ($metadata['Decimals'] < 0) {
                throw new InvalidArgumentException('Decimals must be non-negative in CurrencyMetadata');
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

        if ($this->expectedRefundAmount !== null) {
            $data['ExpectedRefundAmount'] = $this->expectedRefundAmount;
        }

        if ($this->metadata !== null) {
            $data['Metadata'] = $this->metadata;
        }

        if ($this->correlationId !== null) {
            $data['CorrelationID'] = $this->correlationId;
        }

        return $data;
    }
}
