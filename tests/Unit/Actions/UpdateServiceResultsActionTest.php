<?php

namespace Tests\Unit\Actions;

use App\Actions\UpdateServiceResultsAction;
use App\Models\ServiceRequest;
use App\Models\LaboratoryRequest;
use App\Models\ImagingRequest;
use App\Models\Procedure;
use App\Models\Observation;
use App\Models\Visit;
use App\Models\Encounter;
use App\Models\Patient;
use App\Models\Concept;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use InvalidArgumentException;

class UpdateServiceResultsActionTest extends TestCase
{
    use RefreshDatabase;

    private UpdateServiceResultsAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new UpdateServiceResultsAction();
    }

    public function test_updates_laboratory_service_request_results()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $encounter = Encounter::factory()->create(['visit_id' => $visit->id]);
        $serviceRequest = ServiceRequest::factory()->laboratory()->create([
            'visit_id' => $visit->id,
            'encounter_id' => $encounter->id,
            'completed_at' => null,
        ]);
        
        $laboratoryRequest = LaboratoryRequest::factory()->create([
            'service_request_id' => $serviceRequest->id,
            'performed_at' => null,
            'performed_by' => null,
        ]);

        $concept = Concept::factory()->create();
        $completedAt = now();
        $performedAt = now()->subHour();

        $data = [
            'completed_at' => $completedAt,
            'performed_at' => $performedAt,
            'performed_by' => 123,
            'results' => [
                [
                    'concept_id' => $concept->id,
                    'value_number' => 7.2,
                    'reference_range_low' => 6.5,
                    'reference_range_high' => 8.0,
                    'interpretation' => 'Normal',
                    'comments' => 'Within normal range',
                ],
            ],
        ];

        $updatedServiceRequest = $this->action->execute($serviceRequest, $data);

        // Check service request was updated
        $this->assertEquals($completedAt->format('Y-m-d H:i:s'), $updatedServiceRequest->completed_at->format('Y-m-d H:i:s'));
        $this->assertTrue($updatedServiceRequest->isCompleted());

        // Check laboratory request was updated
        $laboratoryRequest->refresh();
        $this->assertEquals($performedAt->format('Y-m-d H:i:s'), $laboratoryRequest->performed_at->format('Y-m-d H:i:s'));
        $this->assertEquals(123, $laboratoryRequest->performed_by);

        // Check observation was created
        $this->assertDatabaseHas('observations', [
            'patient_id' => $patient->id,
            'encounter_id' => $encounter->id,
            'service_request_id' => $serviceRequest->id,
            'concept_id' => $concept->id,
            'value_number' => 7.2,
            'reference_range_low' => 6.5,
            'reference_range_high' => 8.0,
            'interpretation' => 'Normal',
            'comments' => 'Within normal range',
        ]);
    }

    public function test_updates_imaging_service_request_results()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $encounter = Encounter::factory()->create(['visit_id' => $visit->id]);
        $serviceRequest = ServiceRequest::factory()->imaging()->create([
            'visit_id' => $visit->id,
            'encounter_id' => $encounter->id,
            'completed_at' => null,
        ]);
        
        $imagingRequest = ImagingRequest::factory()->create([
            'service_request_id' => $serviceRequest->id,
            'performed_at' => null,
            'performed_by' => null,
        ]);

        $concept = Concept::factory()->create();
        $performedAt = now()->subHour();

        $data = [
            'performed_at' => $performedAt,
            'performed_by' => 456,
            'results' => [
                [
                    'concept_id' => $concept->id,
                    'value_string' => 'No acute findings',
                    'interpretation' => 'Normal',
                ],
            ],
        ];

        $updatedServiceRequest = $this->action->execute($serviceRequest, $data);

        // Check imaging request was updated
        $imagingRequest->refresh();
        $this->assertEquals($performedAt->format('Y-m-d H:i:s'), $imagingRequest->performed_at->format('Y-m-d H:i:s'));
        $this->assertEquals(456, $imagingRequest->performed_by);

        // Check observation was created
        $this->assertDatabaseHas('observations', [
            'service_request_id' => $serviceRequest->id,
            'concept_id' => $concept->id,
            'value_string' => 'No acute findings',
            'interpretation' => 'Normal',
        ]);
    }

    public function test_updates_procedure_service_request_results()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $encounter = Encounter::factory()->create(['visit_id' => $visit->id]);
        $serviceRequest = ServiceRequest::factory()->procedure()->create([
            'visit_id' => $visit->id,
            'encounter_id' => $encounter->id,
            'completed_at' => null,
        ]);
        
        $outcomeConcept = Concept::factory()->create();
        $procedure = Procedure::factory()->create([
            'patient_id' => $patient->id,
            'encounter_id' => $encounter->id,
            'performed_at' => null,
            'performed_by' => null,
        ]);

        $performedAt = now()->subHour();

        $data = [
            'performed_at' => $performedAt,
            'performed_by' => 789,
            'outcome_id' => $outcomeConcept->id,
        ];

        $updatedServiceRequest = $this->action->execute($serviceRequest, $data);

        // Check procedure was updated
        $procedure->refresh();
        $this->assertEquals($performedAt->format('Y-m-d H:i:s'), $procedure->performed_at->format('Y-m-d H:i:s'));
        $this->assertEquals(789, $procedure->performed_by);
        $this->assertEquals($outcomeConcept->id, $procedure->outcome_id);
    }

    public function test_uses_current_time_when_completed_at_not_provided()
    {
        $serviceRequest = ServiceRequest::factory()->laboratory()->create([
            'completed_at' => null,
        ]);
        
        LaboratoryRequest::factory()->create([
            'service_request_id' => $serviceRequest->id,
        ]);

        $beforeUpdate = now()->subSecond();
        $updatedServiceRequest = $this->action->execute($serviceRequest, []);
        $afterUpdate = now()->addSecond();

        $this->assertNotNull($updatedServiceRequest->completed_at);
        $this->assertTrue($updatedServiceRequest->completed_at->between($beforeUpdate, $afterUpdate));
    }

    public function test_creates_multiple_observations_from_results()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $encounter = Encounter::factory()->create(['visit_id' => $visit->id]);
        $serviceRequest = ServiceRequest::factory()->laboratory()->create([
            'visit_id' => $visit->id,
            'encounter_id' => $encounter->id,
        ]);
        
        LaboratoryRequest::factory()->create([
            'service_request_id' => $serviceRequest->id,
        ]);

        $concept1 = Concept::factory()->create();
        $concept2 = Concept::factory()->create();

        $data = [
            'results' => [
                [
                    'concept_id' => $concept1->id,
                    'value_number' => 7.2,
                    'interpretation' => 'Normal',
                ],
                [
                    'concept_id' => $concept2->id,
                    'value_string' => 'Positive',
                    'interpretation' => 'Abnormal',
                ],
            ],
        ];

        $this->action->execute($serviceRequest, $data);

        $this->assertDatabaseHas('observations', [
            'service_request_id' => $serviceRequest->id,
            'concept_id' => $concept1->id,
            'value_number' => 7.2,
            'interpretation' => 'Normal',
        ]);

        $this->assertDatabaseHas('observations', [
            'service_request_id' => $serviceRequest->id,
            'concept_id' => $concept2->id,
            'value_string' => 'Positive',
            'interpretation' => 'Abnormal',
        ]);

        $this->assertEquals(2, Observation::where('service_request_id', $serviceRequest->id)->count());
    }

    public function test_throws_exception_when_laboratory_request_not_found()
    {
        $serviceRequest = ServiceRequest::factory()->laboratory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Laboratory request not found for this service request');

        $this->action->execute($serviceRequest, []);
    }

    public function test_throws_exception_when_imaging_request_not_found()
    {
        $serviceRequest = ServiceRequest::factory()->imaging()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Imaging request not found for this service request');

        $this->action->execute($serviceRequest, []);
    }

    public function test_throws_exception_when_procedure_not_found()
    {
        $serviceRequest = ServiceRequest::factory()->procedure()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Procedure record not found for this service request');

        $this->action->execute($serviceRequest, []);
    }

    public function test_loads_relationships_in_response()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $encounter = Encounter::factory()->create(['visit_id' => $visit->id]);
        $serviceRequest = ServiceRequest::factory()->laboratory()->create([
            'visit_id' => $visit->id,
            'encounter_id' => $encounter->id,
        ]);
        
        LaboratoryRequest::factory()->create([
            'service_request_id' => $serviceRequest->id,
        ]);

        $updatedServiceRequest = $this->action->execute($serviceRequest, []);

        $this->assertTrue($updatedServiceRequest->relationLoaded('visit'));
        $this->assertTrue($updatedServiceRequest->relationLoaded('encounter'));
        $this->assertTrue($updatedServiceRequest->relationLoaded('service'));
        $this->assertTrue($updatedServiceRequest->relationLoaded('status'));
        $this->assertTrue($updatedServiceRequest->relationLoaded('laboratoryRequest'));
        $this->assertTrue($updatedServiceRequest->relationLoaded('observations'));
    }

    public function test_creates_observations_with_all_supported_value_types()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $encounter = Encounter::factory()->create(['visit_id' => $visit->id]);
        $serviceRequest = ServiceRequest::factory()->laboratory()->create([
            'visit_id' => $visit->id,
            'encounter_id' => $encounter->id,
        ]);
        
        LaboratoryRequest::factory()->create([
            'service_request_id' => $serviceRequest->id,
        ]);

        $concept = Concept::factory()->create();
        $testDate = now()->subDay();

        $data = [
            'results' => [
                [
                    'concept_id' => $concept->id,
                    'value_string' => 'Test String',
                    'value_number' => 42.5,
                    'value_datetime' => $testDate,
                    'value_boolean' => true,
                    'reference_range_low' => 10.0,
                    'reference_range_high' => 50.0,
                    'reference_range_text' => '10-50',
                    'interpretation' => 'Normal',
                    'comments' => 'Test comment',
                    'verified_at' => now(),
                    'verified_by' => 999,
                ],
            ],
        ];

        $this->action->execute($serviceRequest, $data);

        $this->assertDatabaseHas('observations', [
            'service_request_id' => $serviceRequest->id,
            'concept_id' => $concept->id,
            'value_string' => 'Test String',
            'value_number' => 42.5,
            'value_boolean' => true,
            'reference_range_low' => 10.0,
            'reference_range_high' => 50.0,
            'reference_range_text' => '10-50',
            'interpretation' => 'Normal',
            'comments' => 'Test comment',
            'verified_by' => 999,
        ]);
    }
}