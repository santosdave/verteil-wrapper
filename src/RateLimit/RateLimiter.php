<?php

namespace Santosdave\VerteilWrapper\RateLimit;

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

        $current = Cache::get($key, 0);
        
        if ($current >= $limit['requests']) {
            return false;
        }

        Cache::increment($key);
        
        // Set expiration if key is new
        if ($current === 0) {
            Cache::put($key, 1, now()->addSeconds($limit['duration']));
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
        $current = Cache::get($key, 0);

        return max(0, $limit['requests'] - $current);
    }

    /**
     * Get retry after time in seconds
     */
    public function retryAfter(string $endpoint): int
    {
        $key = $this->getKey($endpoint);
        
        // Get cache metadata to determine TTL
        $ttl = Cache::getTimeToLive($key);
        
        return $ttl ? (int) $ttl : 0;
    }

    protected function getKey(string $endpoint): string
    {
        return $this->prefix . $endpoint;
    }

    protected function getLimit(string $endpoint): array
    {
        return $this->limits[$endpoint] ?? $this->limits['default'];
    }

    /**
     * Clear rate limit for an endpoint
     */
    public function clear(string $endpoint): void
    {
        Cache::forget($this->getKey($endpoint));
    }

    /**
     * Clear all rate limits
     */
    public function clearAll(): void
    {
        foreach (array_keys($this->limits) as $endpoint) {
            $this->clear($endpoint);
        }
    }
}