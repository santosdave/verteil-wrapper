<?php

namespace Santosdave\VerteilWrapper\Responses;

class OrderViewResponse extends BaseResponse
{
    public function getOrderId(): string
    {
        return $this->data['Response']['Order'][0]['OrderID']['value'] ?? '';
    }

    public function getBookingReferences(): array
    {
        return $this->data['Response']['Order'][0]['BookingReferences'] ?? [];
    }

    public function getPassengers(): array
    {
        return $this->data['Response']['Passengers']['Passenger'] ?? [];
    }

    public function getTotalPrice(): float
    {
        return $this->data['Response']['Order'][0]['TotalOrderPrice']['SimpleCurrencyPrice']['value'] ?? 0.0;
    }

    public function getCurrency(): string
    {
        return $this->data['Response']['Order'][0]['TotalOrderPrice']['SimpleCurrencyPrice']['Code'] ?? '';
    }
}
