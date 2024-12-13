<?php

namespace Santosdave\VerteilWrapper\Requests;

use InvalidArgumentException;
use Santosdave\VerteilWrapper\DataTypes\FlightPrice;

class FlightPriceRequest extends BaseRequest
{

    protected array $dataLists;
    protected array $query;
    protected array $travelers;
    protected array $shoppingResponseId;
    protected ?array $party;
    protected ?array $parameters;
    protected ?array $qualifier;
    protected ?array $metadata;

    public function __construct(
        array $dataLists,
        array $query,
        array $travelers,
        array $shoppingResponseId,
        ?array $party = null,
        ?array $parameters = null,
        ?array $qualifier = null,
        ?array $metadata = null,
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
        $this->parameters = $parameters;
        $this->qualifier = $qualifier;
        $this->metadata = $metadata;
    }

    public function getEndpoint(): string
    {
        return '/entrygate/rest/request:flightPrice';
    }

    public function getHeaders(): array
    {
        return array_filter([
            'service' => 'FlightPrice',
            'ThirdpartyId' => $this->data['third_party_id'] ?? null,
            'OfficeId' => $this->data['office_id'] ?? null,
        ]);
    }

    public function validate(): void
    {
        $this->validateDataLists();
        $this->validateQuery();
        $this->validateTravelers();
        $this->validateShoppingResponseId();

        if ($this->party !== null) {
            $this->validateParty();
        }

        if ($this->parameters !== null) {
            $this->validateParameters();
        }

        if ($this->qualifier !== null) {
            $this->validateQualifier();
        }
    }

    protected function validateDataLists(): void
    {
        if (!isset($this->dataLists['fares'])) {
            throw new InvalidArgumentException('Fares are required in DataLists');
        }

        foreach ($this->dataLists['fares'] as $fare) {
            if (!isset($fare['listKey']) || !isset($fare['code'])) {
                throw new InvalidArgumentException('Each fare must contain listKey and code');
            }
        }

        if (isset($this->dataLists['anonymousTravelers'])) {
            foreach ($this->dataLists['anonymousTravelers'] as $traveler) {
                if (!isset($traveler['objectKey']) || !isset($traveler['passengerType'])) {
                    throw new InvalidArgumentException('Each anonymous traveler must contain objectKey and passengerType');
                }

                if (isset($traveler['age'])) {
                    $this->validateAge($traveler['age']);
                }
            }
        }

        if (isset($this->dataLists['recognizedTravelers'])) {
            foreach ($this->dataLists['recognizedTravelers'] as $traveler) {
                if (!isset($traveler['objectKey']) || !isset($traveler['passengerType'])) {
                    throw new InvalidArgumentException('Each recognized traveler must contain objectKey and passengerType');
                }

                if (isset($traveler['frequentFlyer'])) {
                    foreach ($traveler['frequentFlyer'] as $fqtv) {
                        if (!isset($fqtv['airlineCode']) || !isset($fqtv['accountNumber'])) {
                            throw new InvalidArgumentException('Each frequent flyer entry must contain airlineCode and accountNumber');
                        }
                    }
                }
            }
        }
    }

    protected function validateQuery(): void
    {
        if (!isset($this->query['originDestinations'])) {
            throw new InvalidArgumentException('OriginDestinations are required in Query');
        }

        foreach ($this->query['originDestinations'] as $od) {
            if (!isset($od['flights'])) {
                throw new InvalidArgumentException('Flights are required in each OriginDestination');
            }

            foreach ($od['flights'] as $flight) {
                $this->validateFlight($flight);
            }
        }

        if (!isset($this->query['offers'])) {
            throw new InvalidArgumentException('Offers are required in Query');
        }

        foreach ($this->query['offers'] as $offer) {
            $this->validateOffer($offer);
        }
    }

    protected function validateFlight(array $flight): void
    {
        $required = ['segmentKey', 'departure', 'arrival', 'airlineCode', 'flightNumber'];
        foreach ($required as $field) {
            if (!isset($flight[$field])) {
                throw new InvalidArgumentException("Missing required flight field: $field");
            }
        }

        foreach (['departure', 'arrival'] as $point) {
            if (!isset($flight[$point]['airportCode']) || !isset($flight[$point]['date'])) {
                throw new InvalidArgumentException("$point must contain airportCode and date");
            }
        }

        // Validate airline code format
        if (!preg_match('/^[A-Z]{2}$/', $flight['airlineCode'])) {
            throw new InvalidArgumentException('Invalid airline code format');
        }

        // Validate flight number format
        if (!preg_match('/^\d{1,4}[A-Z]?$/', $flight['flightNumber'])) {
            throw new InvalidArgumentException('Invalid flight number format');
        }
    }

