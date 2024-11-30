<?php

namespace Santosdave\VerteilWrapper\Requests;

abstract class BaseRequest
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    abstract public function getEndpoint(): string;
    
    abstract public function toArray(): array;

    public function getHeaders(): array
    {
        return [];
    }
}