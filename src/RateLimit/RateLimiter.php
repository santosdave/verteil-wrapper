<?php

namespace Santosdave\VerteilWrapper\RateLimit;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;
use Santosdave\VerteilWrapper\Exceptions\VerteilApiException;

class RateLimiter
{
    protected string $prefix = 'verteil_ratelimit_';
    protected array $limits = [
        'default' => [
            'requests' => 60,
            'duration' => 60 // seconds
        ],
        'airShopping' => [
            'requests' => 30,
            'duration' => 60
        ],
        'orderCreate' => [
            'requests' => 20,
            'duration' => 60
        ]
    ];

    /**
     * Check if request can be executed
     */
    public function attempt(string $endpoint): bool
    {
        $key = $this->getKey($endpoint);
        $limit = $this->getLimit($endpoint);

        if (Redis::connection()->exists($key)) {
            $current = (int) Redis::connection()->get($key);
            if ($current >= $limit['requests']) {
                return false;
            }
            Redis::connection()->incr($key);
        } else {
            Redis::connection()->setex(
                $key,
                $limit['duration'],
                1
            );
        }

        return true;
    }

    /**
     * Get remaining requests
     */
    public function remaining(string $endpoint): int
    {
        $key = $this->getKey($endpoint);
        $limit = $this->getLimit($endpoint);

        if (!Redis::connection()->exists($key)) {
            return $limit['requests'];
        }

        $current = (int) Redis::connection()->get($key);
        return max(0, $limit['requests'] - $current);
    }

    /**
     * Get retry after time in seconds
     */
    public function retryAfter(string $endpoint): int
    {
        $key = $this->getKey($endpoint);
        return Redis::connection()->ttl($key);
    }

    protected function getKey(string $endpoint): string
    {
        return $this->prefix . $endpoint;
    }

    protected function getLimit(string $endpoint): array
    {
        return $this->limits[$endpoint] ?? $this->limits['default'];
    }
}
