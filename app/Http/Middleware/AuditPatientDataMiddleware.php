<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Spatie\Activitylog\Facades\CauserResolver;
use Spatie\Activitylog\Models\Activity;

/**
 * Patient Data Audit Middleware
 * 
 * Logs all access and modifications to patient data for compliance
 * and security auditing purposes.
 */
class AuditPatientDataMiddleware
{
    /**
     * Routes that involve patient data access
     */
    protected const PATIENT_DATA_ROUTES = [
        'patients',
        'visits',
        'encounters',
        'observations',
        'medications',
        'prescriptions',
        'service-requests',
        'invoices',
        'exports',
    ];
    
    /**
     * Sensitive operations that require detailed logging
     */
    protected const SENSITIVE_OPERATIONS = [
        'POST',
        'PUT',
        'PATCH',
        'DELETE',
    ];
    
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        
        // Check if this request involves patient data
        if (!$this->involvesPatientData($request)) {
            return $next($request);
        }
        
        // Log the request
        $this->logPatientDataAccess($request, 'request');
        
        $response = $next($request);
        
        // Log the response for sensitive operations
        if ($this->isSensitiveOperation($request)) {
            $this->logPatientDataAccess($request, 'response', $response, microtime(true) - $startTime);
        }
        
        return $response;
    }
    
    /**
     * Check if the request involves patient data.
     */
    protected function involvesPatientData(Request $request): bool
    {
        $path = $request->path();
        
        foreach (self::PATIENT_DATA_ROUTES as $route) {
            if (str_contains($path, $route)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if the operation is sensitive and requires detailed logging.
     */
    protected function isSensitiveOperation(Request $request): bool
    {
        return in_array($request->method(), self::SENSITIVE_OPERATIONS);
    }
    
    /**
     * Log patient data access.
     */
    protected function logPatientDataAccess(
        Request $request, 
        string $type, 
        Response $response = null, 
        float $duration = null
    ): void {
        $user = $request->user();
        $patientId = $this->extractPatientId($request);
        
        $logData = [
            'type' => $type,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'route' => $request->route()?->getName(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
            'patient_id' => $patientId,
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'user_roles' => $user?->getRoleNames()->toArray(),
            'timestamp' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
        ];
        
        // Add request-specific data
        if ($type === 'request') {
            $logData['request_data'] = $this->sanitizeRequestData($request);
        }
        
        // Add response-specific data
        if ($type === 'response' && $response) {
            $logData['response_status'] = $response->getStatusCode();
            $logData['duration_ms'] = round($duration * 1000, 2);
            
            // Log response data for failed requests
            if ($response->getStatusCode() >= 400) {
                $logData['response_data'] = $this->sanitizeResponseData($response);
            }
        }
        
        // Use Spatie Activity Log for structured logging
        if ($user && $patientId) {
            activity('patient_data_access')
                ->causedBy($user)
                ->performedOn($this->getPatientModel($patientId))
                ->withProperties($logData)
                ->log("Patient data {$type}: {$request->method()} {$request->path()}");
        }
        
        // Also log to dedicated audit log
        Log::channel('audit')->info('Patient data access', $logData);
    }
    
    /**
     * Extract patient ID from request.
     */
    protected function extractPatientId(Request $request): ?int
    {
        // Try to get patient ID from route parameters
        $patientId = $request->route('patient')?->id ?? $request->route('patient');
        
        if ($patientId) {
            return is_numeric($patientId) ? (int) $patientId : null;
        }
        
        // Try to get patient ID from visit
        $visit = $request->route('visit');
        if ($visit && is_object($visit) && isset($visit->patient_id)) {
            return $visit->patient_id;
        }
        
        // Try to get patient ID from encounter
        $encounter = $request->route('encounter');
        if ($encounter && is_object($encounter) && isset($encounter->visit)) {
            return $encounter->visit->patient_id;
        }
        
        // Try to get patient ID from request data
        $requestPatientId = $request->input('patient_id');
        if ($requestPatientId && is_numeric($requestPatientId)) {
            return (int) $requestPatientId;
        }
        
        return null;
    }
    
    /**
     * Get patient model for activity logging.
     */
    protected function getPatientModel(?int $patientId): ?\App\Models\Patient
    {
        if (!$patientId) {
            return null;
        }
        
        try {
            return \App\Models\Patient::find($patientId);
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Sanitize request data for logging.
     */
    protected function sanitizeRequestData(Request $request): array
    {
        $data = $request->all();
        
        // Remove sensitive fields
        $sensitiveFields = ['password', 'password_confirmation', 'token', 'api_key'];
        
        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '[REDACTED]';
            }
        }
        
        // Limit data size for logging
        $jsonData = json_encode($data);
        if (strlen($jsonData) > 10000) {
            return ['message' => 'Request data too large for logging', 'size' => strlen($jsonData)];
        }
        
        return $data;
    }
    
    /**
     * Sanitize response data for logging.
     */
    protected function sanitizeResponseData(Response $response): array
    {
        $content = $response->getContent();
        
        if (!$content) {
            return [];
        }
        
        $data = json_decode($content, true);
        
        if (!is_array($data)) {
            return ['raw_content' => substr($content, 0, 1000)];
        }
        
        // Limit data size for logging
        if (strlen($content) > 5000) {
            return ['message' => 'Response data too large for logging', 'size' => strlen($content)];
        }
        
        return $data;
    }
}