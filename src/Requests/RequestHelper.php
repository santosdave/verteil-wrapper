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
        $dataLists = $params['DataLists'] ?? [];
        $query = $params['Query'] ?? [];
        $travelers = $params['Travelers'] ?? [];
        $shoppingResponseId = $params['ShoppingResponseID'] ?? [];

        // Optional parameters
        $party = $params['Party'] ?? null;
        $parameters = $params['Parameters'] ?? null;
        $qualifier = $params['Qualifier'] ?? null;
        $metadata = $params['Metadata'] ?? null;

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