    protected function validateOffer(array $offer): void
    {
        if (!isset($offer['owner']) || !isset($offer['offerId']) || !isset($offer['offerItems'])) {
            throw new InvalidArgumentException('Each offer must contain owner, offerId, and offerItems');
        }

        foreach ($offer['offerItems'] as $item) {
            if (!isset($item['id'])) {
                throw new InvalidArgumentException('Each offer item must contain an id');
            }

            if (isset($item['selectedSeats'])) {
                foreach ($item['selectedSeats'] as $seat) {
                    if (
                        !isset($seat['segmentRefs']) || !isset($seat['travelerRef']) ||
                        !isset($seat['column']) || !isset($seat['row'])
                    ) {
                        throw new InvalidArgumentException('Invalid seat selection structure');
                    }
                }
            }
        }
    }

    protected function validateTravelers(): void
    {
        if (!isset($this->travelers) || empty($this->travelers)) {
            throw new InvalidArgumentException('At least one traveler is required');
        }

        foreach ($this->travelers as $traveler) {
            if (isset($traveler['frequentFlyer'])) {
                $this->validateFrequentFlyer($traveler['frequentFlyer']);
            }

            if (!isset($traveler['passengerType'])) {
                throw new InvalidArgumentException('Passenger type is required for each traveler');
            }

            if (!in_array($traveler['passengerType'], ['ADT', 'CHD', 'INF'])) {
                throw new InvalidArgumentException('Invalid passenger type. Must be ADT, CHD, or INF');
            }
        }
    }

    protected function validateFrequentFlyer(array $frequentFlyer): void
    {
        if (!isset($frequentFlyer['airlineCode']) || !isset($frequentFlyer['accountNumber'])) {
            throw new InvalidArgumentException('Frequent flyer must contain airlineCode and accountNumber');
        }

        if (isset($frequentFlyer['programId'])) {
            $validPrograms = ['Business', 'Discount Pass', 'Overseas France Pass'];
            if (!in_array($frequentFlyer['programId'], $validPrograms)) {
                throw new InvalidArgumentException('Invalid program ID');
            }
        }
    }

    protected function validateAge(array $age): void
    {
        if (isset($age['value'])) {
            if (!is_numeric($age['value']) || $age['value'] < 0 || $age['value'] > 17) {
                throw new InvalidArgumentException('Invalid age value for child passenger');
            }
        }

        if (isset($age['birthDate'])) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $age['birthDate'])) {
                throw new InvalidArgumentException('Invalid birth date format. Must be YYYY-MM-DD');
            }
        }
    }

    protected function validateShoppingResponseId(): void
    {
        if (!isset($this->shoppingResponseId['owner']) || !isset($this->shoppingResponseId['responseId'])) {
            throw new InvalidArgumentException('ShoppingResponseID must contain owner and responseId');
        }

        if (!preg_match('/^[A-Z]{2}$/', $this->shoppingResponseId['owner'])) {
            throw new InvalidArgumentException('Invalid airline code in ShoppingResponseID owner');
        }
    }

    protected function validateParty(): void
    {
        if (!isset($this->party['corporateCode'])) {
            throw new InvalidArgumentException('Corporate code is required in Party');
        }

        // Validate corporate code format (AIRLINE/DEAL or AIRLINE/DEAL/CLID)
        if (!preg_match('/^[A-Z]{2}(\/[A-Z0-9]+)?(\/[A-Z0-9]+)?$/', $this->party['corporateCode'])) {
            throw new InvalidArgumentException('Invalid corporate code format');
        }
    }

    protected function validateParameters(): void
    {
        if (isset($this->parameters['currency'])) {
            if (!preg_match('/^[A-Z]{3}$/', $this->parameters['currency'])) {
                throw new InvalidArgumentException('Invalid currency code format');
            }
        }
    }

    protected function validateQualifier(): void
    {
        if (isset($this->qualifier['programQualifiers'])) {
            foreach ($this->qualifier['programQualifiers'] as $prog) {
                if (!isset($prog['promoCode']) || !isset($prog['airlineCode'])) {
                    throw new InvalidArgumentException('Program qualifier must contain promoCode and airlineCode');
                }
            }
        }

        if (isset($this->qualifier['paymentCard'])) {
            $card = $this->qualifier['paymentCard'];

            if (!isset($card['brandCode']) || !isset($card['number'])) {
                throw new InvalidArgumentException('Payment card must contain brandCode and number');
            }

            $validBrands = ['AX', 'DS', 'DC', 'UP', 'JC', 'CA', 'TP', 'VI'];
            if (!in_array($card['brandCode'], $validBrands)) {
                throw new InvalidArgumentException('Invalid card brand code');
            }

            if (isset($card['productType']) && !in_array($card['productType'], ['P', 'C'])) {
                throw new InvalidArgumentException('Invalid card product type. Must be P (Personal) or C (Corporate)');
            }
        }
    }

    public function toArray(): array
    {
        return FlightPrice::create([
            'dataLists' => $this->dataLists,
            'query' => $this->query,
            'travelers' => $this->travelers,
            'shoppingResponseId' => $this->shoppingResponseId,
            'party' => $this->party,
            'parameters' => $this->parameters,
            'qualifier' => $this->qualifier,
            'metadata' => $this->metadata
        ]);
    }
}
