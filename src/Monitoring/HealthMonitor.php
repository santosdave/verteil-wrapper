<?php

namespace Santosdave\VerteilWrapper\Monitoring;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Santosdave\VerteilWrapper\Security\SecureTokenStorage;

class HealthMonitor
{
    protected array $metrics = [];
    protected int $metricsRetention = 24; // hours
    protected SecureTokenStorage $tokenStorage;

    public function __construct(SecureTokenStorage $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
    }


    /**
     * Check overall API health
     */
    public function checkHealth(): array
    {
        return [
            'status' => $this->getOverallStatus(),
            'uptime' => $this->getUptime(),
            'metrics' => $this->getMetricsSummary(),
            'rate_limits' => $this->getRateLimitStatus(),
            'last_errors' => $this->getRecentErrors(),
            'cache_status' => $this->getCacheStatus(),
            'token_status' => $this->getTokenStatus()
        ];
    }


    /**
     * Get uptime and performance metrics
     */
    protected function getUptime(): array
    {
        $uptimeFile = storage_path('logs/verteil_uptime.log');

        if (!file_exists($uptimeFile)) {
            return [
                'status' => 'unknown',
                'uptime_percentage' => 0,
                'last_downtime' => null
            ];
        }

        $logs = collect(file($uptimeFile))
            ->map(fn($line) => json_decode($line, true))
            ->filter();

        $total = $logs->count();
        if ($total === 0) {
            return [
                'status' => 'unknown',
                'uptime_percentage' => 0,
                'last_downtime' => null
            ];
        }

        $successful = $logs->where('status', 'up')->count();
        $uptimePercentage = ($successful / $total) * 100;

        $lastDowntime = $logs
            ->where('status', 'down')
            ->sortByDesc('timestamp')
            ->first();

        return [
            'status' => $uptimePercentage > 99 ? 'healthy' : ($uptimePercentage > 95 ? 'degraded' : 'critical'),
            'uptime_percentage' => round($uptimePercentage, 2),
            'last_downtime' => $lastDowntime ? date('Y-m-d H:i:s', $lastDowntime['timestamp']) : null
        ];
    }

    /**
     * Get rate limit status
     */
    protected function getRateLimitStatus(): array
    {
        $limits = Cache::get('verteil_rate_limits', []);

        return collect($limits)->map(function ($limit, $endpoint) {
            return [
                'endpoint' => $endpoint,
                'limit' => $limit['limit'],
                'remaining' => $limit['remaining'],
                'resets_at' => date('Y-m-d H:i:s', $limit['reset_time']),
                'status' => $this->getRateLimitHealthStatus($limit)
            ];
        })->values()->all();
    }

    /**
     * Get recent errors
     */
    protected function getRecentErrors(): array
    {
        return Cache::remember('verteil_recent_errors', 60, function () {
            $logFile = storage_path('logs/verteil.log');
            if (!file_exists($logFile)) {
                return [];
            }

            $errors = collect(file($logFile))
                ->map(fn($line) => json_decode($line, true))
                ->filter(fn($log) => isset($log['level']) && $log['level'] === 'error')
                ->sortByDesc('timestamp')
                ->take(10)
                ->map(function ($error) {
                    return [
                        'timestamp' => date('Y-m-d H:i:s', $error['timestamp']),
                        'message' => $error['message'],
                        'endpoint' => $error['context']['endpoint'] ?? 'unknown',
                        'code' => $error['context']['code'] ?? null
                    ];
                })->values()->all();

            return $errors;
        });
    }

    /**
     * Store metric in cache
     */
    protected function storeMetric(array $metric): void
    {
        $key = 'verteil_metrics';
        $metrics = Cache::get($key, []);

        // Add new metric
        array_push($metrics, $metric);

        // Remove old metrics
        $cutoff = now()->subHours($this->metricsRetention)->timestamp;
        $metrics = array_filter($metrics, fn($m) => $m['timestamp'] > $cutoff);

        Cache::put($key, $metrics, now()->addHours($this->metricsRetention));
    }

