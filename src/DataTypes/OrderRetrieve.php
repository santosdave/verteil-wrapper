<?php

namespace Santosdave\VerteilWrapper\DataTypes;

class OrderRetrieve 
{
    public static function create(array $params = []): array 
    {
        return [
            'Query' => [
                'Filters' => [
                    'OrderID' => self::createOrderId($params)
                ]
            ]
        ];
    }

    protected static function createOrderId(array $params): array 
    {
        $orderId = [
            'Owner' => $params['owner'],
            'value' => $params['orderId']
        ];

        if (isset($params['channel'])) {
            $orderId['Channel'] = $params['channel'];
        }

        if (isset($params['refs'])) {
            $orderId['refs'] = array_map(function($ref) {
                return ['Ref' => $ref];
            }, $params['refs']);
        }

        return $orderId;
    }
}