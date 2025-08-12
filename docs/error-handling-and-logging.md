# Error Handling and Logging System

This document describes the comprehensive error handling and logging system implemented for the EMR FHIR API.

## Overview

The system provides:
- Standardized error response format with trace IDs
- Global exception handler for consistent API responses
- Comprehensive logging for all clinical operations
- Error tracking and monitoring for production support
- System health monitoring and metrics

## Components

### 1. Exception Handling

#### ApiExceptionHandler
- Located: `app/Exceptions/ApiExceptionHandler.php`
- Provides consistent error response formatting for all API exceptions
- Includes trace IDs for error tracking
- Sanitizes sensitive data in logs
- Handles different exception types with appropriate HTTP status codes

#### Custom Exception Classes
- `ClinicalException` - Base class for clinical-related errors
- `PatientSafetyException` - Patient safety violations (medication conflicts, allergies)
- `ClinicalWorkflowException` - Clinical workflow rule violations
- `DataIntegrityException` - Data integrity constraint violations
- `BusinessRuleException` - Business rule violations (billing, insurance)

### 2. Logging Services

#### ClinicalLoggingService
- Located: `app/Services/ClinicalLoggingService.php`
- Provides specialized logging for clinical operations
- Supports multiple log channels (clinical, audit, security, performance, api)
- Includes trace IDs and user context in all logs
- Methods for logging different types of activities:
  - Patient activities
  - Visit activities
  - Encounter activities
  - Medication activities
  - Service request activities
  - Billing activities
  - Clinical form activities
  - Audit trails
  - Security events
  - Performance metrics

#### SystemMonitoringService
- Located: `app/Services/SystemMonitoringService.php`
- Provides system health monitoring and metrics
- Checks database, cache, disk space, memory usage, and error rates
- Tracks error occurrences and system performance
- Generates comprehensive system metrics

### 3. Middleware

#### ApiLoggingMiddleware
- Located: `app/Http/Middleware/ApiLoggingMiddleware.php`
- Logs all API requests and responses
- Monitors performance metrics
- Detects suspicious activity
- Sanitizes sensitive data in logs
- Tracks patient data access for HIPAA compliance

### 4. Health Monitoring

#### SystemHealthController
- Located: `app/Http/Controllers/SystemHealthController.php`
- Provides health check endpoints for monitoring systems
- Endpoints:
  - `/api/v1/system/ping` - Basic health check
  - `/api/v1/system/health` - Comprehensive health status
  - `/api/v1/system/metrics` - System metrics
  - `/api/v1/system/ready` - Readiness probe (Kubernetes)
  - `/api/v1/system/live` - Liveness probe (Kubernetes)

### 5. Logging Configuration

#### Log Channels
- `clinical` - Clinical operations and activities
- `audit` - Audit trails and data access logs
- `security` - Security events and violations
- `performance` - Performance metrics and slow queries
- `api` - API request/response logs

## Error Response Format

All API errors follow this standardized format:

```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "Human-readable error message",
    "details": {
      "field_name": ["Specific validation error"]
    }
  },
  "meta": {
    "timestamp": "2025-08-12T14:37:01.393802Z",
    "version": "v1",
    "trace_id": "uuid-for-tracking"
  }
}
```

## Error Codes

- `VALIDATION_ERROR` - Input validation failures
- `UNAUTHORIZED` - Authentication required
- `FORBIDDEN` - Insufficient permissions
- `NOT_FOUND` - Resource not found
- `METHOD_NOT_ALLOWED` - HTTP method not allowed
- `CONFLICT` - Resource conflict
- `PATIENT_SAFETY_VIOLATION` - Patient safety concerns
- `CLINICAL_WORKFLOW_ERROR` - Workflow rule violations
- `DATA_INTEGRITY_ERROR` - Data integrity issues
- `BUSINESS_RULE_VIOLATION` - Business rule violations
- `INTERNAL_ERROR` - Internal server errors

## Usage Examples

