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
        $shoppingResponseId = $params['shoppingResponseID'] ?? [];

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
}
