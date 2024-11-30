<?php

namespace Santosdave\VerteilWrapper\Requests;

use InvalidArgumentException;

class FlightPriceRequest extends BaseRequest
{

    /** @var array|null Party information with corporate sender details */
    public ?array $party;

    /** @var array Required data lists including fare, baggage and traveler information */
    public array $dataLists;

    /** @var array Query parameters including flight selections */
    public array $query;

    /** @var array Traveler information */
    public array $travelers;

    /** @var array Shopping response identifier from previous AirShopping request */
    public array $shoppingResponseId;

    /** @var array|null Payment card qualifier details */
    public ?array $qualifier;

    /** @var array|null Parameters for currency override */
    public ?array $parameters;

    public function __construct(
        array $dataLists,
        array $query,
        array $travelers,
        array $shoppingResponseId,
        ?array $party = null,
        ?array $qualifier = null,
        ?array $parameters = null,
        ?string $thirdPartyId = null,
        ?string $officeId = null
    ) {
        parent::__construct([
            'third_party_id' => $thirdPartyId,
            'office_id' => $officeId
        ]);

        $this->dataLists = $dataLists;
        $this->query = $query;
        $this->travelers = $travelers;
        $this->shoppingResponseId = $shoppingResponseId;
        $this->party = $party;
        $this->qualifier = $qualifier;
        $this->parameters = $parameters;
    }

    public function getEndpoint(): string
    {
        return '/entrygate/rest/request:flightPrice';
    }

    public function getHeaders(): array
    {
        return [
            'service' => 'FlightPrice',
            'ThirdpartyId' => $this->data['third_party_id'] ?? null,
            'OfficeId' => $this->data['office_id'] ?? null,
        ];
    }

    public function validate(): void
    {
        $this->validateDataLists();
        $this->validateQuery();
        $this->validateTravelers();
        $this->validateShoppingResponseId();

        if ($this->qualifier !== null) {
            $this->validateQualifier();
        }
    }

    protected function validateDataLists(): void
    {
        if (!isset($this->dataLists['FareList']) || !isset($this->dataLists['FareList']['FareGroup'])) {
            throw new InvalidArgumentException('FareList with FareGroup is required in DataLists');
        }

        foreach ($this->dataLists['FareList']['FareGroup'] as $fareGroup) {
            if (!isset($fareGroup['ListKey']) || !isset($fareGroup['FareBasisCode']['Code'])) {
                throw new InvalidArgumentException('Invalid FareGroup structure. ListKey and FareBasisCode are required');
            }
        }

        // Validate AnonymousTravelerList if present
        if (isset($this->dataLists['AnonymousTravelerList'])) {
            foreach ($this->dataLists['AnonymousTravelerList']['AnonymousTraveler'] as $traveler) {
                if (!isset($traveler['ObjectKey']) || !isset($traveler['PTC']['value'])) {
                    throw new InvalidArgumentException('Invalid AnonymousTraveler structure');
                }
                if (!in_array($traveler['PTC']['value'], ['ADT', 'CHD', 'INF'])) {
                    throw new InvalidArgumentException('Invalid PTC value for AnonymousTraveler');
                }
            }
        }
    }

    protected function validateQuery(): void
    {
        if (!isset($this->query['OriginDestination']) || !isset($this->query['Offers'])) {
            throw new InvalidArgumentException('OriginDestination and Offers are required in Query');
        }

        foreach ($this->query['OriginDestination'] as $od) {
            if (!isset($od['Flight'])) {
                throw new InvalidArgumentException('Flight details are required in OriginDestination');
            }
            foreach ($od['Flight'] as $flight) {
                if (!isset($flight['SegmentKey']) || !isset($flight['Departure']) || !isset($flight['Arrival'])) {
                    throw new InvalidArgumentException('Invalid Flight structure in OriginDestination');
                }
            }
        }

        if (!isset($this->query['Offers']['Offer'])) {
            throw new InvalidArgumentException('Offer details are required in Query');
        }

        foreach ($this->query['Offers']['Offer'] as $offer) {
            if (!isset($offer['OfferID']['Owner']) || !isset($offer['OfferID']['value'])) {
                throw new InvalidArgumentException('Invalid Offer structure. OfferID is required');
            }
        }
    }

    protected function validateTravelers(): void
    {
        if (!isset($this->travelers['Traveler'])) {
            throw new InvalidArgumentException('At least one traveler is required');
        }

        foreach ($this->travelers['Traveler'] as $traveler) {
            if (isset($traveler['AnonymousTraveler'])) {
                foreach ($traveler['AnonymousTraveler'] as $anonymous) {
                    if (!isset($anonymous['PTC']['value'])) {
                        throw new InvalidArgumentException('PTC is required for anonymous travelers');
                    }
                }
            } elseif (isset($traveler['RecognizedTraveler'])) {
                if (
                    !isset($traveler['RecognizedTraveler']['FQTVs']) ||
                    !isset($traveler['RecognizedTraveler']['ObjectKey']) ||
                    !isset($traveler['RecognizedTraveler']['PTC']) ||
                    !isset($traveler['RecognizedTraveler']['Name'])
                ) {
                    throw new InvalidArgumentException('Invalid RecognizedTraveler structure');
                }
            }
        }
    }

    protected function validateShoppingResponseId(): void
    {
        if (!isset($this->shoppingResponseId['Owner']) || !isset($this->shoppingResponseId['ResponseID']['value'])) {
            throw new InvalidArgumentException('Invalid ShoppingResponseID structure');
        }
    }

    protected function validateQualifier(): void
    {
        if (isset($this->qualifier['ProgramQualifiers'])) {
            if (!isset($this->qualifier['ProgramQualifiers']['ProgramQualifier'])) {
                throw new InvalidArgumentException('Invalid ProgramQualifiers structure');
            }
        }

        if (isset($this->qualifier['PaymentCardQualifier'])) {
            if (
                !isset($this->qualifier['PaymentCardQualifier']['cardBrandCode']) ||
                !isset($this->qualifier['PaymentCardQualifier']['cardNumber'])
            ) {
                throw new InvalidArgumentException('Invalid PaymentCardQualifier structure');
            }

            // Validate card brand code
            $validBrandCodes = ['AX', 'DS', 'DC', 'UP', 'JC', 'CA', 'TP', 'VI'];
            if (!in_array($this->qualifier['PaymentCardQualifier']['cardBrandCode'], $validBrandCodes)) {
                throw new InvalidArgumentException('Invalid card brand code');
            }
        }
    }

    public function toArray(): array
    {
        $data = [
            'DataLists' => $this->dataLists,
            'Query' => $this->query,
            'Travelers' => $this->travelers,
            'ShoppingResponseID' => $this->shoppingResponseId
        ];

        if ($this->party !== null) {
            $data['Party'] = $this->party;
        }

        if ($this->qualifier !== null) {
            $data['Qualifier'] = $this->qualifier;
        }

        if ($this->parameters !== null) {
            $data['Parameters'] = $this->parameters;
        }

        return $data;
    }
}
