<?php

namespace Santosdave\VerteilWrapper\Responses;

class ServiceListResponse extends BaseResponse
{
    public function getServices(): array
    {
        $services = [];
        
        if (isset($this->data['Response']['ServiceList'])) {
            foreach ($this->data['Response']['ServiceList'] as $service) {
                $services[] = [
                    'serviceId' => $service['ServiceID']['value'] ?? '',
                    'type' => $service['ServiceType'] ?? '',
                    'name' => $service['Name'] ?? '',
                    'description' => $this->extractDescription($service['Descriptions'] ?? []),
                    'price' => $this->extractPrice($service['Price'] ?? []),
                    'segmentRefs' => $service['SegmentRefs'] ?? [],
                    'passengerRefs' => $service['PassengerRefs'] ?? [],
                    'availability' => $this->extractAvailability($service['Availability'] ?? []),
                    'media' => $this->extractMedia($service['MediaObjects'] ?? [])
                ];
            }
        }

        return $services;
    }

    protected function extractDescription(array $descriptions): string
    {
        return isset($descriptions[0]['Text']) ? $descriptions[0]['Text'] : '';
    }

    protected function extractPrice(array $price): array
    {
        return [
            'amount' => $price['TotalAmount']['value'] ?? 0.0,
            'currency' => $price['TotalAmount']['Code'] ?? '',
            'baseAmount' => $price['BaseAmount']['value'] ?? 0.0,
            'taxes' => array_map(function($tax) {
                return [
                    'code' => $tax['TaxCode'] ?? '',
                    'amount' => $tax['Amount']['value'] ?? 0.0,
                    'currency' => $tax['Amount']['Code'] ?? ''
                ];
            }, $price['Taxes']['Tax'] ?? [])
        ];
    }

    protected function extractAvailability(array $availability): array
    {
        return [
            'status' => $availability['AvailabilityStatus'] ?? '',
            'quantity' => $availability['AvailableQuantity'] ?? null,
            'limitations' => array_map(function($limitation) {
                return [
                    'type' => $limitation['LimitationType'] ?? '',
                    'value' => $limitation['Value'] ?? '',
                    'description' => $limitation['Description'] ?? null
                ];
            }, $availability['Limitations']['Limitation'] ?? [])
        ];
    }

    protected function extractMedia(array $mediaObjects): array
    {
        return array_map(function($media) {
            return [
                'id' => $media['ID'] ?? '',
                'url' => $media['URI'] ?? '',
                'type' => $media['MediaType'] ?? '',
                'format' => $media['Format'] ?? null,
                'width' => $media['Width'] ?? null,
                'height' => $media['Height'] ?? null,
                'title' => $media['Title'] ?? null,
                'description' => $media['Description'] ?? null
            ];
        }, $mediaObjects);
    }

    public function getServiceGroups(): array
    {
        $groups = [];
        
        if (isset($this->data['Response']['ServiceGroups'])) {
            foreach ($this->data['Response']['ServiceGroups'] as $group) {
                $groups[] = [
                    'groupId' => $group['GroupID'] ?? '',
                    'name' => $group['Name'] ?? '',
                    'description' => $this->extractDescription($group['Descriptions'] ?? []),
                    'serviceRefs' => $group['ServiceRefs'] ?? [],
                    'category' => $group['ServiceCategory'] ?? null
                ];
            }
        }

        return $groups;
    }

    public function getServiceBundles(): array
    {
        $bundles = [];
        
        if (isset($this->data['Response']['ServiceBundles'])) {
            foreach ($this->data['Response']['ServiceBundles'] as $bundle) {
                $bundles[] = [
                    'bundleId' => $bundle['BundleID']['value'] ?? '',
                    'name' => $bundle['Name'] ?? '',
                    'description' => $this->extractDescription($bundle['Descriptions'] ?? []),
                    'services' => array_map(function($service) {
                        return [
                            'serviceRef' => $service['ServiceRef'] ?? '',
                            'includedQuantity' => $service['IncludedQuantity'] ?? 1,
                            'mandatory' => $service['Mandatory'] ?? false
                        ];
                    }, $bundle['Services'] ?? []),
                    'price' => $this->extractPrice($bundle['Price'] ?? [])
                ];
            }
        }

        return $bundles;
    }

    public function getServiceFeatures(): array
    {
        return isset($this->data['Response']['ServiceFeatures']) ?
            array_map(function($feature) {
                return [
                    'featureId' => $feature['FeatureID'] ?? '',
                    'name' => $feature['Name'] ?? '',
                    'description' => $this->extractDescription($feature['Descriptions'] ?? []),
                    'serviceRefs' => $feature['ServiceRefs'] ?? [],
                    'value' => $feature['Value'] ?? null,
                    'unit' => $feature['Unit'] ?? null
                ];
            }, $this->data['Response']['ServiceFeatures']) : [];
    }

    public function getValidationMessages(): array
    {
        return isset($this->data['Response']['Validations']) ?
            array_map(function($validation) {
                return [
                    'type' => $validation['Type'] ?? '',
                    'status' => $validation['Status'] ?? '',
                    'message' => $validation['Message'] ?? '',
                    'serviceRefs' => $validation['ServiceRefs'] ?? []
                ];
            }, $this->data['Response']['Validations']) : [];
    }

    public function getCorrelationId(): ?string
    {
        return $this->data['Response']['CorrelationID'] ?? null;
    }
}