<?php

namespace Santosdave\VerteilWrapper\Requests;

use InvalidArgumentException;

class SeatAvailabilityRequest extends BaseRequest
{
    /** @var string Type of seat availability request (pre/post) */
    public string $type;

    /** @var array|null Data lists containing fare and segment information */
    public ?array $dataLists;

    /** @var array Query parameters including flight selections */
    public array $query;

    /** @var array|null Traveler information */
    public ?array $travelers;

    /** @var array|null Shopping response ID from previous request */
    public ?array $shoppingResponseId;

    public function __construct(
        string $type,
        array $query,
        ?array $dataLists = null,
        ?array $travelers = null,
        ?array $shoppingResponseId = null,
        ?string $thirdPartyId = null,
        ?string $officeId = null
    ) {
        parent::__construct([
            'third_party_id' => $thirdPartyId,
            'office_id' => $officeId
        ]);

        $this->type = strtolower($type);
        $this->query = $query;
        $this->dataLists = $dataLists;
        $this->travelers = $travelers;
        $this->shoppingResponseId = $shoppingResponseId;
    }

    public function getEndpoint(): string
    {
        return "/entrygate/rest/request:{$this->type}SeatAvailability";
    }

    public function getHeaders(): array
    {
        return [
            'service' => 'SeatAvailability',
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
    }

    protected function validateType(): void
    {
        if (!in_array($this->type, ['pre', 'post'])) {
            throw new InvalidArgumentException('Invalid seat availability type. Must be pre or post');
        }
    }

    protected function validateQuery(): void
    {
        if ($this->type === 'post') {
            if (!isset($this->query['OrderID']['Owner']) || !isset($this->query['OrderID']['value'])) {
                throw new InvalidArgumentException('OrderID with Owner and value is required for post seat availability');
            }
            return;
        }

        if (!isset($this->query['OriginDestination']) || !isset($this->query['Offers'])) {
            throw new InvalidArgumentException('OriginDestination and Offers are required in Query for pre seat availability');
        }

        // Validate Offers
        if (!isset($this->query['Offers']['Offer'])) {
            throw new InvalidArgumentException('At least one Offer is required');
        }

        foreach ($this->query['Offers']['Offer'] as $offer) {
            if (!isset($offer['OfferID']) || !isset($offer['OfferItemIDs'])) {
                throw new InvalidArgumentException('Invalid Offer structure. OfferID and OfferItemIDs are required');
            }
        }
    }

    protected function validatePreRequest(): void
    {
        // Validate DataLists if present
        if ($this->dataLists !== null) {
            if (isset($this->dataLists['FareList'])) {
                $this->validateFareList();
            }
            if (isset($this->dataLists['FlightSegmentList'])) {
                $this->validateFlightSegmentList();
            }
        }

        // Validate Travelers
        if ($this->travelers !== null) {
            $this->validateTravelers();
        }

        // Validate Shopping Response ID
        if ($this->shoppingResponseId !== null) {
            if (!isset($this->shoppingResponseId['ResponseID']['value'])) {
                throw new InvalidArgumentException('Invalid ShoppingResponseID structure');
            }
        }
    }

    protected function validateFareList(): void
    {
        if (!isset($this->dataLists['FareList']['FareGroup'])) {
            throw new InvalidArgumentException('FareGroup is required in FareList');
        }

        foreach ($this->dataLists['FareList']['FareGroup'] as $fareGroup) {
            if (!isset($fareGroup['ListKey']) || !isset($fareGroup['FareBasisCode']['Code'])) {
                throw new InvalidArgumentException('Invalid FareGroup structure. ListKey and FareBasisCode are required');
            }
        }
    }

    protected function validateFlightSegmentList(): void
    {
        if (!isset($this->dataLists['FlightSegmentList']['FlightSegment'])) {
            throw new InvalidArgumentException('FlightSegment is required in FlightSegmentList');
        }

        foreach ($this->dataLists['FlightSegmentList']['FlightSegment'] as $segment) {
            if (!isset($segment['SegmentKey']) || !isset($segment['Departure']) || !isset($segment['Arrival'])) {
                throw new InvalidArgumentException('Invalid FlightSegment structure');
            }
        }
    }

    protected function validateTravelers(): void
    {
        if (!isset($this->travelers['Traveler'])) {
            throw new InvalidArgumentException('At least one Traveler is required');
        }

        foreach ($this->travelers['Traveler'] as $traveler) {
            if (isset($traveler['AnonymousTraveler'])) {
                foreach ($traveler['AnonymousTraveler'] as $anonymous) {
                    if (!isset($anonymous['PTC']['value'])) {
                        throw new InvalidArgumentException('PTC is required for anonymous travelers');
                    }
                    if (!in_array($anonymous['PTC']['value'], ['ADT', 'CHD', 'INF'])) {
                        throw new InvalidArgumentException('Invalid PTC value. Must be ADT, CHD, or INF');
                    }
                }
            } elseif (isset($traveler['RecognizedTraveler'])) {
                $this->validateRecognizedTraveler($traveler['RecognizedTraveler']);
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

        if ($this->dataLists !== null) {
            $data['DataLists'] = $this->dataLists;
        }

        if ($this->travelers !== null) {
            $data['Travelers'] = $this->travelers;
        }

        if ($this->shoppingResponseId !== null) {
            $data['ShoppingResponseID'] = $this->shoppingResponseId;
        }

        return $data;
    }
}
