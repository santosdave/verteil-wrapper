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
     * @param  string  $scope  the account the limits apply to, so one account using up its
     *                         allowance never blocks another; empty for app-wide limits
     */
    public function __construct(string $scope = '')
    {
        if ($scope !== '') {
            $this->prefix .= $scope . '_';
        }
    }

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

        // A new window: set its expiry, and remember when it ends for retryAfter()
        if ($current === 0) {
            Cache::put($key, 1, now()->addSeconds($limit['duration']));
            Cache::put($key . ':ends_at', time() + $limit['duration'], now()->addSeconds($limit['duration']));
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
     *
     * From the end of the window recorded when it opened: Laravel's cache cannot report a
     * key's remaining lifetime (the getTimeToLive() called here before does not exist).
     */
    public function retryAfter(string $endpoint): int
    {
        $endsAt = Cache::get($this->getKey($endpoint) . ':ends_at');

        return $endsAt ? max(0, (int) $endsAt - time()) : 0;
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
        Cache::forget($this->getKey($endpoint) . ':ends_at');
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
