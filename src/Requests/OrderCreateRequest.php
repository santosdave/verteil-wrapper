<?php

namespace Santosdave\VerteilWrapper\Requests;

use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use Santosdave\VerteilWrapper\DataTypes\OrderCreate;

class OrderCreateRequest extends BaseRequest
{
    protected array $query;
    protected ?array $party;
    protected ?array $payments;
    protected ?array $commission;
    protected ?array $metadata;
    protected ?string $thirdPartyId;
    protected ?string $officeId;

    public function __construct(
        array $query,
        ?array $party = null,
        ?array $payments = null,
        ?array $commission = null,
        ?array $metadata = null,
        ?string $thirdPartyId = null,
        ?string $officeId = null
    ) {
        parent::__construct([]);

        $this->query = $query;
        $this->party = $party;
        $this->payments = $payments;
        $this->commission = $commission;
        $this->metadata = $metadata;

        $this->thirdPartyId = $thirdPartyId ?? $shoppingResponseId['owner'] ?? null;

        // Get officeId from config
        $this->officeId = Config::get('verteil.office_id');

        // Set thirdPartyId from param or fallback to shoppingResponse owner
        $shoppingResponse = $this->query['orderItems']['shoppingResponse'] ?? null;
        if ($shoppingResponse) {
            $this->thirdPartyId = $thirdPartyId ?? $shoppingResponse['owner'] ?? null;
        }
    }

    public function getEndpoint(): string
    {
        return '/entrygate/rest/request:orderCreate';
    }

    public function getHeaders(): array
    {
        return array_filter([
            'service' => 'OrderCreate',
            'ThirdpartyId' => $this->thirdPartyId,
            'OfficeId' => $this->officeId
        ]);
    }

    public function validate(): void
    {
        $this->validateQuery();

        if ($this->party !== null) {
            $this->validateParty();
        }

        if ($this->payments !== null) {
            $this->validatePayments();
        }

        if ($this->commission !== null) {
            $this->validateCommission();
        }

        if ($this->metadata !== null) {
            $this->validateMetadata();
        }
    }

    protected function validateQuery(): void
    {
        if (!isset($this->query['orderItems']) || !isset($this->query['dataLists']) || !isset($this->query['passengers'])) {
            throw new InvalidArgumentException('Query must contain orderItems, dataLists, and passengers');
        }

        $this->validateOrderItems($this->query['orderItems']);
        $this->validateDataLists($this->query['dataLists']);
        $this->validatePassengers($this->query['passengers']);
    }

    protected function validateOrderItems(array $orderItems): void
    {
        if (!isset($orderItems['shoppingResponse']) || !isset($orderItems['offerItem'])) {
            throw new InvalidArgumentException('OrderItems must contain shoppingResponse and offerItem');
        }

        // Validate shopping response
        $shoppingResponse = $orderItems['shoppingResponse'];
        if (!isset($shoppingResponse['owner']) || !isset($shoppingResponse['responseId']) || !isset($shoppingResponse['offers'])) {
            throw new InvalidArgumentException('Invalid shopping response structure');
        }

        // Validate offer items
        foreach ($orderItems['offerItem'] as $item) {
            if (!isset($item['offerId']) || !isset($item['type'])) {
                throw new InvalidArgumentException('Each offer item must contain offerId and type');
            }

            // Validate specific offer item types
            switch ($item['type']) {
                case 'flight':
                    $this->validateFlightOfferItem($item);
                    break;
                case 'seat':
                    $this->validateSeatOfferItem($item);
                    break;
                case 'ancillary':
                    $this->validateAncillaryOfferItem($item);
                    break;
            }
        }
    }

    protected function validateFlightOfferItem(array $item): void
    {
        if (!isset($item['price']) || !isset($item['originDestination'])) {
            throw new InvalidArgumentException('Flight offer item must contain price and originDestination');
        }

        foreach ($item['originDestination'] as $od) {
            if (!isset($od['flights'])) {
                throw new InvalidArgumentException('Origin destination must contain flights');
            }

            foreach ($od['flights'] as $flight) {
                $required = ['segmentKey', 'departure', 'arrival', 'airline', 'flightNumber'];
                foreach ($required as $field) {
                    if (!isset($flight[$field])) {
                        throw new InvalidArgumentException("Missing required flight field: $field");
                    }
                }
            }
        }
    }

    protected function validateSeatOfferItem(array $item): void
    {
        if (!isset($item['associations']) || !isset($item['location'])) {
            throw new InvalidArgumentException('Seat offer item must contain associations and location');
        }

        foreach ($item['associations'] as $assoc) {
            if (!isset($assoc['segmentRef']) || !isset($assoc['travelerRef'])) {
                throw new InvalidArgumentException('Seat association must contain segmentRef and travelerRef');
            }
        }

        if (!isset($item['location']['column']) || !isset($item['location']['row'])) {
            throw new InvalidArgumentException('Seat location must contain column and row');
        }
    }

