<?php

namespace Santosdave\VerteilWrapper\Logging;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Psr\Log\LogLevel;

use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\JsonFormatter;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\WebProcessor;

class VerteilLogger
{
    protected Logger $logger;
    protected string $channel;
    protected string $logPath;
    protected bool $enabled;
    protected int $maxDepth;

    public function __construct(string $channel = 'verteil')
    {
        $this->channel = Config::get('verteil.logging.channel', 'verteil');
        $this->logPath = Config::get('verteil.logging.path', storage_path('logs/verteil.log'));
        $this->enabled = Config::get('verteil.logging.enabled', true);
        $this->maxDepth = Config::get('verteil.logging.max_depth', 10);

        // Ensure the verteil channel is configured
        $this->initializeLogger();
    }

    /**
     * Configure the verteil logging channel
     */
    protected function initializeLogger(): void
    {
        // Create a new Logger instance
        $this->logger = new Logger($this->channel);

        // Create rotating file handler
        $handler = new RotatingFileHandler(
            $this->logPath,
            Config::get('verteil.logging.days_to_keep', 30),
            Logger::DEBUG
        );

        // Use JSON formatter with max depth control
        $jsonFormatter = new JsonFormatter();
        $jsonFormatter->setMaxNormalizeDepth($this->maxDepth);
        $handler->setFormatter($jsonFormatter);

        // Add processors for extra context
        $this->logger->pushProcessor(new IntrospectionProcessor());
        $this->logger->pushProcessor(new WebProcessor());

        // Add handler to logger
        $this->logger->pushHandler($handler);
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

        $this->logger->debug($message, $context);
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

        $level = $this->getLogLevelForStatus($statusCode);
        $this->logger->log($level, $message, $context);
    }

    /**
     * Determine appropriate log level based on status code
     */
    protected function getLogLevelForStatus(int $statusCode): string
    {
        if ($statusCode >= 500) {
            return Logger::ERROR;
        }
        if ($statusCode >= 400) {
            return Logger::WARNING;
        }
        return Logger::INFO;
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
        $errorContext = $this->normalizeContext([
            'endpoint' => $endpoint,
            'message' => $error->getMessage(),
            'code' => $error->getCode(),
            'file' => $error->getFile(),
            'line' => $error->getLine(),
            'trace' => $this->normalizeTrace($error->getTrace()),
            'context' => $this->sanitizeLogData($context),
            'timestamp' => now()->toIso8601String()
        ]);

        $this->logger->error('API Error', $errorContext);
    }

    protected function normalizeContext(array $context): array
    {
        array_walk_recursive($context, function (&$value) {
            if (is_object($value)) {
                $value = method_exists($value, '__toString') ? (string)$value : get_class($value);
            } elseif (is_resource($value)) {
                $value = get_resource_type($value);
            }
        });

        return $context;
    }

    protected function normalizeTrace(array $trace): array
    {
        return array_map(function ($item) {
            return array_intersect_key($item, array_flip(['file', 'line', 'function', 'class']));
        }, array_slice($trace, 0, 10)); // Only keep first 10 trace items
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
    protected function sanitizeLogData(array $data, int $depth = 0): array
    {
        if ($depth >= $this->maxDepth) {
            return ['warning' => 'Max depth reached, data truncated'];
        }

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

        $result = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), $sensitiveFields)) {
                $result[$key] = '******';
            } elseif (is_array($value)) {
                $result[$key] = $this->sanitizeLogData($value, $depth + 1);
            } elseif (is_object($value)) {
                $result[$key] = get_class($value);
            } elseif (is_resource($value)) {
                $result[$key] = get_resource_type($value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}