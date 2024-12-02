<?php

namespace Santosdave\VerteilWrapper\Retry;

use Santosdave\VerteilWrapper\Exceptions\VerteilApiException;
use Illuminate\Support\Facades\Log;

class RetryHandler
{
    protected int $maxAttempts;
    protected int $delay;
    protected array $retryableStatusCodes = [408, 429, 500, 502, 503, 504];
    protected array $retryableExceptions = [
        \GuzzleHttp\Exception\ConnectException::class,
        \GuzzleHttp\Exception\ServerException::class,
        \GuzzleHttp\Exception\RequestException::class
    ];

    public function __construct(int $maxAttempts = 3, int $delay = 100)
    {
        $this->maxAttempts = $maxAttempts;
        $this->delay = $delay;
    }

    /**
     * Execute with retry logic
     */
    public function execute(callable $callback, string $context = ''): mixed
    {
        $attempt = 1;
        $lastException = null;

        while ($attempt <= $this->maxAttempts) {
            try {
                return $callback();
            } catch (\Exception $e) {
                $lastException = $e;

                if (!$this->shouldRetry($e)) {
                    throw $e;
                }

                $this->handleRetry($e, $attempt, $context);

                if ($attempt === $this->maxAttempts) {
                    throw new VerteilApiException(
                        "Max retry attempts reached: {$e->getMessage()}",
                        $e->getCode(),
                        $e
                    );
                }

                $this->wait($attempt);
                $attempt++;
            }
        }

        throw $lastException;
    }

    /**
     * Determine if exception is retryable
     */
    protected function shouldRetry(\Exception $e): bool
    {
        if ($e instanceof \GuzzleHttp\Exception\RequestException) {
            if ($e->hasResponse()) {
                return in_array($e->getResponse()->getStatusCode(), $this->retryableStatusCodes);
            }
        }

        foreach ($this->retryableExceptions as $exceptionClass) {
            if ($e instanceof $exceptionClass) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle retry attempt
     */
    protected function handleRetry(\Exception $e, int $attempt, string $context): void
    {
        Log::warning('Verteil API retry attempt', [
            'attempt' => $attempt,
            'context' => $context,
            'error' => $e->getMessage(),
            'code' => $e->getCode(),
            'next_attempt_in' => $this->getDelay($attempt) . 'ms'
        ]);
    }

    /**
     * Calculate delay for current attempt
     */
    protected function getDelay(int $attempt): int
    {
        // Exponential backoff with jitter
        $backoff = $this->delay * pow(2, $attempt - 1);
        return $backoff + random_int(0, min(1000, $backoff * 0.1));
    }

    /**
     * Wait before next attempt
     */
    protected function wait(int $attempt): void
    {
        usleep($this->getDelay($attempt) * 1000);
    }
}