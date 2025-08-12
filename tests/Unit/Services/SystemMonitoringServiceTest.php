<?php

namespace Tests\Unit\Services;

use App\Services\ClinicalLoggingService;
use App\Services\SystemMonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SystemMonitoringServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SystemMonitoringService $monitoringService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->monitoringService = app(SystemMonitoringService::class);
        
        // Configure test environment to use null logger
        config(['logging.default' => 'null']);
    }

    /** @test */
    public function it_checks_system_health()
    {
        $health = $this->monitoringService->checkSystemHealth();

        $this->assertIsArray($health);
        $this->assertArrayHasKey('status', $health);
        $this->assertArrayHasKey('timestamp', $health);
        $this->assertArrayHasKey('checks', $health);
        
        $this->assertContains($health['status'], ['healthy', 'warning', 'critical']);
        $this->assertIsString($health['timestamp']);
        $this->assertIsArray($health['checks']);
        
        // Check that all expected health checks are present
        $expectedChecks = ['database', 'cache', 'disk_space', 'memory', 'error_rate'];
        foreach ($expectedChecks as $check) {
            $this->assertArrayHasKey($check, $health['checks']);
            $this->assertArrayHasKey('status', $health['checks'][$check]);
        }
    }

    /** @test */
    public function it_checks_database_health()
    {
        $health = $this->monitoringService->checkSystemHealth();
        $dbCheck = $health['checks']['database'];

        $this->assertArrayHasKey('status', $dbCheck);
        $this->assertArrayHasKey('response_time_ms', $dbCheck);
        $this->assertArrayHasKey('message', $dbCheck);
        
        $this->assertContains($dbCheck['status'], ['healthy', 'warning', 'critical']);
        $this->assertIsNumeric($dbCheck['response_time_ms']);
        $this->assertIsString($dbCheck['message']);
    }

    /** @test */
    public function it_checks_cache_health()
    {
        $health = $this->monitoringService->checkSystemHealth();
        $cacheCheck = $health['checks']['cache'];

        $this->assertArrayHasKey('status', $cacheCheck);
        $this->assertArrayHasKey('message', $cacheCheck);
        
        $this->assertContains($cacheCheck['status'], ['healthy', 'warning', 'critical']);
        $this->assertIsString($cacheCheck['message']);
    }

    /** @test */
    public function it_checks_disk_space()
    {
        $health = $this->monitoringService->checkSystemHealth();
        $diskCheck = $health['checks']['disk_space'];

        $this->assertArrayHasKey('status', $diskCheck);
        $this->assertContains($diskCheck['status'], ['healthy', 'warning', 'critical']);
        
        if (isset($diskCheck['free_space_gb'])) {
            $this->assertIsNumeric($diskCheck['free_space_gb']);
            $this->assertIsNumeric($diskCheck['total_space_gb']);
            $this->assertIsNumeric($diskCheck['free_percentage']);
        }
    }

    /** @test */
    public function it_checks_memory_usage()
    {
        $health = $this->monitoringService->checkSystemHealth();
        $memoryCheck = $health['checks']['memory'];

        $this->assertArrayHasKey('status', $memoryCheck);
        $this->assertArrayHasKey('current_usage_mb', $memoryCheck);
        $this->assertArrayHasKey('peak_usage_mb', $memoryCheck);
        $this->assertArrayHasKey('usage_percentage', $memoryCheck);
        
        $this->assertContains($memoryCheck['status'], ['healthy', 'warning', 'critical']);
        $this->assertIsNumeric($memoryCheck['current_usage_mb']);
        $this->assertIsNumeric($memoryCheck['peak_usage_mb']);
        $this->assertIsNumeric($memoryCheck['usage_percentage']);
    }

    /** @test */
    public function it_checks_error_rate()
    {
        $health = $this->monitoringService->checkSystemHealth();
        $errorCheck = $health['checks']['error_rate'];

        $this->assertArrayHasKey('status', $errorCheck);
        $this->assertArrayHasKey('error_count', $errorCheck);
        $this->assertArrayHasKey('message', $errorCheck);
        
        $this->assertContains($errorCheck['status'], ['healthy', 'warning', 'critical']);
        $this->assertIsNumeric($errorCheck['error_count']);
        $this->assertIsString($errorCheck['message']);
    }

    /** @test */
    public function it_tracks_errors()
    {
        $this->monitoringService->trackError('test_error', [
            'context' => 'test_context',
        ]);

        // Check that error count was incremented in cache
        $errorCount = Cache::get('error_count_last_hour', 0);
        $this->assertGreaterThan(0, $errorCount);

        $typeCount = Cache::get('error_type_test_error_last_hour', 0);
        $this->assertGreaterThan(0, $typeCount);
    }

    /** @test */
    public function it_gets_system_metrics()
    {
        $metrics = $this->monitoringService->getSystemMetrics();

        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('timestamp', $metrics);
        $this->assertArrayHasKey('uptime', $metrics);
        $this->assertArrayHasKey('database', $metrics);
        $this->assertArrayHasKey('performance', $metrics);
        $this->assertArrayHasKey('errors', $metrics);
        
        // Check uptime structure
        $this->assertArrayHasKey('seconds', $metrics['uptime']);
        $this->assertArrayHasKey('formatted', $metrics['uptime']);
        
        // Check performance structure
        $this->assertArrayHasKey('memory_usage_mb', $metrics['performance']);
        $this->assertArrayHasKey('peak_memory_mb', $metrics['performance']);
        $this->assertArrayHasKey('cpu_usage', $metrics['performance']);
        
        // Check error structure
        $this->assertArrayHasKey('errors_last_hour', $metrics['errors']);
        $this->assertArrayHasKey('critical_errors_last_hour', $metrics['errors']);
        $this->assertArrayHasKey('warning_errors_last_hour', $metrics['errors']);
    }

    /** @test */
    public function it_gets_database_metrics()
    {
        $metrics = $this->monitoringService->getSystemMetrics();
        $dbMetrics = $metrics['database'];

        $this->assertIsArray($dbMetrics);
        
        if (!isset($dbMetrics['error'])) {
            $this->assertArrayHasKey('query_time_ms', $dbMetrics);
            $this->assertArrayHasKey('patient_count', $dbMetrics);
            $this->assertArrayHasKey('visit_count', $dbMetrics);
            $this->assertArrayHasKey('encounter_count', $dbMetrics);
            
            $this->assertIsNumeric($dbMetrics['query_time_ms']);
            $this->assertIsNumeric($dbMetrics['patient_count']);
            $this->assertIsNumeric($dbMetrics['visit_count']);
            $this->assertIsNumeric($dbMetrics['encounter_count']);
        }
    }

    /** @test */
    public function it_logs_performance_metrics_during_health_check()
    {
        $this->monitoringService->checkSystemHealth();

        Log::channel('performance')->assertLogged('info', function ($message, $context) {
            return str_contains($message, 'Performance: system_health_check') &&
                   isset($context['overall_status']) &&
                   isset($context['failed_checks']);
        });
    }

    /** @test */
    public function it_determines_overall_status_correctly()
    {
        // Test with no failed checks (should be healthy)
        $health = $this->monitoringService->checkSystemHealth();
        
        // Count failed checks
        $failedChecks = array_filter($health['checks'], fn($check) => $check['status'] !== 'healthy');
        $failedCount = count($failedChecks);
        
        if ($failedCount === 0) {
            $this->assertEquals('healthy', $health['status']);
        } elseif ($failedCount <= 2) {
            $this->assertEquals('warning', $health['status']);
        } else {
            $this->assertEquals('critical', $health['status']);
        }
    }

    /** @test */
    public function it_handles_database_connection_failures()
    {
        // Mock a database connection failure
        DB::shouldReceive('select')
            ->once()
            ->andThrow(new \Exception('Connection refused'));

        $health = $this->monitoringService->checkSystemHealth();
        $dbCheck = $health['checks']['database'];

        $this->assertEquals('critical', $dbCheck['status']);
        $this->assertArrayHasKey('error', $dbCheck);
        $this->assertEquals('Database connection failed', $dbCheck['message']);
    }

    /** @test */
    public function it_handles_cache_connection_failures()
    {
        // Mock a cache connection failure
        Cache::shouldReceive('put')
            ->once()
            ->andThrow(new \Exception('Cache connection failed'));

        $health = $this->monitoringService->checkSystemHealth();
        $cacheCheck = $health['checks']['cache'];

        $this->assertEquals('critical', $cacheCheck['status']);
        $this->assertArrayHasKey('error', $cacheCheck);
        $this->assertEquals('Cache connection failed', $cacheCheck['message']);
    }

    /** @test */
    public function it_formats_timestamps_correctly()
    {
        $health = $this->monitoringService->checkSystemHealth();
        
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/',
            $health['timestamp']
        );
    }

    /** @test */
    public function it_includes_trace_ids_in_logs()
    {
        $this->monitoringService->trackError('test_error');

        Log::channel('security')->assertLogged('info', function ($message, $context) {
            return isset($context['trace_id']) && 
                   is_string($context['trace_id']) &&
                   strlen($context['trace_id']) > 0;
        });
    }
}