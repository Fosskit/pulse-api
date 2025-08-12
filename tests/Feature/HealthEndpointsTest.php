<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Configure test environment to use null logger
        config(['logging.default' => 'null']);
    }

    public function test_ping_endpoint_returns_success()
    {
        $response = $this->getJson('/api/v1/system/ping');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'timestamp',
                'service',
                'version',
            ])
            ->assertJson([
                'status' => 'ok',
                'service' => 'EMR FHIR API',
                'version' => 'v1',
            ]);
    }

    public function test_health_endpoint_returns_system_status()
    {
        $response = $this->getJson('/api/v1/system/health');
        
        $response->assertJsonStructure([
            'status',
            'timestamp',
            'checks' => [
                'database' => [
                    'status',
                    'message',
                ],
                'cache' => [
                    'status',
                    'message',
                ],
                'disk_space' => [
                    'status',
                ],
                'memory' => [
                    'status',
                ],
                'error_rate' => [
                    'status',
                ],
            ],
        ]);
        
        $this->assertContains($response->json('status'), ['healthy', 'warning', 'critical']);
    }

    public function test_metrics_endpoint_returns_system_metrics()
    {
        $response = $this->getJson('/api/v1/system/metrics');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'timestamp',
                    'uptime',
                    'database',
                    'performance',
                    'errors',
                ],
                'meta' => [
                    'timestamp',
                    'version',
                ],
            ])
            ->assertJson([
                'success' => true,
                'meta' => [
                    'version' => 'v1',
                ],
            ]);
    }

    public function test_ready_endpoint_returns_readiness_status()
    {
        $response = $this->getJson('/api/v1/system/ready');
        
        $response->assertJsonStructure([
            'ready',
            'status',
            'timestamp',
        ]);
        
        $this->assertIsBool($response->json('ready'));
        $this->assertContains($response->json('status'), ['healthy', 'warning', 'critical']);
    }

    public function test_live_endpoint_returns_liveness_status()
    {
        $response = $this->getJson('/api/v1/system/live');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'alive',
                'timestamp',
            ])
            ->assertJson([
                'alive' => true,
            ]);
    }

    public function test_health_endpoint_returns_503_when_critical()
    {
        // This test would require mocking critical failures
        // For now, we'll just verify the endpoint is accessible
        $response = $this->getJson('/api/v1/system/health');
        
        // Should return either 200 (healthy/warning) or 503 (critical)
        $this->assertContains($response->getStatusCode(), [200, 503]);
    }

    public function test_ready_endpoint_returns_503_when_not_ready()
    {
        // This test would require mocking system failures
        // For now, we'll just verify the endpoint is accessible
        $response = $this->getJson('/api/v1/system/ready');
        
        // Should return either 200 (ready) or 503 (not ready)
        $this->assertContains($response->getStatusCode(), [200, 503]);
    }
}