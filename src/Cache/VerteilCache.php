<?php

namespace Santosdave\VerteilWrapper\Cache;

use Illuminate\Support\Facades\Cache;
use Illuminate\Cache\TaggedCache;

class VerteilCache
{
    protected string $prefix = 'verteil_';
    protected ?TaggedCache $cache;
    protected array $cacheableEndpoints = [
        'airShopping' => 5, // minutes
        'seatAvailability' => 2,
        'serviceList' => 5,
        'flightPrice' => 2
    ];

    public function __construct()
    {
        $this->cache = Cache::tags(['verteil']);
    }

    /**
     * Get cached response if available
     */
    public function get(string $endpoint, array $params): ?array
    {
        if (!$this->isCacheable($endpoint)) {
            return null;
        }

        $key = $this->generateCacheKey($endpoint, $params);
        return $this->cache->get($key);
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

        $this->cache->put($key, $response, now()->addMinutes($ttl));
        $this->storeCacheKey($key);
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
     * Clear cached responses
     */
    public function clear(?string $endpoint = null): void
    {
        if (!$endpoint) {
            // Clear all verteil cache
            $this->cache->flush();
            return;
        }

        // Clear specific endpoint cache
        $keys = Cache::get($this->prefix . 'keys', []);
        $pattern = $this->prefix . $endpoint . '_*';

        foreach ($keys as $key) {
            if (fnmatch($pattern, $key)) {
                $this->cache->forget($key);
            }
        }
    }

    /**
     * Store cache key for later pattern matching
     */
    protected function storeCacheKey(string $key): void
    {
        $keys = Cache::get($this->prefix . 'keys', []);
        $keys[] = $key;
        Cache::put($this->prefix . 'keys', array_unique($keys));
    }
}
