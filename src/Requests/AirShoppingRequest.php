<?php

namespace Santosdave\VerteilWrapper\Requests;

use InvalidArgumentException;

class AirShoppingRequest extends BaseRequest
{

    /** @var array Fare and cabin preferences */
    public array $preference;

    /** @var array Response parameters for sorting and filtering results */
    public array $responseParameters;

    /** @var array Array of traveler details */
    public array $travelers;

    /** @var array Core query parameters including origin/destination details */
    public array $coreQuery;

    /** @var bool Enable GDS flag */
    public ?bool $enableGDS;

    /** @var array|null Program qualifiers for promotions */
    public ?array $qualifier;

    public function __construct(
        array $coreQuery,
        array $travelers,
        array $responseParameters = [],
        array $preference = [],
        ?bool $enableGDS = null,
        ?array $qualifier = null
    ) {
        $this->coreQuery = $coreQuery;
        $this->travelers = $travelers;
        $this->responseParameters = $responseParameters;
        $this->preference = $preference;
        $this->enableGDS = $enableGDS;
        $this->qualifier = $qualifier;
    }

    public function getEndpoint(): string
    {
        return '/entrygate/rest/request:airShopping';
    }

    public function getHeaders(): array
    {
        return [
            'service' => 'AirShopping'
        ];
    }

    public function validate(): void
    {
        $this->validateCoreQuery();
        $this->validateTravelers();
        $this->validateResponseParameters();
        if (!empty($this->preference)) {
            $this->validatePreference();
        }
        if (!empty($this->qualifier)) {
            $this->validateQualifier();
        }
    }

    protected function validateCoreQuery(): void
    {
        if (empty($this->coreQuery['OriginDestinations'])) {
            throw new InvalidArgumentException('OriginDestinations is required in CoreQuery');
        }

        // Validate OriginDestination structure
        if (!empty($this->coreQuery['OriginDestinations']['OriginDestination'])) {
            foreach ($this->coreQuery['OriginDestinations']['OriginDestination'] as $od) {
                if (
                    !isset($od['Departure']['AirportCode']['value']) ||
                    !isset($od['Arrival']['AirportCode']['value']) ||
                    !isset($od['Departure']['Date']) ||
                    !isset($od['OriginDestinationKey'])
                ) {
                    throw new InvalidArgumentException('Invalid OriginDestination structure');
                }
            }
        }
    }

    protected function validateTravelers(): void
    {
        if (empty($this->travelers['Traveler'])) {
            throw new InvalidArgumentException('At least one traveler is required');
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
            }
        }
    }

    protected function validateResponseParameters(): void
    {
        if (!empty($this->responseParameters)) {
            if (!empty($this->responseParameters['++++++++++++'])) {
                foreach ($this->responseParameters['SortOrder'] as $sort) {
                    if (!isset($sort['Order']) || !isset($sort['Parameter'])) {
                        throw new InvalidArgumentException('Sort order must contain Order and Parameter');
                    }
                    if (!in_array($sort['Order'], ['ASCENDING', 'DESCENDING'])) {
                        throw new InvalidArgumentException('Invalid sort order. Must be ASCENDING or DESCENDING');
                    }
                    if (!in_array($sort['Parameter'], ['STOP', 'PRICE', 'DEPARTURE_TIME'])) {
                        throw new InvalidArgumentException('Invalid sort parameter. Must be STOP, PRICE, or DEPARTURE_TIME');
                    }
                }
            }

            if (!empty($this->responseParameters['ShopResultPreference'])) {
                if (!in_array($this->responseParameters['ShopResultPreference'], ['OPTIMIZED', 'FULL', 'BEST'])) {
                    throw new InvalidArgumentException('Invalid ShopResultPreference. Must be OPTIMIZED, FULL, or BEST');
                }
            }
        }
    }
    

    protected function validatePreference(): void
    {
        if (isset($this->preference['CabinPreferences'])) {
            if (!isset($this->preference['CabinPreferences']['CabinType'])) {
                throw new InvalidArgumentException('CabinType is required when specifying CabinPreferences');
            }
            foreach ($this->preference['CabinPreferences']['CabinType'] as $cabin) {
                if (!isset($cabin['Code'])) {
                    throw new InvalidArgumentException('Cabin code is required');
                }
                if (!in_array($cabin['Code'], ['Y', 'W', 'C', 'F'])) {
                    throw new InvalidArgumentException('Invalid cabin code. Must be Y, W, C, or F');
                }
            }
        }
    }

    protected function validateQualifier(): void
    {
        if (isset($this->qualifier['ProgramQualifiers'])) {
            if (!isset($this->qualifier['ProgramQualifiers']['ProgramQualifier'])) {
                throw new InvalidArgumentException('ProgramQualifier is required when specifying ProgramQualifiers');
            }
            foreach ($this->qualifier['ProgramQualifiers']['ProgramQualifier'] as $qual) {
                if (
                    !isset($qual['DiscountProgramQualifier']['Account']['value']) ||
                    !isset($qual['DiscountProgramQualifier']['AssocCode']['value']) ||
                    !isset($qual['DiscountProgramQualifier']['Name']['value'])
                ) {
                    throw new InvalidArgumentException('Invalid DiscountProgramQualifier structure');
                }
            }
        }
    }

    public function toArray(): array
    {
        $data = [
            'CoreQuery' => $this->coreQuery,
            'Travelers' => $this->travelers,
            'ResponseParameters' => $this->responseParameters,
            'Preference' => $this->preference
        ];

        if ($this->enableGDS !== null) {
            $data['EnableGDS'] = $this->enableGDS;
        }

        if ($this->qualifier !== null) {
            $data['Qualifier'] = $this->qualifier;
        }

        return $data;
    }
}