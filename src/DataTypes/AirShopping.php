<?php

namespace Santosdave\VerteilWrapper\DataTypes;

class AirShopping
{
    public static function create(array $params = []): array
    {
        $data = [
            'coreQuery' => isset($params['coreQuery']) ? $params['coreQuery'] : [],
            'travelers' => isset($params['travelers']) ? $params['travelers'] : [],
            'preference' => isset($params['preference']) ? $params['preference'] : [],
            'responseParameters' => isset($params['responseParameters']) ? $params['responseParameters'] : [],
            'enableGDS' => $params['enableGDS'] ?? null,
            'qualifier' => $params['qualifier'] ?? null
        ];

        return $data;
    }
}
