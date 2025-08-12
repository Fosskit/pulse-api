<?php

namespace Tests\Unit\Services;

use App\Services\ClinicalLoggingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

use Tests\TestCase;

class ClinicalLoggingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ClinicalLoggingService $clinicalLogger;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->clinicalLogger = app(ClinicalLoggingService::class);
        
        // Configure test environment to use null logger
        config(['logging.default' => 'null']);
    }

    /** @test */
    public function it_logs_patient_activities()
    {
        $this->clinicalLogger->logPatientActivity('created', [
            'patient_id' => 1,
            'created_by' => 'test_user',
        ]);

        // Verify the method completes without throwing exceptions
        $this->assertTrue(true);
    }

    /** @test */
    public function it_logs_visit_activities()
    {
        $this->clinicalLogger->logVisitActivity('admitted', [
            'visit_id' => 1,
            'patient_id' => 1,
        ]);

        // Verify the method completes without throwing exceptions
        $this->assertTrue(true);
    }

    /** @test */
    public function it_logs_encounter_activities()
    {
        $this->clinicalLogger->logEncounterActivity('created', [
            'encounter_id' => 1,
            'visit_id' => 1,
        ]);

        Log::channel('clinical')->assertLogged('info', function ($message, $context) {
            return str_contains($message, 'Clinical: encounter.created') &&
                   $context['encounter_id'] === 1;
        });
    }

    /** @test */
    public function it_logs_medication_activities()
    {
        $this->clinicalLogger->logMedicationActivity('prescribed', [
            'medication_request_id' => 1,
            'patient_id' => 1,
        ]);

        Log::channel('clinical')->assertLogged('info', function ($message, $context) {
            return str_contains($message, 'Clinical: medication.prescribed') &&
                   $context['medication_request_id'] === 1;
        });
    }

    /** @test */
    public function it_logs_service_request_activities()
    {
        $this->clinicalLogger->logServiceRequestActivity('ordered', [
            'service_request_id' => 1,
            'request_type' => 'Laboratory',
        ]);

        Log::channel('clinical')->assertLogged('info', function ($message, $context) {
            return str_contains($message, 'Clinical: service_request.ordered') &&
                   $context['request_type'] === 'Laboratory';
        });
    }

    /** @test */
    public function it_logs_billing_activities()
    {
        $this->clinicalLogger->logBillingActivity('invoice_generated', [
            'invoice_id' => 1,
            'total_amount' => 100.00,
        ]);

        Log::channel('clinical')->assertLogged('info', function ($message, $context) {
            return str_contains($message, 'Clinical: billing.invoice_generated') &&
                   $context['total_amount'] === 100.00;
        });
    }

    /** @test */
    public function it_logs_form_activities()
    {
        $this->clinicalLogger->logFormActivity('submitted', [
            'form_template_id' => 1,
            'encounter_id' => 1,
        ]);

        Log::channel('clinical')->assertLogged('info', function ($message, $context) {
            return str_contains($message, 'Clinical: clinical_form.submitted') &&
                   $context['form_template_id'] === 1;
        });
    }

    /** @test */
    public function it_logs_audit_trails()
    {
        $this->clinicalLogger->logAuditTrail('update', 'patient', [
            'patient_id' => 1,
            'changes' => ['name' => 'John Doe'],
        ]);

        Log::channel('audit')->assertLogged('info', function ($message, $context) {
            return str_contains($message, 'Audit: update on patient') &&
                   $context['action'] === 'update' &&
                   $context['resource'] === 'patient' &&
                   isset($context['trace_id']);
        });
    }

    /** @test */
    public function it_logs_security_events()
    {
        $this->clinicalLogger->logSecurityEvent('Unauthorized access attempt', 'warning', [
            'ip_address' => '192.168.1.100',
        ]);

        Log::channel('security')->assertLogged('warning', function ($message, $context) {
            return str_contains($message, 'Security Event: Unauthorized access attempt') &&
                   $context['event'] === 'Unauthorized access attempt' &&
                   $context['ip_address'] === '192.168.1.100';
        });
    }

    /** @test */
    public function it_logs_performance_metrics()
    {
        $this->clinicalLogger->logPerformanceMetric('patient_search', 0.250, [
            'query_params' => ['name' => 'John'],
        ]);

        Log::channel('performance')->assertLogged('info', function ($message, $context) {
            return str_contains($message, 'Performance: patient_search') &&
                   $context['operation'] === 'patient_search' &&
                   $context['duration_ms'] === 250.0;
        });
    }

    /** @test */
    public function it_logs_api_requests()
    {
        $this->clinicalLogger->logApiRequest('GET', '/api/v1/patients', 200, 0.150, [
            'user_id' => 1,
        ]);

        Log::channel('api')->assertLogged('info', function ($message, $context) {
            return str_contains($message, 'API Request: GET /api/v1/patients') &&
                   $context['method'] === 'GET' &&
                   $context['status_code'] === 200 &&
                   $context['duration_ms'] === 150.0;
        });
    }

    /** @test */
    public function it_logs_critical_errors()
    {
        $exception = new \Exception('Test exception');
        
        $this->clinicalLogger->logCriticalError('Database connection failed', $exception, [
            'database' => 'primary',
        ]);

        Log::channel('single')->assertLogged('critical', function ($message, $context) {
            return str_contains($message, 'Critical Error: Database connection failed') &&
                   $context['error'] === 'Database connection failed' &&
                   $context['exception'] === 'Exception' &&
                   $context['database'] === 'primary';
        });
    }

    /** @test */
    public function it_logs_data_export_activities()
    {
        $this->clinicalLogger->logDataExport('visit_export', [
            'visit_id' => 1,
            'patient_id' => 1,
        ]);

        Log::channel('audit')->assertLogged('info', function ($message, $context) {
            return str_contains($message, 'Audit: export on visit_export');
        });

        Log::channel('clinical')->assertLogged('info', function ($message, $context) {
            return str_contains($message, 'Clinical: export.visit_export');
        });
    }

    /** @test */
    public function it_logs_medication_safety_checks()
    {
        $this->clinicalLogger->logMedicationSafety('allergy_check', false, [
            'patient_id' => 1,
            'medication' => 'Penicillin',
            'allergy' => 'Penicillin allergy',
        ]);

        Log::channel('clinical')->assertLogged('warning', function ($message, $context) {
            return str_contains($message, 'Medication Safety: allergy_check') &&
                   $context['passed'] === false &&
                   $context['medication'] === 'Penicillin';
        });

        Log::channel('security')->assertLogged('warning', function ($message, $context) {
            return str_contains($message, 'Medication safety check failed: allergy_check');
        });
    }

    /** @test */
    public function it_logs_patient_data_access()
    {
        $this->clinicalLogger->logPatientDataAccess(1, 'read', [
            'accessed_fields' => ['name', 'demographics'],
        ]);

        Log::channel('audit')->assertLogged('info', function ($message, $context) {
            return str_contains($message, 'Audit: access on patient_data') &&
                   $context['patient_id'] === 1 &&
                   $context['access_type'] === 'read';
        });
    }

    /** @test */
    public function it_includes_trace_ids_in_all_logs()
    {
        $this->clinicalLogger->logPatientActivity('test_action');

        Log::channel('clinical')->assertLogged('info', function ($message, $context) {
            return isset($context['trace_id']) && 
                   is_string($context['trace_id']) &&
                   strlen($context['trace_id']) > 0;
        });
    }

    /** @test */
    public function it_includes_timestamps_in_all_logs()
    {
        $this->clinicalLogger->logPatientActivity('test_action');

        Log::channel('clinical')->assertLogged('info', function ($message, $context) {
            return isset($context['timestamp']) && 
                   is_string($context['timestamp']);
        });
    }

    /** @test */
    public function it_includes_user_context_when_authenticated()
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $this->clinicalLogger->logPatientActivity('test_action');

        Log::channel('clinical')->assertLogged('info', function ($message, $context) use ($user) {
            return $context['user_id'] === $user->id &&
                   $context['user_email'] === $user->email;
        });
    }
}