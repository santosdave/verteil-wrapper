<?php

namespace Santosdave\VerteilWrapper\Responses;

class OrderViewResponse extends BaseResponse
{
    public function __construct(array $data)
    {
        parent::__construct($data);
    }

    public function toArray(): array
    {
        $data = $this->data ?? null;
        if (!$data) {
            return [];
        }

        return [
            'success' => $this->isSuccessful(),
            'orderId' => $this->getOrderId(),
            'BookingReferences' => $this->getBookingReferences(),
            'passengers' => $this->getPassengers(),
            'totalPrice' => $this->getTotalPrice(),
            'currency' => $this->getCurrency(),
            'response' => $data['Response'] ?? null,
            'errors' => $this->getErrors(),
        ];
    }

    public function isSuccessful(): bool
    {
        return empty($this->getErrors()) && !empty($this->getOrderId());
    }

    /**
     * Get errors from response
     */
    public function getErrors(): array
    {
        $errors = $this->data['Errors']['Error'] ?? [];
        return array_map(function ($error) {
            return [
                'code' => $error['Code'] ?? null,
                'short_text' => $error['ShortText'] ?? null,
                'message' => $error['value'] ?? null,
                'owner' => $error['Owner'] ?? null,
                'reason' => $error['Reason'] ?? null
            ];
        }, $errors);
    }



    public function getOrderId(): string
    {
        return $this->data['Response']['Order'][0]['OrderID']['value'] ?? '';
    }

    public function getBookingReferences(): array
    {
        return $this->data['Response']['Order'][0]['BookingReferences']['BookingReference'] ?? [];
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