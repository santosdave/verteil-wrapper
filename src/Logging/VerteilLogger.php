<?php

namespace Santosdave\VerteilWrapper\Logging;

use Illuminate\Support\Facades\Log;
use Psr\Log\LogLevel;

class VerteilLogger
{
    protected string $channel;

    public function __construct(string $channel = 'verteil')
    {
        $this->channel = $channel;
    }

    /**
     * Log API request
     * 
     * @param string $endpoint
     * @param array $params
     * @return void
     */
    public function logRequest(string $endpoint, array $params): void
    {
        $this->log(LogLevel::DEBUG, 'API Request', [
            'endpoint' => $endpoint,
            'params' => $this->sanitizeLogData($params)
        ]);
    }

    /**
     * Log API response
     * 
     * @param string $endpoint
     * @param int $statusCode
     * @param array $response
     * @return void
     */
    public function logResponse(string $endpoint, int $statusCode, array $response): void
    {
        $this->log(LogLevel::DEBUG, 'API Response', [
            'endpoint' => $endpoint,
            'status_code' => $statusCode,
            'response' => $this->sanitizeLogData($response)
        ]);
    }

    /**
     * Log API error
     * 
     * @param string $endpoint
     * @param \Throwable $error
     * @param array $context
     * @return void
     */
    public function logError(string $endpoint, \Throwable $error, array $context = []): void
    {
        $this->log(LogLevel::ERROR, 'API Error', [
            'endpoint' => $endpoint,
            'message' => $error->getMessage(),
            'code' => $error->getCode(),
            'file' => $error->getFile(),
            'line' => $error->getLine(),
            'trace' => $error->getTraceAsString(),
            'context' => $this->sanitizeLogData($context)
        ]);
    }

    /**
     * Log authentication events
     * 
     * @param string $event
     * @param array $context
     * @return void
     */
    public function logAuth(string $event, array $context = []): void
    {
        $this->log(LogLevel::INFO, 'Authentication: ' . $event, $this->sanitizeLogData($context));
    }

    /**
     * Write to log
     * 
     * @param string $level
     * @param string $message
     * @param array $context
     * @return void
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        Log::channel($this->channel)->log($level, $message, $context);
    }

    /**
     * Sanitize sensitive data before logging
     * 
     * @param array $data
     * @return array
     */
    protected function sanitizeLogData(array $data): array
    {
        $sensitiveFields = [
            'password', 'token', 'authorization', 'credit_card', 'card_number', 
            'cvv', 'secret', 'api_key'
        ];

        array_walk_recursive($data, function(&$value, $key) use ($sensitiveFields) {
            if (is_string($key) && in_array(strtolower($key), $sensitiveFields)) {
                $value = '******';
            }
        });

        return $data;
    }
}