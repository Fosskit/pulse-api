<?php

namespace Tests\Unit\Actions;

use App\Actions\GetPendingServiceRequestsAction;
use App\Models\Patient;
use App\Models\Visit;
use App\Models\ServiceRequest;
use App\Models\LaboratoryRequest;
use App\Models\ImagingRequest;
use App\Models\Procedure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetPendingServiceRequestsActionTest extends TestCase
{
    use RefreshDatabase;

    private GetPendingServiceRequestsAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new GetPendingServiceRequestsAction();
    }

    public function test_returns_pending_service_requests_for_visit()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);

        $pendingRequest = ServiceRequest::factory()->create([
            'visit_id' => $visit->id,
            'status_id' => 1, // Pending
            'completed_at' => null
        ]);

        $completedRequest = ServiceRequest::factory()->create([
            'visit_id' => $visit->id,
            'status_id' => 2, // Completed
            'completed_at' => now()
        ]);

        $requests = $this->action->execute($visit->id);

        $this->assertCount(1, $requests);
        $this->assertEquals($pendingRequest->id, $requests->first()->id);
    }

    public function test_returns_empty_collection_for_visit_without_pending_requests()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);

        // Create only completed requests
        ServiceRequest::factory()->create([
            'visit_id' => $visit->id,
            'status_id' => 2, // Completed
            'completed_at' => now()
        ]);

        $requests = $this->action->execute($visit->id);

        $this->assertCount(0, $requests);
    }

    public function test_filters_by_request_type()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);

        $labRequest = ServiceRequest::factory()->create([
            'visit_id' => $visit->id,
            'request_type' => 'Laboratory',
            'status_id' => 1
        ]);

        $imagingRequest = ServiceRequest::factory()->create([
            'visit_id' => $visit->id,
            'request_type' => 'Imaging',
            'status_id' => 1
        ]);

        $procedureRequest = ServiceRequest::factory()->create([
            'visit_id' => $visit->id,
            'request_type' => 'Procedure',
            'status_id' => 1
        ]);

        $labRequests = $this->action->execute($visit->id, ['type' => 'Laboratory']);
        $imagingRequests = $this->action->execute($visit->id, ['type' => 'Imaging']);

        $this->assertCount(1, $labRequests);
        $this->assertEquals('Laboratory', $labRequests->first()->request_type);

        $this->assertCount(1, $imagingRequests);
        $this->assertEquals('Imaging', $imagingRequests->first()->request_type);
    }

    public function test_filters_by_priority()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);

        ServiceRequest::factory()->create([
            'visit_id' => $visit->id,
            'priority' => 'urgent',
            'status_id' => 1
        ]);

        ServiceRequest::factory()->create([
            'visit_id' => $visit->id,
            'priority' => 'routine',
            'status_id' => 1
        ]);

        $urgentRequests = $this->action->execute($visit->id, ['priority' => 'urgent']);

        $this->assertCount(1, $urgentRequests);
        $this->assertEquals('urgent', $urgentRequests->first()->priority);
    }

    public function test_orders_by_priority_and_created_date()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);

        $routineOld = ServiceRequest::factory()->create([
            'visit_id' => $visit->id,
            'priority' => 'routine',
            'status_id' => 1,
            'created_at' => now()->subDays(2)
        ]);

        $urgentNew = ServiceRequest::factory()->create([
            'visit_id' => $visit->id,
            'priority' => 'urgent',
            'status_id' => 1,
            'created_at' => now()->subDay()
        ]);

        $urgentOld = ServiceRequest::factory()->create([
            'visit_id' => $visit->id,
            'priority' => 'urgent',
            'status_id' => 1,
            'created_at' => now()->subDays(3)
        ]);

        $requests = $this->action->execute($visit->id);

        // Should be ordered: urgent (oldest first), then routine (oldest first)
        $this->assertEquals($urgentOld->id, $requests->first()->id);
        $this->assertEquals($urgentNew->id, $requests->get(1)->id);
        $this->assertEquals($routineOld->id, $requests->last()->id);
    }

    public function test_includes_related_laboratory_requests()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);

        $serviceRequest = ServiceRequest::factory()->create([
            'visit_id' => $visit->id,
            'request_type' => 'Laboratory',
            'status_id' => 1
        ]);

        $labRequest = LaboratoryRequest::factory()->create([
            'service_request_id' => $serviceRequest->id,
            'test_name' => 'Complete Blood Count'
        ]);

        $requests = $this->action->execute($visit->id);

        $this->assertCount(1, $requests);
        $this->assertNotNull($requests->first()->laboratoryRequest);
        $this->assertEquals('Complete Blood Count', $requests->first()->laboratoryRequest->test_name);
    }

    public function test_includes_related_imaging_requests()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);

        $serviceRequest = ServiceRequest::factory()->create([
            'visit_id' => $visit->id,
            'request_type' => 'Imaging',
            'status_id' => 1
        ]);

        $imagingRequest = ImagingRequest::factory()->create([
            'service_request_id' => $serviceRequest->id,
            'imaging_type' => 'X-Ray'
        ]);

        $requests = $this->action->execute($visit->id);

        $this->assertCount(1, $requests);
        $this->assertNotNull($requests->first()->imagingRequest);
        $this->assertEquals('X-Ray', $requests->first()->imagingRequest->imaging_type);
    }

    public function test_includes_related_procedures()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);

        $serviceRequest = ServiceRequest::factory()->create([
            'visit_id' => $visit->id,
            'request_type' => 'Procedure',
            'status_id' => 1
        ]);

        $procedure = Procedure::factory()->create([
            'service_request_id' => $serviceRequest->id,
            'procedure_name' => 'Biopsy'
        ]);

        $requests = $this->action->execute($visit->id);

        $this->assertCount(1, $requests);
        $this->assertNotNull($requests->first()->procedure);
        $this->assertEquals('Biopsy', $requests->first()->procedure->procedure_name);
    }

    public function test_handles_nonexistent_visit()
    {
        $requests = $this->action->execute(999);

        $this->assertCount(0, $requests);
    }

    public function test_validates_visit_id_parameter()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->action->execute(null);
    }

    public function test_filters_by_department()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);

        ServiceRequest::factory()->create([
            'visit_id' => $visit->id,
            'department_id' => 1,
            'status_id' => 1
        ]);

        ServiceRequest::factory()->create([
            'visit_id' => $visit->id,
            'department_id' => 2,
            'status_id' => 1
        ]);

        $requests = $this->action->execute($visit->id, ['department_id' => 1]);

        $this->assertCount(1, $requests);
        $this->assertEquals(1, $requests->first()->department_id);
    }

    public function test_includes_overdue_requests()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);

        ServiceRequest::factory()->create([
            'visit_id' => $visit->id,
            'status_id' => 1,
            'requested_at' => now()->subDays(3),
            'expected_completion' => now()->subDay() // Overdue
        ]);

        ServiceRequest::factory()->create([
            'visit_id' => $visit->id,
            'status_id' => 1,
            'requested_at' => now()->subDay(),
            'expected_completion' => now()->addDay() // Not overdue
        ]);

        $overdueRequests = $this->action->execute($visit->id, ['overdue_only' => true]);

        $this->assertCount(1, $overdueRequests);
    }

    public function test_groups_by_request_type()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);

        ServiceRequest::factory()->count(2)->create([
            'visit_id' => $visit->id,
            'request_type' => 'Laboratory',
            'status_id' => 1
        ]);

        ServiceRequest::factory()->create([
            'visit_id' => $visit->id,
            'request_type' => 'Imaging',
            'status_id' => 1
        ]);

        $groupedRequests = $this->action->execute($visit->id, ['group_by_type' => true]);

        $this->assertArrayHasKey('Laboratory', $groupedRequests);
        $this->assertArrayHasKey('Imaging', $groupedRequests);
        $this->assertCount(2, $groupedRequests['Laboratory']);
        $this->assertCount(1, $groupedRequests['Imaging']);
    }
}