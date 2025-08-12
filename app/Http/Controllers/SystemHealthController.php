<?php

namespace App\Http\Controllers;

use App\Services\SystemMonitoringService;
use Illuminate\Http\JsonResponse;

/**
 * System Health Controller
 * 
 * Provides system health and monitoring endpoints for the EMR system.
 * These endpoints are used by load balancers, monitoring systems, and ops teams.
 */
class SystemHealthController extends Controller
{
    protected function getMonitoringService(): SystemMonitoringService
    {
        return app(SystemMonitoringService::class);
    }

    /**
     * Basic health check endpoint
     * 
     * @return JsonResponse
     */
    public function ping(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
            'service' => 'EMR FHIR API',
            'version' => 'v1',
        ]);
    }

    /**
     * Comprehensive health check
     * 
     * @return JsonResponse
     */
    public function health(): JsonResponse
    {
        $health = $this->getMonitoringService()->checkSystemHealth();
        
        $statusCode = match ($health['status']) {
            'healthy' => 200,
            'warning' => 200, // Still operational
            'critical' => 503, // Service unavailable
            default => 500,
        };
        
        return response()->json($health, $statusCode);
    }

    /**
     * System metrics endpoint
     * 
     * @return JsonResponse
     */
    public function metrics(): JsonResponse
    {
        $metrics = $this->getMonitoringService()->getSystemMetrics();
        
        return response()->json([
            'success' => true,
            'data' => $metrics,
            'meta' => [
                'timestamp' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
                'version' => 'v1',
            ],
        ]);
    }

    /**
     * Readiness probe for Kubernetes/container orchestration
     * 
     * @return JsonResponse
     */
    public function ready(): JsonResponse
    {
        $health = $this->getMonitoringService()->checkSystemHealth();
        
        // Service is ready if it's healthy or has only warnings
        $isReady = in_array($health['status'], ['healthy', 'warning']);
        
        $statusCode = $isReady ? 200 : 503;
        
        return response()->json([
            'ready' => $isReady,
            'status' => $health['status'],
            'timestamp' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
        ], $statusCode);
    }

    /**
     * Liveness probe for Kubernetes/container orchestration
     * 
     * @return JsonResponse
     */
    public function live(): JsonResponse
    {
        // Simple liveness check - if we can respond, we're alive
        return response()->json([
            'alive' => true,
            'timestamp' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
        ]);
    }
}