### Using ClinicalLoggingService

```php
use App\Services\ClinicalLoggingService;

$clinicalLogger = app(ClinicalLoggingService::class);

// Log patient activity
$clinicalLogger->logPatientActivity('created', [
    'patient_id' => 1,
    'created_by' => 'user@example.com'
]);

// Log audit trail
$clinicalLogger->logAuditTrail('update', 'patient', [
    'patient_id' => 1,
    'changes' => ['name' => 'John Doe']
]);

// Log security event
$clinicalLogger->logSecurityEvent('Unauthorized access attempt', 'warning', [
    'ip_address' => '192.168.1.100'
]);
```

### Using LogsClinicalActivity Trait

```php
use App\Traits\LogsClinicalActivity;

class CreatePatientAction
{
    use LogsClinicalActivity;
    
    public function execute(array $data)
    {
        $this->logActionStart('create_patient', ['data' => $data]);
        
        try {
            // Create patient logic
            $patient = Patient::create($data);
            
            $this->logPatientActivity('created', $patient->id, [
                'patient_code' => $patient->code
            ]);
            
            $this->logActionComplete('create_patient', [
                'patient_id' => $patient->id
            ]);
            
            return $patient;
        } catch (\Exception $e) {
            $this->logActionFailure('create_patient', $e, ['data' => $data]);
            throw $e;
        }
    }
}
```

### Throwing Custom Exceptions

```php
use App\Exceptions\PatientSafetyException;

// Check for medication allergies
if ($patient->hasAllergy($medication)) {
    throw new PatientSafetyException(
        'Patient has known allergy to this medication',
        [
            'patient_id' => $patient->id,
            'medication' => $medication->name,
            'allergy' => $patient->getAllergy($medication)
        ]
    );
}
```

## Monitoring and Alerting

### Health Check Endpoints

Use these endpoints for monitoring:

- **Ping**: `GET /api/v1/system/ping` - Basic connectivity check
- **Health**: `GET /api/v1/system/health` - Comprehensive health status
- **Metrics**: `GET /api/v1/system/metrics` - System performance metrics
- **Ready**: `GET /api/v1/system/ready` - Kubernetes readiness probe
- **Live**: `GET /api/v1/system/live` - Kubernetes liveness probe

### Log File Locations

- Clinical logs: `storage/logs/clinical.log`
- Audit logs: `storage/logs/audit.log`
- Security logs: `storage/logs/security.log`
- Performance logs: `storage/logs/performance.log`
- API logs: `storage/logs/api.log`
- General logs: `storage/logs/laravel.log`

## Security Considerations

- All sensitive data is sanitized before logging
- Patient data access is tracked for HIPAA compliance
- Security events are logged with appropriate severity levels
- Trace IDs enable correlation across log entries
- Failed authentication attempts are monitored
- Suspicious activity patterns are detected and logged

## Testing

The system includes comprehensive tests:

- `tests/Feature/ErrorHandlingTest.php` - Error handling functionality
- `tests/Feature/HealthEndpointsTest.php` - Health monitoring endpoints
- `tests/Unit/Services/ClinicalLoggingServiceSimpleTest.php` - Logging service
- `tests/Unit/Services/SystemMonitoringServiceTest.php` - Monitoring service

Run tests with:
```bash
php artisan test tests/Feature/ErrorHandlingTest.php
php artisan test tests/Feature/HealthEndpointsTest.php
```

## Configuration

### Environment Variables

Add these to your `.env` file:

```env
# Logging configuration
LOG_CLINICAL_DAYS=30
LOG_AUDIT_DAYS=365
LOG_SECURITY_DAYS=90
LOG_PERFORMANCE_DAYS=7
LOG_API_DAYS=14
```

### Log Rotation

The system uses daily log rotation with configurable retention periods:
- Clinical logs: 30 days (default)
- Audit logs: 365 days (default)
- Security logs: 90 days (default)
- Performance logs: 7 days (default)
- API logs: 14 days (default)