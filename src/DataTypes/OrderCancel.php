<?php

namespace Santosdave\VerteilWrapper\DataTypes;

class OrderCancel 
{
    public static function create(array $params = []): array 
    {
        $request = [
            'Query' => [
                'OrderID' => self::createOrderIds($params['orders'])
            ]
        ];

        if (isset($params['expectedRefundAmount'])) {
            $request['ExpectedRefundAmount'] = self::createRefundAmount($params['expectedRefundAmount']);
        }

        if (isset($params['metadata'])) {
            $request['Metadata'] = self::createMetadata($params['metadata']);
        }

        if (isset($params['correlationId'])) {
            $request['CorrelationID'] = $params['correlationId'];
        }

        return $request;
    }

    protected static function createOrderIds(array $orders): array 
    {
        return array_map(function($order) {
            $orderId = [
                'Owner' => $order['owner'],
                'value' => $order['orderId']
            ];

            if (isset($order['channel'])) {
                $orderId['Channel'] = $order['channel'];
            }

            if (isset($order['refs'])) {
                $orderId['refs'] = array_map(function($ref) {
                    return ['Ref' => ['value' => $ref]];
                }, $order['refs']);
            }

            return $orderId;
        }, $orders);
    }

    protected static function createRefundAmount(array $refund): array 
    {
        return [
            'Total' => [
                'value' => $refund['amount'],
                'Code' => $refund['currency']
            ]
        ];
    }

    protected static function createMetadata(array $metadata): array 
    {
        return [
            'Other' => [
                'OtherMetadata' => array_map(function($meta) {
                    $metadataItem = [];

                    if (isset($meta['priceMetadata'])) {
                        $metadataItem['PriceMetadatas'] = [
                            'PriceMetadata' => array_map(function($price) {
                                return [
                                    'AugmentationPoint' => [
                                        'AugPoint' => $price['augmentationPoint']
                                    ],
                                    'MetadataKey' => $price['key']
                                ];
                            }, $meta['priceMetadata'])
                        ];
                    }

                    if (isset($meta['currencyMetadata'])) {
                        $metadataItem['CurrencyMetadatas'] = [
                            'CurrencyMetadata' => array_map(function($currency) {
                                return [
                                    'MetadataKey' => $currency['key'],
                                    'Decimals' => $currency['decimals']
                                ];
                            }, $meta['currencyMetadata'])
                        ];
                    }

                    return $metadataItem;
                }, $metadata)
            ]
        ];
    }
}