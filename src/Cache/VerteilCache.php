<?php

namespace Santosdave\VerteilWrapper\Cache;

use Illuminate\Support\Facades\Cache;

class VerteilCache
{
    protected string $prefix = 'verteil_';
    protected array $cacheableEndpoints = [
        'airShopping' => 5, // minutes
        'seatAvailability' => 2,
        'serviceList' => 5,
        'flightPrice' => 2
    ];

    /**
     * Get cached response if available
     */
    public function get(string $endpoint, array $params): ?array
    {
        if (!$this->isCacheable($endpoint)) {
            return null;
        }

        $key = $this->generateCacheKey($endpoint, $params);
        return Cache::get($key);
    }

    /**
     * Cache API response
     */
    public function put(string $endpoint, array $params, array $response): void
    {
        if (!$this->isCacheable($endpoint)) {
            return;
        }

        $key = $this->generateCacheKey($endpoint, $params);
        $ttl = $this->getCacheDuration($endpoint);

        Cache::put($key, $response, now()->addMinutes($ttl));
        $this->storeCacheKeys($key);
    }

    /**
     * Check if endpoint is cacheable
     */
    protected function isCacheable(string $endpoint): bool
    {
        return isset($this->cacheableEndpoints[$endpoint]);
    }

    /**
     * Get cache duration for endpoint
     */
    protected function getCacheDuration(string $endpoint): int
    {
        return $this->cacheableEndpoints[$endpoint] ?? 0;
    }

    /**
     * Generate unique cache key
     */
    protected function generateCacheKey(string $endpoint, array $params): string
    {
        $paramHash = md5(json_encode($params));
        return $this->prefix . $endpoint . '_' . $paramHash;
    }

    /**
     * Store cache keys for tracking
     */
    protected function storeCacheKeys(string $key): void
    {
        $keys = Cache::get($this->prefix . 'keys', []);
        $keys[] = $key;
        Cache::put($this->prefix . 'keys', array_unique($keys), now()->addDays(1));
    }

    /**
     * Clear cached responses
     */
    public function clear(?string $endpoint = null): void
    {
        if (!$endpoint) {
            // Get all keys and clear them
            $keys = Cache::get($this->prefix . 'keys', []);
            foreach ($keys as $key) {
                Cache::forget($key);
            }
            Cache::forget($this->prefix . 'keys');
            return;
        }

        // Clear specific endpoint cache
        $keys = Cache::get($this->prefix . 'keys', []);
        $pattern = $this->prefix . $endpoint . '_';

        foreach ($keys as $key) {
            if (strpos($key, $pattern) === 0) {
                Cache::forget($key);
            }
        }
    }
}