    protected function validateAncillaryOfferItem(array $item): void
    {
        if (!isset($item['serviceCode']) || !isset($item['price'])) {
            throw new InvalidArgumentException('Ancillary offer item must contain serviceCode and price');
        }
    }

    protected function validateDataLists(array $dataLists): void
    {
        if (!isset($dataLists['fares'])) {
            throw new InvalidArgumentException('DataLists must contain fare information');
        }

        foreach ($dataLists['fares'] as $fare) {
            if (!isset($fare['listKey']) || !isset($fare['code'])) {
                throw new InvalidArgumentException('Each fare must contain listKey and code');
            }
        }
    }

    protected function validatePassengers(array $passengers): void
    {
        foreach ($passengers as $passenger) {
            if (
                !isset($passenger['objectKey']) || !isset($passenger['passengerType']) ||
                !isset($passenger['gender']) || !isset($passenger['name'])
            ) {
                throw new InvalidArgumentException('Invalid passenger structure');
            }

            // Validate passenger type
            if (!in_array($passenger['passengerType'], ['ADT', 'CHD', 'INF'])) {
                throw new InvalidArgumentException('Invalid passenger type. Must be ADT, CHD, or INF');
            }

            // Validate name
            if (!isset($passenger['name']['given']) || !isset($passenger['name']['surname'])) {
                throw new InvalidArgumentException('Passenger name must contain given name and surname');
            }

            // Validate contacts if present
            if (isset($passenger['contacts'])) {
                $this->validatePassengerContacts($passenger['contacts']);
            }

            // Validate documents if present
            if (isset($passenger['document'])) {
                $this->validatePassengerDocument($passenger['document']);
            }
        }
    }

    protected function validatePassengerContacts(array $contacts): void
    {
        if (!isset($contacts['phone']) || !isset($contacts['email']) || !isset($contacts['address'])) {
            throw new InvalidArgumentException('Passenger contacts must contain phone, email, and address');
        }

        // Validate phone
        if (!isset($contacts['phone']['countryCode']) || !isset($contacts['phone']['number'])) {
            throw new InvalidArgumentException('Phone contact must contain countryCode and number');
        }

        // Validate email format
        if (!filter_var($contacts['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email format');
        }

        // Validate address
        if (
            !isset($contacts['address']['street']) || !isset($contacts['address']['postalCode']) ||
            !isset($contacts['address']['city']) || !isset($contacts['address']['countryCode'])
        ) {
            throw new InvalidArgumentException('Address must contain street, postalCode, city, and countryCode');
        }
    }

    protected function validatePassengerDocument(array $document): void
    {
        // Validate required fields
        $required = ['type', 'number', 'issuingCountry'];
        foreach ($required as $field) {
            if (!isset($document[$field])) {
                throw new InvalidArgumentException("Document must contain $field");
            }
        }

        // Validate document type
        $validTypes = ['PT', 'NI', 'ID', 'CR'];
        if (!in_array($document['type'], $validTypes)) {
            throw new InvalidArgumentException('Invalid document type. Must be PT, NI, ID, or CR');
        }

        // Validate country code format
        if (!preg_match('/^[A-Z]{2}$/', $document['issuingCountry'])) {
            throw new InvalidArgumentException('Invalid country code format in document');
        }

        // Validate expiry date format if present
        if (
            isset($document['expiryDate']) &&
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $document['expiryDate'])
        ) {
            throw new InvalidArgumentException('Invalid expiry date format. Must be YYYY-MM-DD');
        }
    }