    /**
     * Get cache status
     */
    protected function getCacheStatus(): array
    {
        $cacheStats = Cache::get('verteil_cache_stats', [
            'hits' => 0,
            'misses' => 0,
            'size' => 0
        ]);

        $hitRate = $cacheStats['hits'] + $cacheStats['misses'] > 0
            ? ($cacheStats['hits'] / ($cacheStats['hits'] + $cacheStats['misses'])) * 100
            : 0;

        return [
            'status' => $hitRate > 80 ? 'optimal' : ($hitRate > 50 ? 'acceptable' : 'suboptimal'),
            'hit_rate' => round($hitRate, 2),
            'hits' => $cacheStats['hits'],
            'misses' => $cacheStats['misses'],
            'size' => $this->formatBytes($cacheStats['size']),
            'items_count' => Cache::tags(['verteil'])->count()
        ];
    }

    /**
     * Get authentication token status
     */
    protected function getTokenStatus(): array
    {
        $hasToken = $this->tokenStorage->hasValidToken();
        $token = $this->tokenStorage->retrieveToken();

        if (!$hasToken || !$token) {
            return [
                'status' => 'missing',
                'valid' => false,
                'expires_in' => null
            ];
        }

        return [
            'status' => 'active',
            'valid' => true,
            'expires_in' => Cache::get('verteil_token_expiry')
                ? now()->diffInMinutes(Cache::get('verteil_token_expiry'))
                : null
        ];
    }

    /**
     * Perform API latency test
     */
    protected function measureLatency(): array
    {
        $measurements = collect(range(1, 3))->map(function () {
            $start = microtime(true);

            try {
                Http::get(config('verteil.base_url') . '/health');
                $duration = (microtime(true) - $start) * 1000;
                return $duration;
            } catch (\Exception $e) {
                return null;
            }
        })->filter()->values();

        if ($measurements->isEmpty()) {
            return [
                'status' => 'error',
                'average' => null,
                'min' => null,
                'max' => null
            ];
        }

        return [
            'status' => $this->getLatencyStatus($measurements->average()),
            'average' => round($measurements->average(), 2),
            'min' => round($measurements->min(), 2),
            'max' => round($measurements->max(), 2)
        ];
    }

    /**
     * Get endpoint performance metrics
     */
    protected function getEndpointMetrics(): array
    {
        return collect(Cache::get('verteil_endpoint_metrics', []))
            ->map(function ($metrics, $endpoint) {
                $avgResponseTime = collect($metrics['response_times'])->average();
                return [
                    'endpoint' => $endpoint,
                    'success_rate' => round(($metrics['success'] / ($metrics['success'] + $metrics['failures'])) * 100, 2),
                    'avg_response_time' => round($avgResponseTime, 2),
                    'requests_per_minute' => round($metrics['requests_per_minute'], 2),
                    'status' => $this->getEndpointStatus($avgResponseTime, $metrics['success_rate'])
                ];
            })->values()->all();
    }

    /**
     * Helper methods for status determination
     */
    protected function getRateLimitHealthStatus(array $limit): string
    {
        $remainingPercentage = ($limit['remaining'] / $limit['limit']) * 100;

        if ($remainingPercentage < 10) {
            return 'critical';
        } elseif ($remainingPercentage < 30) {
            return 'warning';
        }
        return 'good';
    }

    protected function getLatencyStatus(float $avgLatency): string
    {
        if ($avgLatency > 1000) {
            return 'critical';
        } elseif ($avgLatency > 500) {
            return 'warning';
        }
        return 'good';
    }

    protected function getEndpointStatus(float $avgResponseTime, float $successRate): string
    {
        if ($successRate < 95 || $avgResponseTime > 2000) {
            return 'critical';
        } elseif ($successRate < 98 || $avgResponseTime > 1000) {
            return 'warning';
        }
        return 'healthy';
    }

