<?php

namespace Santosdave\VerteilWrapper\Responses;

class AirShoppingResponse extends BaseResponse
{
    public function getOffers(): array
    {
        return $this->data['OffersGroup']['AirlineOffers'] ?? [];
    }

    public function getFlightSegments(): array
    {
        return $this->data['DataLists']['FlightSegmentList']['FlightSegment'] ?? [];
    }

    public function getBaggageAllowance(): array
    {
        return $this->data['DataLists']['CheckedBagAllowanceList'] ?? [];
    }

    public function getResponseId(): string
    {
        return $this->data['ShoppingResponseID']['ResponseID']['value'] ?? '';
    }
}
