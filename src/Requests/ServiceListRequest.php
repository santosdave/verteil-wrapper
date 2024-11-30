<?php

namespace Santosdave\VerteilWrapper\Requests;

use InvalidArgumentException;

class ServiceListRequest extends BaseRequest
{
    /** @var string Type of service list request (pre/post) */
    public string $type;

    /** @var array Query parameters including flight selections and order ID */
    public array $query;

    /** @var array|null Party information for corporate requests */
    public ?array $party;

    /** @var array|null Traveler information */
    public ?array $travelers;

    /** @var array|null Shopping response ID from previous request */
    public ?array $shoppingResponseId;

    /** @var array|null Program qualifier for promotions */
    public ?array $qualifier;

    public function __construct(
        string $type,
        array $query,
        ?array $travelers = null,
        ?array $shoppingResponseId = null,
        ?array $party = null,
        ?array $qualifier = null,
        ?string $thirdPartyId = null,
        ?string $officeId = null
    ) {
        parent::__construct([
            'third_party_id' => $thirdPartyId,
            'office_id' => $officeId
        ]);

        $this->type = strtolower($type);
        $this->query = $query;
        $this->travelers = $travelers;
        $this->shoppingResponseId = $shoppingResponseId;
        $this->party = $party;
        $this->qualifier = $qualifier;
    }
    public function getEndpoint(): string
    {
        return "/entrygate/rest/request:{$this->type}ServiceList";
    }

    public function getHeaders(): array
    {
        return [
            'service' => 'ServiceList',
            'ThirdpartyId' => $this->data['third_party_id'] ?? null,
            'OfficeId' => $this->data['office_id'] ?? null,
        ];
    }

    public function validate(): void
    {
        $this->validateType();
        $this->validateQuery();

        if ($this->type === 'pre') {
            $this->validatePreRequest();
        }

        if ($this->party !== null) {
            $this->validateParty();
        }

        if ($this->qualifier !== null) {
            $this->validateQualifier();
        }
    }

    protected function validateType(): void
    {
        if (!in_array($this->type, ['pre', 'post'])) {
            throw new InvalidArgumentException('Invalid service list type. Must be pre or post');
        }
    }

    protected function validateQuery(): void
    {
        if ($this->type === 'post') {
            if (!isset($this->query['OrderID']['Owner']) || !isset($this->query['OrderID']['value'])) {
                throw new InvalidArgumentException('OrderID with Owner and value is required for post service list');
            }

            // Validate airline code format
            if (!preg_match('/^[A-Z]{2}$/', $this->query['OrderID']['Owner'])) {
                throw new InvalidArgumentException('Invalid airline code format in OrderID Owner');
            }
            return;
        }

        if (!isset($this->query['OriginDestination']) || !isset($this->query['Offers'])) {
            throw new InvalidArgumentException('OriginDestination and Offers are required in Query for pre service list');
        }

        $this->validateOriginDestination();
        $this->validateOffers();
    }

    protected function validateOriginDestination(): void
    {
        foreach ($this->query['OriginDestination'] as $od) {
            if (!isset($od['Flight'])) {
                throw new InvalidArgumentException('Flight is required in OriginDestination');
            }

            foreach ($od['Flight'] as $flight) {
                if (!isset($flight['SegmentKey']) || !isset($flight['Departure']) || !isset($flight['Arrival'])) {
                    throw new InvalidArgumentException('Invalid Flight structure');
                }

                if (!isset($flight['Departure']['AirportCode']['value']) || !isset($flight['Arrival']['AirportCode']['value'])) {
                    throw new InvalidArgumentException('Airport codes are required for Departure and Arrival');
                }

                if (!isset($flight['Departure']['Date'])) {
                    throw new InvalidArgumentException('Departure date is required');
                }
            }
        }
    }

    protected function validateOffers(): void
    {
        if (!isset($this->query['Offers']['Offer'])) {
            throw new InvalidArgumentException('At least one Offer is required');
        }

        foreach ($this->query['Offers']['Offer'] as $offer) {
            if (!isset($offer['OfferID']) || !isset($offer['OfferItemIDs'])) {
                throw new InvalidArgumentException('Invalid Offer structure. OfferID and OfferItemIDs are required');
            }

            if (isset($offer['OfferID'])) {
                $this->validateOfferId($offer['OfferID']);
            }
        }
    }

    protected function validateOfferId(array $offerId): void
    {
        if (!isset($offerId['Owner']) || !isset($offerId['value'])) {
            throw new InvalidArgumentException('OfferID must contain Owner and value');
        }

        if (isset($offerId['Channel'])) {
            $validChannels = ['NDC', 'Direct_Connect'];
            if (!in_array($offerId['Channel'], $validChannels)) {
                throw new InvalidArgumentException('Invalid channel in OfferID');
            }
        }
    }

