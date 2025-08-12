<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Clinical Logging Service
 * 
 * Provides comprehensive logging for all clinical operations in the EMR system.
 * Handles audit trails, security events, performance monitoring, and clinical activities.
 */
class ClinicalLoggingService
{
    /**
     * Log patient-related activities
     */
    public function logPatientActivity(string $action, array $context = []): void
    {
        $this->logClinicalActivity('patient', $action, $context);
    }

    /**
     * Log visit-related activities
     */
    public function logVisitActivity(string $action, array $context = []): void
    {
        $this->logClinicalActivity('visit', $action, $context);
    }

    /**
     * Log encounter-related activities
     */
    public function logEncounterActivity(string $action, array $context = []): void
    {
        $this->logClinicalActivity('encounter', $action, $context);
    }

    /**
     * Log medication-related activities
     */
    public function logMedicationActivity(string $action, array $context = []): void
    {
        $this->logClinicalActivity('medication', $action, $context);
    }

    /**
     * Log service request activities
     */
    public function logServiceRequestActivity(string $action, array $context = []): void
    {
        $this->logClinicalActivity('service_request', $action, $context);
    }

    /**
     * Log billing-related activities
     */
    public function logBillingActivity(string $action, array $context = []): void
    {
        $this->logClinicalActivity('billing', $action, $context);
    }

    /**
     * Log clinical form activities
     */
    public function logFormActivity(string $action, array $context = []): void
    {
        $this->logClinicalActivity('clinical_form', $action, $context);
    }

    /**
     * Log audit trail for data access and modifications
     */
    public function logAuditTrail(string $action, string $resource, array $context = []): void
    {
        $auditContext = array_merge([
            'trace_id' => Str::uuid()->toString(),
            'user_id' => auth()->id(),
            'user_email' => auth()->user()?->email,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
            'action' => $action,
            'resource' => $resource,
        ], $context);

        Log::channel('audit')->info("Audit: {$action} on {$resource}", $auditContext);
    }

    /**
     * Log security events
     */
    public function logSecurityEvent(string $event, string $level = 'warning', array $context = []): void
    {
        $securityContext = array_merge([
            'trace_id' => Str::uuid()->toString(),
            'user_id' => auth()->id(),
            'user_email' => auth()->user()?->email,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
            'event' => $event,
        ], $context);

        Log::channel('security')->{$level}("Security Event: {$event}", $securityContext);
    }

    /**
     * Log performance metrics
     */
    public function logPerformanceMetric(string $operation, float $duration, array $context = []): void
    {
        $performanceContext = array_merge([
            'trace_id' => Str::uuid()->toString(),
            'operation' => $operation,
            'duration_ms' => round($duration * 1000, 2),
            'timestamp' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
        ], $context);

        Log::channel('performance')->info("Performance: {$operation}", $performanceContext);
    }

    /**
     * Log API requests and responses
     */
    public function logApiRequest(string $method, string $endpoint, int $statusCode, float $duration, array $context = []): void
    {
        $apiContext = array_merge([
            'trace_id' => Str::uuid()->toString(),
            'method' => $method,
            'endpoint' => $endpoint,
            'status_code' => $statusCode,
            'duration_ms' => round($duration * 1000, 2),
            'user_id' => auth()->id(),
            'ip_address' => request()->ip(),
            'timestamp' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
        ], $context);

        $level = $statusCode >= 500 ? 'error' : ($statusCode >= 400 ? 'warning' : 'info');
        Log::channel('api')->{$level}("API Request: {$method} {$endpoint}", $apiContext);
    }

    /**
     * Log clinical activities with standardized format
     */
    private function logClinicalActivity(string $category, string $action, array $context = []): void
    {
        $clinicalContext = array_merge([
            'trace_id' => Str::uuid()->toString(),
            'category' => $category,
            'action' => $action,
            'user_id' => auth()->id(),
            'user_email' => auth()->user()?->email,
            'facility_id' => $context['facility_id'] ?? null,
            'patient_id' => $context['patient_id'] ?? null,
            'visit_id' => $context['visit_id'] ?? null,
            'encounter_id' => $context['encounter_id'] ?? null,
            'timestamp' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
        ], $context);

        Log::channel('clinical')->info("Clinical: {$category}.{$action}", $clinicalContext);
    }

    /**
     * Log critical system errors
     */
    public function logCriticalError(string $error, \Throwable $exception, array $context = []): void
    {
        $errorContext = array_merge([
            'trace_id' => Str::uuid()->toString(),
            'error' => $error,
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'user_id' => auth()->id(),
            'timestamp' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
        ], $context);

        Log::channel('single')->critical("Critical Error: {$error}", $errorContext);
        
        // Also log to security channel if it's a security-related error
        if (str_contains(strtolower($error), 'security') || str_contains(strtolower($error), 'auth')) {
            $this->logSecurityEvent($error, 'critical', $errorContext);
        }
    }

    /**
     * Log data export activities
     */
    public function logDataExport(string $exportType, array $context = []): void
    {
        $exportContext = array_merge([
            'export_type' => $exportType,
            'exported_at' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
        ], $context);

        $this->logAuditTrail('export', $exportType, $exportContext);
        $this->logClinicalActivity('export', $exportType, $exportContext);
    }

    /**
     * Log medication safety checks
     */
    public function logMedicationSafety(string $checkType, bool $passed, array $context = []): void
    {
        $safetyContext = array_merge([
            'check_type' => $checkType,
            'passed' => $passed,
            'checked_at' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
        ], $context);

        $level = $passed ? 'info' : 'warning';
        Log::channel('clinical')->{$level}("Medication Safety: {$checkType}", $safetyContext);
        
        if (!$passed) {
            $this->logSecurityEvent("Medication safety check failed: {$checkType}", 'warning', $safetyContext);
        }
    }

    /**
     * Log patient data access for HIPAA compliance
     */
    public function logPatientDataAccess(int $patientId, string $accessType, array $context = []): void
    {
        $accessContext = array_merge([
            'patient_id' => $patientId,
            'access_type' => $accessType,
            'accessed_at' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
        ], $context);

        $this->logAuditTrail('access', 'patient_data', $accessContext);
    }
}