<?php

namespace Santosdave\VerteilWrapper\Requests;

use InvalidArgumentException;

class OrderCreateRequest extends BaseRequest
{
    /** @var array|null Corporate party information */
    public ?array $party;

    /** @var array Required order query information */
    public array $query;

    /** @var array|null Metadata for payment and currency info */
    public ?array $metadata;

    /** @var array|null Commission details */
    public ?array $commission;

    /** @var array|null Payment information */
    public ?array $payments;

    public function __construct(
        array $query,
        ?array $party = null,
        ?array $metadata = null,
        ?array $commission = null,
        ?array $payments = null,
        ?string $thirdPartyId = null,
        ?string $officeId = null
    ) {
        parent::__construct([
            'third_party_id' => $thirdPartyId,
            'office_id' => $officeId
        ]);

        $this->query = $query;
        $this->party = $party;
        $this->metadata = $metadata;
        $this->commission = $commission;
        $this->payments = $payments;
    }

    public function getEndpoint(): string
    {
        return '/entrygate/rest/request:orderCreate';
    }

    public function getHeaders(): array
    {
        return [
            'service' => 'OrderCreate',
            'ThirdpartyId' => $this->data['third_party_id'] ?? null,
            'OfficeId' => $this->data['office_id'] ?? null,
        ];
    }

    public function validate(): void
    {
        $this->validateQuery();
        if ($this->payments !== null) {
            $this->validatePayments();
        }
        if ($this->party !== null) {
            $this->validateParty();
        }
    }

    protected function validateQuery(): void
    {
        // Validate required sections
        if (
            !isset($this->query['OrderItems']) || !isset($this->query['DataLists']) ||
            !isset($this->query['Passengers'])
        ) {
            throw new InvalidArgumentException('OrderItems, DataLists, and Passengers are required in Query');
        }

        $this->validateOrderItems();
        $this->validateDataLists();
        $this->validatePassengers();
    }

    protected function validateOrderItems(): void
    {
        if (
            !isset($this->query['OrderItems']['ShoppingResponse']) ||
            !isset($this->query['OrderItems']['OfferItem'])
        ) {
            throw new InvalidArgumentException('ShoppingResponse and OfferItem are required in OrderItems');
        }

        // Validate ShoppingResponse
        $shoppingResponse = $this->query['OrderItems']['ShoppingResponse'];
        if (
            !isset($shoppingResponse['Owner']) || !isset($shoppingResponse['ResponseID']) ||
            !isset($shoppingResponse['Offers'])
        ) {
            throw new InvalidArgumentException('Invalid ShoppingResponse structure');
        }

        // Validate OfferItem
        foreach ($this->query['OrderItems']['OfferItem'] as $offerItem) {
            if (!isset($offerItem['OfferItemID']) || !isset($offerItem['OfferItemType'])) {
                throw new InvalidArgumentException('Invalid OfferItem structure');
            }

            if (isset($offerItem['OfferItemType']['DetailedFlightItem'])) {
                $this->validateDetailedFlightItem($offerItem['OfferItemType']['DetailedFlightItem']);
            }
        }
    }

    protected function validateDetailedFlightItem(array $flightItem): void
    {
        foreach ($flightItem as $item) {
            if (!isset($item['Price']) || !isset($item['OriginDestination']) || !isset($item['refs'])) {
                throw new InvalidArgumentException('Invalid DetailedFlightItem structure');
            }

            // Validate Price structure
            if (!isset($item['Price']['BaseAmount']) || !isset($item['Price']['Taxes'])) {
                throw new InvalidArgumentException('Invalid Price structure in DetailedFlightItem');
            }
        }
    }

    protected function validateDataLists(): void
    {
        if (!isset($this->query['DataLists']['FareList'])) {
            throw new InvalidArgumentException('FareList is required in DataLists');
        }

        foreach ($this->query['DataLists']['FareList']['FareGroup'] as $fareGroup) {
            if (!isset($fareGroup['ListKey']) || !isset($fareGroup['FareBasisCode']['Code'])) {
                throw new InvalidArgumentException('Invalid FareGroup structure');
            }
        }
    }

