<?php

namespace Santosdave\VerteilWrapper\Responses;

class SeatAvailabilityResponse extends BaseResponse
{
    public function getAvailableSeats(): array
    {
        return $this->data['DataLists']['SeatList']['Seats'] ?? [];
    }

    public function getFlightSegments(): array
    {
        return $this->data['DataLists']['FlightSegmentList']['FlightSegment'] ?? [];
    }

    public function getCabinLayout(): array
    {
        return $this->data['Flights'][0]['Cabin'] ?? [];
    }
}