    protected function validateParty(): void
    {
        if (!isset($this->party['corporateCode'])) {
            throw new InvalidArgumentException('Corporate code is required in party information');
        }

        // Validate corporate code format
        $pattern = '/^[A-Z]{2}(\/[A-Z0-9]+)?(\/[A-Z0-9]+)?$/';
        if (!preg_match($pattern, $this->party['corporateCode'])) {
            throw new InvalidArgumentException(
                'Invalid corporate code format. Must be AIRLINE_CODE/DEALCODE/CLID or AIRLINE_CODE/DEALCODE or AIRLINE_CODE//CLID'
            );
        }

        // Validate contact information if present
        if (isset($this->party['contact'])) {
            if (
                !isset($this->party['contact']['email']) ||
                !isset($this->party['contact']['phoneCountryCode']) ||
                !isset($this->party['contact']['phoneNumber'])
            ) {
                throw new InvalidArgumentException('Party contact must contain email, phoneCountryCode, and phoneNumber');
            }

            if (!filter_var($this->party['contact']['email'], FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Invalid party contact email format');
            }
        }
    }

    protected function validatePayments(): void
    {
        foreach ($this->payments as $payment) {
            if (!isset($payment['amount']) || !isset($payment['currency'])) {
                throw new InvalidArgumentException('Payment must contain amount and currency');
            }

            // Validate amount
            if (!is_numeric($payment['amount']) || $payment['amount'] <= 0) {
                throw new InvalidArgumentException('Payment amount must be a positive number');
            }

            // Validate currency format
            if (!preg_match('/^[A-Z]{3}$/', $payment['currency'])) {
                throw new InvalidArgumentException('Invalid currency code format');
            }

            // Validate payment method
            if (isset($payment['card'])) {
                $this->validatePaymentCard($payment['card']);
            } elseif (isset($payment['cash'])) {
                if (!is_bool($payment['cash'])) {
                    throw new InvalidArgumentException('Cash payment indicator must be a boolean');
                }
            } elseif (isset($payment['other'])) {
                if (!isset($payment['other']['remarks'])) {
                    throw new InvalidArgumentException('Other payment method must contain remarks');
                }
            } else {
                throw new InvalidArgumentException('Invalid payment method. Must be card, cash, or other');
            }
        }
    }

    protected function validatePaymentCard(array $card): void
    {
        // Validate required fields
        $required = ['number', 'expiryDate', 'brand'];
        foreach ($required as $field) {
            if (!isset($card[$field])) {
                throw new InvalidArgumentException("Card must contain $field");
            }
        }

        // Validate card brand
        $validBrands = ['AX', 'DS', 'DC', 'UP', 'JC', 'CA', 'TP', 'VI'];
        if (!in_array($card['brand'], $validBrands)) {
            throw new InvalidArgumentException('Invalid card brand');
        }

        // Validate expiry date format (MMYY)
        if (!preg_match('/^(0[1-9]|1[0-2])\d{2}$/', $card['expiryDate'])) {
            throw new InvalidArgumentException('Invalid card expiry date format. Must be MMYY');
        }

        // Validate CVV if present
        if (isset($card['cvv']) && !preg_match('/^\d{3,4}$/', $card['cvv'])) {
            throw new InvalidArgumentException('Invalid CVV format');
        }

        // Validate billing address if present
        if (isset($card['billingAddress'])) {
            $required = ['street', 'postalCode', 'city', 'countryCode'];
            foreach ($required as $field) {
                if (!isset($card['billingAddress'][$field])) {
                    throw new InvalidArgumentException("Billing address must contain $field");
                }
            }
        }
    }

    protected function validateCommission(): void
    {
        foreach ($this->commission as $comm) {
            if (!isset($comm['amount']) || !isset($comm['currency']) || !isset($comm['code'])) {
                throw new InvalidArgumentException('Commission must contain amount, currency, and code');
            }

            // Validate amount
            if (!is_numeric($comm['amount']) || $comm['amount'] < 0) {
                throw new InvalidArgumentException('Commission amount must be a non-negative number');
            }

            // Validate currency format
            if (!preg_match('/^[A-Z]{3}$/', $comm['currency'])) {
                throw new InvalidArgumentException('Invalid commission currency code format');
            }
        }
    }

    protected function validateMetadata(): void
    {
        // Validate passenger metadata if present
        if (isset($this->metadata['passengerMetadata'])) {
            foreach ($this->metadata['passengerMetadata'] as $meta) {
                if (!isset($meta['augmentationPoints'])) {
                    throw new InvalidArgumentException('Passenger metadata must contain augmentationPoints');
                }
            }
        }

        // Validate other metadata if present
        if (isset($this->metadata['other'])) {
            foreach ($this->metadata['other'] as $meta) {
                // Validate payment form metadata
                if (isset($meta['paymentForm'])) {
                    foreach ($meta['paymentForm'] as $payment) {
                        if (!isset($payment['text']) || !isset($payment['key'])) {
                            throw new InvalidArgumentException('Payment form metadata must contain text and key');
                        }
                    }
                }

                // Validate price metadata
                if (isset($meta['price'])) {
                    foreach ($meta['price'] as $price) {
                        if (!isset($price['key']) || !isset($price['augmentationPoints'])) {
                            throw new InvalidArgumentException('Price metadata must contain key and augmentationPoints');
                        }
                    }
                }

                // Validate currency metadata
                if (isset($meta['currency'])) {
                    foreach ($meta['currency'] as $currency) {
                        if (!isset($currency['key']) || !isset($currency['decimals'])) {
                            throw new InvalidArgumentException('Currency metadata must contain key and decimals');
                        }
                    }
                }
            }
        }
    }

    public function toArray(): array
    {
        return OrderCreate::create([
            'query' => $this->query,
            'party' => $this->party,
            'payments' => $this->payments,
            'commission' => $this->commission,
            'metadata' => $this->metadata
        ]);
    }
}
