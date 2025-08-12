<?php

namespace App\Http\Middleware;

use App\Services\ClinicalLoggingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API Logging Middleware
 * 
 * Logs all API requests and responses with performance metrics
 * and security monitoring for the EMR system.
 */
class ApiLoggingMiddleware
{
    protected ClinicalLoggingService $clinicalLogger;

    public function __construct(ClinicalLoggingService $clinicalLogger)
    {
        $this->clinicalLogger = $clinicalLogger;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        
        // Log incoming request
        $this->logIncomingRequest($request);
        
        // Process the request
        $response = $next($request);
        
        // Calculate duration
        $duration = microtime(true) - $startTime;
        
        // Log outgoing response
        $this->logOutgoingResponse($request, $response, $duration);
        
        return $response;
    }

    /**
     * Log incoming API request
     */
    private function logIncomingRequest(Request $request): void
    {
        // Skip logging for health check endpoints
        if ($this->shouldSkipLogging($request)) {
            return;
        }

        $context = [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'path' => $request->path(),
            'query_params' => $request->query(),
            'headers' => $this->sanitizeHeaders($request->headers->all()),
            'user_id' => auth()->id(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];

        // Add request body for non-GET requests (sanitized)
        if (!$request->isMethod('GET')) {
            $context['request_body'] = $this->sanitizeRequestBody($request->all());
        }

        $this->clinicalLogger->logApiRequest(
            $request->method(),
            $request->path(),
            0, // Status not available yet
            0, // Duration not available yet
            $context
        );
    }

    /**
     * Log outgoing API response
     */
    private function logOutgoingResponse(Request $request, Response $response, float $duration): void
    {
        // Skip logging for health check endpoints
        if ($this->shouldSkipLogging($request)) {
            return;
        }

        $context = [
            'status_code' => $response->getStatusCode(),
            'response_size' => strlen($response->getContent()),
            'duration_ms' => round($duration * 1000, 2),
        ];

        // Log performance metrics
        $this->clinicalLogger->logPerformanceMetric(
            $request->path(),
            $duration,
            array_merge($context, [
                'method' => $request->method(),
                'user_id' => auth()->id(),
            ])
        );

        // Log API request with final status
        $this->clinicalLogger->logApiRequest(
            $request->method(),
            $request->path(),
            $response->getStatusCode(),
            $duration,
            $context
        );

        // Log security events for suspicious activity
        $this->checkForSuspiciousActivity($request, $response, $duration);
    }

    /**
     * Check for suspicious activity and log security events
     */
    private function checkForSuspiciousActivity(Request $request, Response $response, float $duration): void
    {
        // Log failed authentication attempts
        if ($response->getStatusCode() === 401) {
            $this->clinicalLogger->logSecurityEvent(
                'Failed authentication attempt',
                'warning',
                [
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]
            );
        }

        // Log potential brute force attempts (multiple 401s from same IP)
        if ($response->getStatusCode() === 401 && $this->isRepeatedFailure($request)) {
            $this->clinicalLogger->logSecurityEvent(
                'Potential brute force attack',
                'critical',
                [
                    'ip_address' => $request->ip(),
                    'url' => $request->fullUrl(),
                    'user_agent' => $request->userAgent(),
                ]
            );
        }

        // Log slow requests that might indicate performance issues
        if ($duration > 5.0) { // 5 seconds threshold
            $this->clinicalLogger->logSecurityEvent(
                'Slow API request detected',
                'warning',
                [
                    'duration_seconds' => $duration,
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                ]
            );
        }

        // Log access to sensitive patient data
        if ($this->isPatientDataAccess($request) && $response->getStatusCode() === 200) {
            $patientId = $this->extractPatientId($request);
            if ($patientId) {
                $this->clinicalLogger->logPatientDataAccess(
                    $patientId,
                    $this->getAccessType($request),
                    [
                        'url' => $request->fullUrl(),
                        'method' => $request->method(),
                    ]
                );
            }
        }
    }

    /**
     * Check if this is a repeated failure from the same IP
     */
    private function isRepeatedFailure(Request $request): bool
    {
        // This is a simplified check - in production, you'd want to use Redis or cache
        // to track failed attempts per IP address over time
        return false; // Placeholder implementation
    }

    /**
     * Check if the request is accessing patient data
     */
    private function isPatientDataAccess(Request $request): bool
    {
        $path = $request->path();
        return str_contains($path, '/patients/') || 
               str_contains($path, '/visits/') || 
               str_contains($path, '/encounters/');
    }

    /**
     * Extract patient ID from request
     */
    private function extractPatientId(Request $request): ?int
    {
        // Extract patient ID from URL segments
        $segments = $request->segments();
        
        if (in_array('patients', $segments)) {
            $patientIndex = array_search('patients', $segments);
            return isset($segments[$patientIndex + 1]) ? (int) $segments[$patientIndex + 1] : null;
        }
        
        // For visits and encounters, you might need to look up the patient ID
        return null;
    }

    /**
     * Get the type of data access
     */
    private function getAccessType(Request $request): string
    {
        return match ($request->method()) {
            'GET' => 'read',
            'POST' => 'create',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            default => 'unknown',
        };
    }

    /**
     * Determine if logging should be skipped for this request
     */
    private function shouldSkipLogging(Request $request): bool
    {
        $skipPaths = [
            'health',
            'ping',
            'status',
            'metrics',
        ];

        $path = $request->path();
        
        foreach ($skipPaths as $skipPath) {
            if (str_contains($path, $skipPath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sanitize headers for logging (remove sensitive information)
     */
    private function sanitizeHeaders(array $headers): array
    {
        $sensitiveHeaders = [
            'authorization',
            'x-api-key',
            'cookie',
            'x-auth-token',
        ];

        foreach ($sensitiveHeaders as $header) {
            if (isset($headers[$header])) {
                $headers[$header] = ['[REDACTED]'];
            }
        }

        return $headers;
    }

    /**
     * Sanitize request body for logging (remove sensitive information)
     */
    private function sanitizeRequestBody(array $data): array
    {
        $sensitiveFields = [
            'password',
            'password_confirmation',
            'token',
            'api_key',
            'secret',
            'credit_card',
            'ssn',
            'social_security_number',
        ];

        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '[REDACTED]';
            }
        }

        return $data;
    }
}