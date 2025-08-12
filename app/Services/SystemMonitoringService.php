<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * System Monitoring Service
 * 
 * Provides system health monitoring, error tracking, and alerting
 * for the EMR system production environment.
 */
class SystemMonitoringService
{
    protected function getClinicalLogger(): ClinicalLoggingService
    {
        return app(ClinicalLoggingService::class);
    }

    /**
     * Check overall system health
     */
    public function checkSystemHealth(): array
    {
        $health = [
            'status' => 'healthy',
            'timestamp' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
            'checks' => [],
        ];

        // Database connectivity check
        $health['checks']['database'] = $this->checkDatabaseHealth();
        
        // Cache connectivity check
        $health['checks']['cache'] = $this->checkCacheHealth();
        
        // Disk space check
        $health['checks']['disk_space'] = $this->checkDiskSpace();
        
        // Memory usage check
        $health['checks']['memory'] = $this->checkMemoryUsage();
        
        // Error rate check
        $health['checks']['error_rate'] = $this->checkErrorRate();

        // Determine overall status
        $failedChecks = array_filter($health['checks'], fn($check) => $check['status'] !== 'healthy');
        if (!empty($failedChecks)) {
            $health['status'] = count($failedChecks) > 2 ? 'critical' : 'warning';
        }

        // Log health check results
        $this->getClinicalLogger()->logPerformanceMetric(
            'system_health_check',
            0,
            [
                'overall_status' => $health['status'],
                'failed_checks' => count($failedChecks),
                'checks' => array_keys($failedChecks),
            ]
        );

        return $health;
    }