    protected function validatePreRequest(): void
    {
        if ($this->travelers !== null) {
            $this->validateTravelers();
        }

        if ($this->shoppingResponseId !== null) {
            $this->validateShoppingResponseId();
        }
    }

    protected function validateTravelers(): void
    {
        if (!isset($this->travelers['Traveler'])) {
            throw new InvalidArgumentException('At least one Traveler is required');
        }

        foreach ($this->travelers['Traveler'] as $traveler) {
            if (isset($traveler['AnonymousTraveler'])) {
                $this->validateAnonymousTraveler($traveler['AnonymousTraveler']);
            } elseif (isset($traveler['RecognizedTraveler'])) {
                $this->validateRecognizedTraveler($traveler['RecognizedTraveler']);
            } else {
                throw new InvalidArgumentException('Invalid Traveler structure');
            }
        }
    }

    protected function validateAnonymousTraveler(array $anonymousTraveler): void
    {
        foreach ($anonymousTraveler as $traveler) {
            if (!isset($traveler['PTC']['value'])) {
                throw new InvalidArgumentException('PTC is required for anonymous travelers');
            }

            if (!in_array($traveler['PTC']['value'], ['ADT', 'CHD', 'INF'])) {
                throw new InvalidArgumentException('Invalid PTC value. Must be ADT, CHD, or INF');
            }

            if (isset($traveler['Age'])) {
                $this->validateAge($traveler['Age']);
            }
        }
    }

    protected function validateRecognizedTraveler(array $recognizedTraveler): void
    {
        $required = ['ObjectKey', 'PTC', 'Name'];
        foreach ($required as $field) {
            if (!isset($recognizedTraveler[$field])) {
                throw new InvalidArgumentException("$field is required for recognized travelers");
            }
        }

        if (isset($recognizedTraveler['FQTVs'])) {
            foreach ($recognizedTraveler['FQTVs'] as $fqtv) {
                if (!isset($fqtv['AirlineID']) || !isset($fqtv['Account'])) {
                    throw new InvalidArgumentException('Invalid FQTV structure');
                }
            }
        }
    }

    protected function validateAge(array $age): void
    {
        if (isset($age['Value'])) {
            if (!isset($age['Value']['value']) || !is_numeric($age['Value']['value'])) {
                throw new InvalidArgumentException('Invalid age value');
            }
        }

        if (isset($age['BirthDate'])) {
            if (!isset($age['BirthDate']['value']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $age['BirthDate']['value'])) {
                throw new InvalidArgumentException('Invalid birth date format. Must be YYYY-MM-DD');
            }
        }
    }

    protected function validateShoppingResponseId(): void
    {
        if (!isset($this->shoppingResponseId['ResponseID']['value'])) {
            throw new InvalidArgumentException('Invalid ShoppingResponseID structure');
        }
    }

    protected function validateParty(): void
    {
        if (!isset($this->party['Sender']) || !isset($this->party['Sender']['CorporateSender'])) {
            throw new InvalidArgumentException('Invalid Party structure');
        }

        if (!isset($this->party['Sender']['CorporateSender']['CorporateCode'])) {
            throw new InvalidArgumentException('CorporateCode is required in CorporateSender');
        }
    }

    protected function validateQualifier(): void
    {
        if (isset($this->qualifier['ProgramQualifiers'])) {
            if (!isset($this->qualifier['ProgramQualifiers']['ProgramQualifier'])) {
                throw new InvalidArgumentException('Invalid ProgramQualifiers structure');
            }

            foreach ($this->qualifier['ProgramQualifiers']['ProgramQualifier'] as $qualifier) {
                if (!isset($qualifier['DiscountProgramQualifier'])) {
                    throw new InvalidArgumentException('DiscountProgramQualifier is required');
                }

                $required = ['Account', 'AssocCode', 'Name'];
                foreach ($required as $field) {
                    if (!isset($qualifier['DiscountProgramQualifier'][$field]['value'])) {
                        throw new InvalidArgumentException("$field is required in DiscountProgramQualifier");
                    }
                }
            }
        }
    }

    public function toArray(): array
    {
        if ($this->type === 'post') {
            return [
                'Query' => [
                    'OrderID' => $this->query['OrderID']
                ]
            ];
        }

        $data = [
            'Query' => $this->query
        ];

        if ($this->travelers !== null) {
            $data['Travelers'] = $this->travelers;
        }

        if ($this->shoppingResponseId !== null) {
            $data['ShoppingResponseID'] = $this->shoppingResponseId;
        }

        if ($this->party !== null) {
            $data['Party'] = $this->party;
        }

        if ($this->qualifier !== null) {
            $data['Qualifier'] = $this->qualifier;
        }

        return $data;
    }
}
