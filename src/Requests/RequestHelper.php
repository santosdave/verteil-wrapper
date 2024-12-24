<?php

namespace Santosdave\VerteilWrapper\Requests;

class RequestHelper
{
    /**
     * Transform raw parameters into appropriate request constructor arguments
     */
    public static function transformParameters(string $endpoint, array $params): array
    {
        switch ($endpoint) {
            case 'orderCreate':
                return self::transformOrderCreateParams($params);
            case 'flightPrice':
                return self::transformFlightPriceParams($params);
            case 'airShopping':
                return [$params]; // Existing behavior
                // Add other endpoints as needed
            default:
                return [$params];
        }
    }

    /**
     * Transform parameters for FlightPrice request
     */
    protected static function transformFlightPriceParams(array $params): array
    {
        // Extract required parameters with defaults
        $dataLists = $params['dataLists'] ?? [];
        $query = $params['query'] ?? [];
        $travelers = $params['travelers'] ?? [];
        $shoppingResponseId = $params['shoppingResponseId'] ?? [];

        // Optional parameters
        $party = $params['party'] ?? null;
        $parameters = $params['parameters'] ?? null;
        $qualifier = $params['qualifier'] ?? null;
        $metadata = $params['metadata'] ?? null;

        // Additional headers if present
        $thirdPartyId = $params['third_party_id'] ?? null;
        $officeId = $params['office_id'] ?? null;

        return [
            $dataLists,
            $query,
            $travelers,
            $shoppingResponseId,
            $party,
            $parameters,
            $qualifier,
            $metadata,
            $thirdPartyId,
            $officeId
        ];
    }

    /**
     * Transform parameters for OrderCreate request
     */
    protected static function transformOrderCreateParams(array $params): array
    {
        // Extract required query parameter
        $query = $params['query'] ?? [];

        // Extract optional parameters with defaults
        $party = $params['party'] ?? null;
        $payments = $params['payments'] ?? null;
        $commission = $params['commission'] ?? null;
        $metadata = $params['metadata'] ?? null;
        $thirdPartyId = $params['third_party_id'] ?? null;
        $officeId = $params['office_id'] ?? null;

        return [
            $query,
            $party,
            $payments,
            $commission,
            $metadata,
            $thirdPartyId,
            $officeId
        ];
    }
}