    /**
     * Format bytes to human readable format
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Record API metrics for monitoring
     */
    public function recordMetric(string $endpoint, float $duration, int $statusCode): void
    {
        $metric = [
            'timestamp' => now()->timestamp,
            'endpoint' => $endpoint,
            'duration' => $duration,
            'status_code' => $statusCode,
            'memory_usage' => memory_get_usage(true)
        ];

        $this->storeMetric($metric);
        $this->updateEndpointMetrics($endpoint, $duration, $statusCode);
    }

    /**
     * Update endpoint-specific metrics
     */
    protected function updateEndpointMetrics(string $endpoint, float $duration, int $statusCode): void
    {
        $metrics = Cache::get('verteil_endpoint_metrics', []);

        if (!isset($metrics[$endpoint])) {
            $metrics[$endpoint] = [
                'success' => 0,
                'failures' => 0,
                'response_times' => [],
                'requests_per_minute' => 0,
                'last_updated' => now()->timestamp
            ];
        }

        // Update success/failure counts
        if ($statusCode >= 200 && $statusCode < 300) {
            $metrics[$endpoint]['success']++;
        } else {
            $metrics[$endpoint]['failures']++;
        }

        // Update response times (keep last 100)
        array_push($metrics[$endpoint]['response_times'], $duration);
        $metrics[$endpoint]['response_times'] = array_slice(
            $metrics[$endpoint]['response_times'],
            -100
        );

        // Update requests per minute
        $timeDiff = now()->timestamp - $metrics[$endpoint]['last_updated'];
        if ($timeDiff > 0) {
            $metrics[$endpoint]['requests_per_minute'] = 60 / $timeDiff;
        }
        $metrics[$endpoint]['last_updated'] = now()->timestamp;

        Cache::put('verteil_endpoint_metrics', $metrics, now()->addDay());
    }

    /**
     * Get metrics summary
     */
    protected function getMetricsSummary(): array
    {
        $metrics = collect(Cache::get('verteil_metrics', []));

        return [
            'requests_per_minute' => $this->calculateRequestsPerMinute($metrics),
            'average_response_time' => $this->calculateAverageResponseTime($metrics),
            'error_rate' => $this->calculateErrorRate($metrics),
            'endpoint_stats' => $this->calculateEndpointStats($metrics)
        ];
    }

    protected function calculateRequestsPerMinute(Collection $metrics): float
    {
        $recentMetrics = $metrics->where('timestamp', '>', now()->subMinutes(5)->timestamp);
        return round($recentMetrics->count() / 5, 2);
    }

    protected function calculateAverageResponseTime(Collection $metrics): float
    {
        if ($metrics->isEmpty()) {
            return 0.0;
        }
        return round($metrics->avg('duration'), 2);
    }

    protected function calculateErrorRate(Collection $metrics): float
    {
        if ($metrics->isEmpty()) {
            return 0.0;
        }
        $errors = $metrics->where('status_code', '>=', 400)->count();
        return round(($errors / $metrics->count()) * 100, 2);
    }

    protected function calculateEndpointStats(Collection $metrics): array
    {
        return $metrics->groupBy('endpoint')
            ->map(function ($endpointMetrics) {
                return [
                    'count' => $endpointMetrics->count(),
                    'average_duration' => round($endpointMetrics->avg('duration'), 2),
                    'error_rate' => $this->calculateErrorRate($endpointMetrics)
                ];
            })->toArray();
    }

    protected function getOverallStatus(): string
    {
        $metrics = $this->getMetricsSummary();

        if ($metrics['error_rate'] > 25) {
            return 'critical';
        }

        if ($metrics['error_rate'] > 10) {
            return 'degraded';
        }

        if ($metrics['average_response_time'] > 2000) {
            return 'slow';
        }

        return 'healthy';
    }
}