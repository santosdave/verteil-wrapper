<?php

namespace Santosdave\VerteilWrapper\Requests;

use Illuminate\Support\Facades\Config;
use InvalidArgumentException;

class AirShoppingRequest extends BaseRequest
{
    protected ?string $thirdPartyId;
    protected ?string $officeId;

    public function __construct(array $data)
    {
        parent::__construct($data);
        $this->thirdPartyId = $this->data['thirdPartyId'];
        $this->officeId = Config::get('verteil.office_id');
    }

    public function getEndpoint(): string
    {
        return '/entrygate/rest/request:airShopping';
    }

    public function getHeaders(): array
    {
        return array_filter([
            'service' => 'AirShopping',
            'ThirdpartyId' => $this->thirdPartyId,
            'OfficeId' => $this->officeId,
        ]);
    }

    public function validate(): void
    {
        $this->validateCoreQuery();
        $this->validateTravelers();

        if (!empty($this->data['preference'])) {
            $this->validatePreference();
        }

        if (!empty($this->data['responseParameters'])) {
            $this->validateResponseParameters();
        }
    }

    protected function validateCoreQuery(): void
    {
        if (!isset($this->data['coreQuery']['originDestinations'])) {
            throw new InvalidArgumentException('originDestinations is required in coreQuery');
        }

        foreach ($this->data['coreQuery']['originDestinations'] as $od) {
            if (
                !isset($od['departureAirport']) ||
                !isset($od['arrivalAirport']) ||
                !isset($od['departureDate']) ||
                !isset($od['key'])
            ) {
                throw new InvalidArgumentException('Invalid originDestination structure. Required: departureAirport, arrivalAirport, departureDate, key');
            }
        }
    }

    protected function validateTravelers(): void
    {
        if (!isset($this->data['travelers']) || empty($this->data['travelers'])) {
            throw new InvalidArgumentException('At least one traveler is required');
        }

        foreach ($this->data['travelers'] as $traveler) {
            if (!isset($traveler['passengerType'])) {
                throw new InvalidArgumentException('passengerType is required for each traveler');
            }

            if (!in_array($traveler['passengerType'], ['ADT', 'CHD', 'INF'])) {
                throw new InvalidArgumentException('Invalid passengerType. Must be ADT, CHD, or INF');
            }
        }
    }

    protected function validatePreference(): void
    {
        if (isset($this->data['preference']['cabin'])) {
            if (!in_array($this->data['preference']['cabin'], ['Y', 'W', 'C', 'F'])) {
                throw new InvalidArgumentException('Invalid cabin code. Must be Y, W, C, or F');
            }
        }

        if (isset($this->data['preference']['fareTypes'])) {
            $validFareTypes = ['PUBL', 'FLEX', 'PVT', 'IT', 'CB', 'STU', 'MR', 'HR', 'VFR', 'LBR', 'CRU'];
            foreach ($this->data['preference']['fareTypes'] as $fareType) {
                if (!in_array($fareType, $validFareTypes)) {
                    throw new InvalidArgumentException('Invalid fare type: ' . $fareType);
                }
            }
        }
    }

    protected function validateResponseParameters(): void
    {
        if (isset($this->data['responseParameters']['SortOrder'])) {
            foreach ($this->data['responseParameters']['SortOrder'] as $sort) {
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

        if (isset($this->data['responseParameters']['ShopResultPreference'])) {
            if (!in_array($this->data['responseParameters']['ShopResultPreference'], ['OPTIMIZED', 'FULL', 'BEST'])) {
                throw new InvalidArgumentException('Invalid ShopResultPreference. Must be OPTIMIZED, FULL, or BEST');
            }
        }
    }

    public function toArray(): array
    {
        return [
            'CoreQuery' => [
                'OriginDestinations' => [
                    'OriginDestination' => array_map(function ($od) {
                        return [
                            'Departure' => [
                                'AirportCode' => ['value' => $od['departureAirport']],
                                'Date' => $od['departureDate']
                            ],
                            'Arrival' => [
                                'AirportCode' => ['value' => $od['arrivalAirport']]
                            ],
                            'OriginDestinationKey' => $od['key']
                        ];
                    }, $this->data['coreQuery']['originDestinations'])
                ]
            ],
            'Travelers' => [
                'Traveler' => array_map(function ($traveler) {
                    if (isset($traveler['frequentFlyer'])) {
                        return [
                            'RecognizedTraveler' => [
                                'FQTVs' => [[
                                    'AirlineID' => ['value' => $traveler['frequentFlyer']['airlineCode']],
                                    'Account' => [
                                        'Number' => ['value' => $traveler['frequentFlyer']['accountNumber']]
                                    ]
                                ]],
                                'ObjectKey' => $traveler['objectKey'],
                                'PTC' => ['value' => $traveler['passengerType']],
                                'Name' => [
                                    'Given' => array_map(function ($given) {
                                        return ['value' => $given];
                                    }, $traveler['name']['given']),
                                    'Surname' => ['value' => $traveler['name']['surname']],
                                    'Title' => $traveler['name']['title']
                                ]
                            ]
                        ];
                    }
                    return [
                        'AnonymousTraveler' => [[
                            'PTC' => ['value' => $traveler['passengerType']]
                        ]]
                    ];
                }, $this->data['travelers'])
            ],
            'Preference' => [
                'CabinPreferences' => isset($this->data['preference']['cabin']) ? [
                    'CabinType' => [[
                        'Code' => $this->data['preference']['cabin']
                    ]]
                ] : null,
                'FarePreferences' => [
                    'Types' => [
                        'Type' => array_map(function ($type) {
                            return ['Code' => $type];
                        }, $this->data['preference']['fareTypes'] ?? ['PUBL'])
                    ]
                ]
            ],
            'ResponseParameters' => $this->data['responseParameters'] ?? [
                'SortOrder' => [
                    [
                        'Order' => 'ASCENDING',
                        'Parameter' => 'PRICE'
                    ]
                ],
                'ShopResultPreference' => 'OPTIMIZED'
            ],
            'EnableGDS' => $this->data['enableGDS'] ?? null
        ];
    }
}
