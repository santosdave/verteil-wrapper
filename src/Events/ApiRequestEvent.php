<?php

namespace Santosdave\VerteilWrapper\Events;

class ApiRequestEvent
{
    public string $endpoint;
    public array $parameters;
    public float $duration;
    
    public function __construct(string $endpoint, array $parameters, float $duration)
    {
        $this->endpoint = $endpoint;
        $this->parameters = $parameters;
        $this->duration = $duration;
    }
}

class ApiResponseEvent
{
    public string $endpoint;
    public array $response;
    public int $statusCode;
    public float $duration;
    
    public function __construct(string $endpoint, array $response, int $statusCode, float $duration)
    {
        $this->endpoint = $endpoint;
        $this->response = $response;
        $this->statusCode = $statusCode;
        $this->duration = $duration;
    }
}

class ApiErrorEvent
{
    public string $endpoint;
    public \Throwable $error;
    public ?array $context;
    
    public function __construct(string $endpoint, \Throwable $error, ?array $context = null)
    {
        $this->endpoint = $endpoint;
        $this->error = $error;
        $this->context = $context;
    }
}

class TokenRefreshEvent
{
    public string $newToken;
    public int $expiresIn;
    
    public function __construct(string $newToken, int $expiresIn)
    {
        $this->newToken = $newToken;
        $this->expiresIn = $expiresIn;
    }
}