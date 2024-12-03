<?php

namespace Santosdave\VerteilWrapper\Logging;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Psr\Log\LogLevel;

class VerteilLogger
{
    protected string $channel;
    protected string $logPath;
    protected bool $enabled;

    public function __construct(string $channel = 'verteil')
    {
        $this->channel = Config::get('verteil.logging.channel', 'verteil');
        $this->logPath = Config::get('verteil.logging.path', storage_path('logs/verteil.log'));
        $this->enabled = Config::get('verteil.logging.enabled', true);

        // Ensure the verteil channel is configured
        $this->configureLoggingChannel();
    }

    /**
     * Configure the verteil logging channel
     */
    protected function configureLoggingChannel(): void
    {
        // Only configure if not already set in config/logging.php
        if (!Config::has('logging.channels.' . $this->channel)) {
            Config::set('logging.channels.' . $this->channel, [
                'driver' => 'single',
                'path' => $this->logPath,
                'level' => Config::get('verteil.logging.level', 'debug'),
            ]);
        }
    }

    /**
     * Log API request with stage information
     * 
     * @param string $endpoint
     * @param array $params
     * @return void
     */
    public function logRequest(string $endpoint, array $params): void
    {
        if (!$this->enabled) return;

        $stage = $params['stage'] ?? 'undefined';
        unset($params['stage']); // Remove stage from logged params

        $context = [
            'endpoint' => $endpoint,
            'stage' => $stage,
            'params' => $this->sanitizeLogData($params),
            'timestamp' => now()->toIso8601String(),
            'request_id' => uniqid('req_'),
        ];

        $message = sprintf(
            "API Request [%s] Stage: %s - Endpoint: %s",
            $context['request_id'],
            $stage,
            $endpoint
        );

        $this->log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * Log API response with enhanced context
     * 
     * @param string $endpoint
     * @param int $statusCode
     * @param array $response
     * @return void
     */
    public function logResponse(string $endpoint, int $statusCode, array $response): void
    {
        if (!$this->enabled) return;

        $context = [
            'endpoint' => $endpoint,
            'status_code' => $statusCode,
            'response' => $this->sanitizeLogData($response),
            'timestamp' => now()->toIso8601String(),
            'response_time' => defined('LARAVEL_START') ? round((microtime(true) - LARAVEL_START) * 1000, 2) : null,
        ];

        $message = sprintf(
            "API Response - Endpoint: %s, Status: %d, Time: %sms",
            $endpoint,
            $statusCode,
            $context['response_time']
        );

        $this->log($this->getLogLevelForStatus($statusCode), $message, $context);
    }

    /**
     * Determine appropriate log level based on status code
     */
    protected function getLogLevelForStatus(int $statusCode): string
    {
        if ($statusCode >= 500) {
            return LogLevel::ERROR;
        }
        if ($statusCode >= 400) {
            return LogLevel::WARNING;
        }
        return LogLevel::INFO;
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
            'context' => $this->sanitizeLogData($context),
            'timestamp' => now()->toIso8601String()
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
        if (!$this->enabled) return;

        $this->log(LogLevel::INFO, 'Authentication: ' . $event, array_merge(
            $this->sanitizeLogData($context),
            ['timestamp' => now()->toIso8601String()]
        ));
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
            'password',
            'token',
            'authorization',
            'credit_card',
            'card_number',
            'cvv',
            'secret',
            'api_key'
        ];

        array_walk_recursive($data, function (&$value, $key) use ($sensitiveFields) {
            if (is_string($key) && in_array(strtolower($key), $sensitiveFields)) {
                $value = '******';
            }
        });

        return $data;
    }
}