    /**
     * Check database connectivity and performance
     */
    protected function checkDatabaseHealth(): array
    {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $duration = microtime(true) - $start;

            $status = $duration > 1.0 ? 'warning' : 'healthy';
            
            return [
                'status' => $status,
                'response_time_ms' => round($duration * 1000, 2),
                'message' => $status === 'healthy' ? 'Database responsive' : 'Database slow response',
            ];
        } catch (\Exception $e) {
            $this->getClinicalLogger()->logCriticalError('Database health check failed', $e);
            
            return [
                'status' => 'critical',
                'message' => 'Database connection failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check cache connectivity
     */
    protected function checkCacheHealth(): array
    {
        try {
            $testKey = 'health_check_' . time();
            $testValue = 'test_value';
            
            Cache::put($testKey, $testValue, 60);
            $retrieved = Cache::get($testKey);
            Cache::forget($testKey);
            
            if ($retrieved === $testValue) {
                return [
                    'status' => 'healthy',
                    'message' => 'Cache operational',
                ];
            } else {
                return [
                    'status' => 'warning',
                    'message' => 'Cache read/write mismatch',
                ];
            }
        } catch (\Exception $e) {
            $this->getClinicalLogger()->logCriticalError('Cache health check failed', $e);
            
            return [
                'status' => 'critical',
                'message' => 'Cache connection failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check available disk space
     */
    protected function checkDiskSpace(): array
    {
        $path = storage_path();
        $freeBytes = disk_free_space($path);
        $totalBytes = disk_total_space($path);
        
        if ($freeBytes === false || $totalBytes === false) {
            return [
                'status' => 'warning',
                'message' => 'Unable to determine disk space',
            ];
        }
        
        $freePercentage = ($freeBytes / $totalBytes) * 100;
        
        $status = match (true) {
            $freePercentage < 5 => 'critical',
            $freePercentage < 15 => 'warning',
            default => 'healthy',
        };
        
        return [
            'status' => $status,
            'free_space_gb' => round($freeBytes / (1024 ** 3), 2),
            'total_space_gb' => round($totalBytes / (1024 ** 3), 2),
            'free_percentage' => round($freePercentage, 2),
            'message' => "Disk space: {$freePercentage}% free",
        ];
    }

    /**
     * Check memory usage
     */
    protected function checkMemoryUsage(): array
    {
        $memoryUsage = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);
        $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
        
        $usagePercentage = $memoryLimit > 0 ? ($memoryUsage / $memoryLimit) * 100 : 0;
        
        $status = match (true) {
            $usagePercentage > 90 => 'critical',
            $usagePercentage > 75 => 'warning',
            default => 'healthy',
        };
        
        return [
            'status' => $status,
            'current_usage_mb' => round($memoryUsage / (1024 ** 2), 2),
            'peak_usage_mb' => round($peakMemory / (1024 ** 2), 2),
            'limit_mb' => round($memoryLimit / (1024 ** 2), 2),
            'usage_percentage' => round($usagePercentage, 2),
            'message' => "Memory usage: {$usagePercentage}%",
        ];
    }

    /**
     * Check error rate over the last hour
     */
    protected function checkErrorRate(): array
    {
        $cacheKey = 'error_count_last_hour';
        $errorCount = Cache::get($cacheKey, 0);
        
        // This is a simplified implementation
        // In production, you'd want to track actual error rates from logs
        $status = match (true) {
            $errorCount > 100 => 'critical',
            $errorCount > 50 => 'warning',
            default => 'healthy',
        };
        
        return [
            'status' => $status,
            'error_count' => $errorCount,
            'message' => "Errors in last hour: {$errorCount}",
        ];
    }

    /**
     * Parse memory limit string to bytes
     */
    protected function parseMemoryLimit(string $limit): int
    {
        if ($limit === '-1') {
            return PHP_INT_MAX;
        }
        
        $unit = strtolower(substr($limit, -1));
        $value = (int) substr($limit, 0, -1);
        
        return match ($unit) {
            'g' => $value * 1024 ** 3,
            'm' => $value * 1024 ** 2,
            'k' => $value * 1024,
            default => (int) $limit,
        };
    }

    /**
     * Track error occurrence
     */
    public function trackError(string $errorType, array $context = []): void
    {
        $cacheKey = 'error_count_last_hour';
        $currentCount = Cache::get($cacheKey, 0);
        Cache::put($cacheKey, $currentCount + 1, 3600); // 1 hour TTL
        
        // Track specific error types
        $errorTypeKey = "error_type_{$errorType}_last_hour";
        $typeCount = Cache::get($errorTypeKey, 0);
        Cache::put($errorTypeKey, $typeCount + 1, 3600);
        
        // Log error tracking
        $this->getClinicalLogger()->logSecurityEvent(
            "Error tracked: {$errorType}",
            'info',
            array_merge($context, [
                'error_type' => $errorType,
                'total_errors_last_hour' => $currentCount + 1,
                'type_errors_last_hour' => $typeCount + 1,
            ])
        );
    }

    /**
     * Get system metrics for monitoring dashboard
     */
    public function getSystemMetrics(): array
    {
        return [
            'timestamp' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
            'uptime' => $this->getUptime(),
            'database' => $this->getDatabaseMetrics(),
            'performance' => $this->getPerformanceMetrics(),
            'errors' => $this->getErrorMetrics(),
        ];
    }

    /**
     * Get application uptime
     */
    protected function getUptime(): array
    {
        // This is a simplified implementation
        // In production, you'd track actual application start time
        return [
            'seconds' => time() - strtotime('today'),
            'formatted' => gmdate('H:i:s', time() - strtotime('today')),
        ];
    }

    /**
     * Get database performance metrics
     */
    protected function getDatabaseMetrics(): array
    {
        try {
            $start = microtime(true);
            $result = DB::select("
                SELECT 
                    COUNT(*) as total_connections,
                    (SELECT COUNT(*) FROM patients) as patient_count,
                    (SELECT COUNT(*) FROM visits) as visit_count,
                    (SELECT COUNT(*) FROM encounters) as encounter_count
            ");
            $queryTime = microtime(true) - $start;
            
            return [
                'query_time_ms' => round($queryTime * 1000, 2),
                'connections' => $result[0]->total_connections ?? 0,
                'patient_count' => $result[0]->patient_count ?? 0,
                'visit_count' => $result[0]->visit_count ?? 0,
                'encounter_count' => $result[0]->encounter_count ?? 0,
            ];
        } catch (\Exception $e) {
            return [
                'error' => 'Unable to fetch database metrics',
                'exception' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get performance metrics
     */
    protected function getPerformanceMetrics(): array
    {
        $cpuUsage = 0;
        
        // sys_getloadavg is not available on Windows
        if (function_exists('sys_getloadavg')) {
            $loadAvg = sys_getloadavg();
            $cpuUsage = $loadAvg[0] ?? 0;
        }
        
        return [
            'memory_usage_mb' => round(memory_get_usage(true) / (1024 ** 2), 2),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / (1024 ** 2), 2),
            'cpu_usage' => $cpuUsage,
        ];
    }

    /**
     * Get error metrics
     */
    protected function getErrorMetrics(): array
    {
        return [
            'errors_last_hour' => Cache::get('error_count_last_hour', 0),
            'critical_errors_last_hour' => Cache::get('error_type_critical_last_hour', 0),
            'warning_errors_last_hour' => Cache::get('error_type_warning_last_hour', 0),
        ];
    }
}