<?php

namespace Santosdave\VerteilWrapper\Responses;

class OrderChangeNotifResponse extends BaseResponse
{
    public function isAcknowledged(): bool
    {
        return isset($this->data['Response']['Acknowledgement']) && 
            $this->data['Response']['Acknowledgement']['value'] === 'OK';
    }

    public function getStatus(): string
    {
        return $this->data['Response']['Status'] ?? '';
    }

    public function getErrors(): array
    {
        if (!isset($this->data['Response']['Errors'])) {
            return [];
        }

        return array_map(function($error) {
            return [
                'code' => $error['Code'] ?? '',
                'type' => $error['Type'] ?? '',
                'description' => $error['Description'] ?? '',
                'status' => $error['Status'] ?? '',
                'tag' => $error['Tag'] ?? null
            ];
        }, $this->data['Response']['Errors']);
    }

    public function getWarnings(): array
    {
        if (!isset($this->data['Response']['Warnings'])) {
            return [];
        }

        return array_map(function($warning) {
            return [
                'code' => $warning['Code'] ?? '',
                'type' => $warning['Type'] ?? '',
                'description' => $warning['Description'] ?? '',
                'severity' => $warning['Severity'] ?? 'Info'
            ];
        }, $this->data['Response']['Warnings']);
    }

    public function getTimestamp(): ?string
    {
        return $this->data['Response']['Timestamp'] ?? null;
    }

    public function getCorrelationId(): ?string
    {
        return $this->data['Response']['CorrelationID'] ?? null;
    }
}