<?php

namespace App\Http\Controllers\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Health Controller
 * 
 * Provides health check and system status endpoints for monitoring
 * the EMR FHIR API system.
 */
class HealthController extends BaseController
{
    /**
     * Get API health status
     *
     * @return JsonResponse
     */
    public function health(): JsonResponse
    {
        return $this->successResponse([
            'status' => 'healthy',
            'version' => 'v1',
            'timestamp' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
            'services' => [
                'database' => $this->checkDatabase(),
                'cache' => $this->checkCache(),
                'storage' => $this->checkStorage(),
            ]
        ], 'API is healthy and operational');
    }

    /**
     * Get API version information
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function version(Request $request): JsonResponse
    {
        return $this->successResponse([
            'version' => 'v1',
            'api_version' => $request->attributes->get('api_version', 'v1'),
            'laravel_version' => app()->version(),
            'php_version' => PHP_VERSION,
            'supported_versions' => ['v1'],
            'deprecated_versions' => [],
        ], 'API version information');
    }

    /**
     * Check database connectivity
     *
     * @return string
     */
    private function checkDatabase(): string
    {
        try {
            \DB::connection()->getPdo();
            return 'healthy';
        } catch (\Exception $e) {
            return 'unhealthy';
        }
    }

    /**
     * Check cache connectivity
     *
     * @return string
     */
    private function checkCache(): string
    {
        try {
            cache()->put('health_check', 'test', 1);
            $value = cache()->get('health_check');
            return $value === 'test' ? 'healthy' : 'unhealthy';
        } catch (\Exception $e) {
            return 'unhealthy';
        }
    }

    /**
     * Check storage accessibility
     *
     * @return string
     */
    private function checkStorage(): string
    {
        try {
            $testFile = 'health_check_' . time() . '.txt';
            \Storage::put($testFile, 'test');
            $exists = \Storage::exists($testFile);
            \Storage::delete($testFile);
            return $exists ? 'healthy' : 'unhealthy';
        } catch (\Exception $e) {
            return 'unhealthy';
        }
    }
}