    protected function validatePassengers(): void
    {
        if (!isset($this->query['Passengers']['Passenger'])) {
            throw new InvalidArgumentException('At least one passenger is required');
        }

        foreach ($this->query['Passengers']['Passenger'] as $passenger) {
            if (
                !isset($passenger['Contacts']) || !isset($passenger['ObjectKey']) ||
                !isset($passenger['PTC']) || !isset($passenger['Name'])
            ) {
                throw new InvalidArgumentException('Invalid Passenger structure');
            }

            // Validate contact information
            $this->validatePassengerContacts($passenger['Contacts']);

            // Validate PTC
            if (!in_array($passenger['PTC']['value'], ['ADT', 'CHD', 'INF'])) {
                throw new InvalidArgumentException('Invalid PTC value');
            }

            // Validate Name
            if (!isset($passenger['Name']['Given']) || !isset($passenger['Name']['Surname'])) {
                throw new InvalidArgumentException('Invalid Name structure');
            }
        }
    }

    protected function validatePassengerContacts(array $contacts): void
    {
        if (!isset($contacts['Contact'])) {
            throw new InvalidArgumentException('Contact information is required');
        }

        foreach ($contacts['Contact'] as $contact) {
            if (!isset($contact['PhoneContact']) || !isset($contact['EmailContact'])) {
                throw new InvalidArgumentException('Phone and Email contacts are required');
            }

            if (
                !isset($contact['PhoneContact']['Number']) ||
                !isset($contact['EmailContact']['Address'])
            ) {
                throw new InvalidArgumentException('Invalid contact information structure');
            }
        }
    }

    protected function validatePayments(): void
    {
        if (!isset($this->payments['Payment'])) {
            throw new InvalidArgumentException('Payment information is required');
        }

        foreach ($this->payments['Payment'] as $payment) {
            if (!isset($payment['Amount']) || !isset($payment['Method'])) {
                throw new InvalidArgumentException('Invalid Payment structure');
            }

            // Validate payment method
            if (isset($payment['Method']['PaymentCard'])) {
                $this->validatePaymentCard($payment['Method']['PaymentCard']);
            }
        }
    }

    protected function validatePaymentCard(array $paymentCard): void
    {
        $requiredFields = [
            'CardNumber',
            'EffectiveExpireDate',
            'CardType',
            'Amount',
            'CardCode'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($paymentCard[$field])) {
                throw new InvalidArgumentException("Missing required payment card field: $field");
            }
        }

        // Validate card code
        $validCardCodes = ['AX', 'DS', 'DC', 'UP', 'JC', 'CA', 'TP', 'VI'];
        if (!in_array($paymentCard['CardCode'], $validCardCodes)) {
            throw new InvalidArgumentException('Invalid card code');
        }

        // Validate card type
        if ($paymentCard['CardType'] !== 'Credit') {
            throw new InvalidArgumentException('Invalid card type');
        }
    }

    protected function validateParty(): void
    {
        if (!isset($this->party['Sender']) || !isset($this->party['Sender']['CorporateSender'])) {
            throw new InvalidArgumentException('Invalid Party structure. Sender with CorporateSender is required');
        }

        $corporateSender = $this->party['Sender']['CorporateSender'];

        if (!isset($corporateSender['CorporateCode'])) {
            throw new InvalidArgumentException('CorporateCode is required in CorporateSender');
        }

        // Validate corporate code format
        $corporateCode = $corporateSender['CorporateCode'];
        if (!preg_match('/^[A-Z]{2}(\/[A-Z0-9]+)?(\/[A-Z0-9]+)?$/', $corporateCode)) {
            throw new InvalidArgumentException(
                'Invalid CorporateCode format. Must be in format: AIRLINE_CODE/DEALCODE/CLID or AIRLINE_CODE/DEALCODE or AIRLINE_CODE//CLID'
            );
        }

        // Validate multiple corporate deal codes if present
        if (strpos($corporateCode, ',') !== false) {
            $codes = explode(',', $corporateCode);
            foreach ($codes as $code) {
                if (!preg_match('/^[A-Z]{2}(\/[A-Z0-9]+)?(\/[A-Z0-9]+)?$/', trim($code))) {
                    throw new InvalidArgumentException(
                        'Invalid format in multiple corporate codes. Each code must follow format: AIRLINE_CODE/DEALCODE/CLID'
                    );
                }
            }
        }
    }

    public function toArray(): array
    {
        $data = [
            'Query' => $this->query
        ];

        if ($this->party !== null) {
            $data['Party'] = $this->party;
        }

        if ($this->metadata !== null) {
            $data['Metadata'] = $this->metadata;
        }

        if ($this->commission !== null) {
            $data['Commission'] = $this->commission;
        }

        if ($this->payments !== null) {
            $data['Payments'] = $this->payments;
        }

        return $data;
    }
}
