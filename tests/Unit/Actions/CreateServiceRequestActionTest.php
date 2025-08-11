<?php

namespace Tests\Unit\Actions;

use App\Actions\CreateServiceRequestAction;
use App\Models\ServiceRequest;
use App\Models\LaboratoryRequest;
use App\Models\ImagingRequest;
use App\Models\Procedure;
use App\Models\Visit;
use App\Models\Encounter;
use App\Models\Concept;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use InvalidArgumentException;

class CreateServiceRequestActionTest extends TestCase
{
    use RefreshDatabase;

    private CreateServiceRequestAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new CreateServiceRequestAction();
    }

    public function test_creates_laboratory_service_request_successfully()
    {
        $visit = Visit::factory()->create();
        $encounter = Encounter::factory()->create(['visit_id' => $visit->id]);
        $testConcept = Concept::factory()->create();
        $specimenConcept = Concept::factory()->create();

        $data = [
            'visit_id' => $visit->id,
            'encounter_id' => $encounter->id,
            'request_type' => 'Laboratory',
            'status_id' => 1,
            'laboratory_data' => [
                'test_concept_id' => $testConcept->id,
                'specimen_type_concept_id' => $specimenConcept->id,
                'reason_for_study' => 'Routine blood work',
            ],
        ];

        $serviceRequest = $this->action->execute($data);

        $this->assertInstanceOf(ServiceRequest::class, $serviceRequest);
        $this->assertEquals('Laboratory', $serviceRequest->request_type);
        $this->assertEquals($visit->id, $serviceRequest->visit_id);
        $this->assertEquals($encounter->id, $serviceRequest->encounter_id);
        $this->assertNotNull($serviceRequest->ordered_at);

        $this->assertDatabaseHas('service_requests', [
            'id' => $serviceRequest->id,
            'request_type' => 'Laboratory',
        ]);

        $this->assertDatabaseHas('laboratory_requests', [
            'service_request_id' => $serviceRequest->id,
            'test_concept_id' => $testConcept->id,
            'specimen_type_concept_id' => $specimenConcept->id,
            'reason_for_study' => 'Routine blood work',
        ]);
    }

    public function test_creates_imaging_service_request_successfully()
    {
        $visit = Visit::factory()->create();
        $encounter = Encounter::factory()->create(['visit_id' => $visit->id]);
        $modalityConcept = Concept::factory()->create();
        $bodySiteConcept = Concept::factory()->create();

        $data = [
            'visit_id' => $visit->id,
            'encounter_id' => $encounter->id,
            'request_type' => 'Imaging',
            'status_id' => 1,
            'imaging_data' => [
                'modality_concept_id' => $modalityConcept->id,
                'body_site_concept_id' => $bodySiteConcept->id,
                'reason_for_study' => 'Chest pain evaluation',
            ],
        ];

        $serviceRequest = $this->action->execute($data);

        $this->assertInstanceOf(ServiceRequest::class, $serviceRequest);
        $this->assertEquals('Imaging', $serviceRequest->request_type);

        $this->assertDatabaseHas('imaging_requests', [
            'service_request_id' => $serviceRequest->id,
            'modality_concept_id' => $modalityConcept->id,
            'body_site_concept_id' => $bodySiteConcept->id,
            'reason_for_study' => 'Chest pain evaluation',
        ]);
    }

    public function test_creates_procedure_service_request_successfully()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $encounter = Encounter::factory()->create(['visit_id' => $visit->id]);
        $procedureConcept = Concept::factory()->create();
        $outcomeConcept = Concept::factory()->create();
        $bodySiteConcept = Concept::factory()->create();

        $data = [
            'visit_id' => $visit->id,
            'encounter_id' => $encounter->id,
            'request_type' => 'Procedure',
            'status_id' => 1,
            'procedure_data' => [
                'procedure_concept_id' => $procedureConcept->id,
                'outcome_id' => $outcomeConcept->id,
                'body_site_id' => $bodySiteConcept->id,
            ],
        ];

        $serviceRequest = $this->action->execute($data);

        $this->assertInstanceOf(ServiceRequest::class, $serviceRequest);
        $this->assertEquals('Procedure', $serviceRequest->request_type);

        $this->assertDatabaseHas('procedures', [
            'patient_id' => $patient->id,
            'encounter_id' => $encounter->id,
            'procedure_concept_id' => $procedureConcept->id,
            'outcome_id' => $outcomeConcept->id,
            'body_site_id' => $bodySiteConcept->id,
        ]);
    }

    public function test_throws_exception_when_encounter_does_not_belong_to_visit()
    {
        $visit1 = Visit::factory()->create();
        $visit2 = Visit::factory()->create();
        $encounter = Encounter::factory()->create(['visit_id' => $visit2->id]);

        $data = [
            'visit_id' => $visit1->id,
            'encounter_id' => $encounter->id,
            'request_type' => 'Laboratory',
            'status_id' => 1,
            'laboratory_data' => [
                'test_concept_id' => 1,
                'specimen_type_concept_id' => 1,
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Encounter does not belong to the specified visit');

        $this->action->execute($data);
    }

    public function test_throws_exception_for_invalid_request_type()
    {
        $visit = Visit::factory()->create();
        $encounter = Encounter::factory()->create(['visit_id' => $visit->id]);

        $data = [
            'visit_id' => $visit->id,
            'encounter_id' => $encounter->id,
            'request_type' => 'InvalidType',
            'status_id' => 1,
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid request type: InvalidType');

        $this->action->execute($data);
    }

    public function test_throws_exception_when_laboratory_data_missing()
    {
        $visit = Visit::factory()->create();
        $encounter = Encounter::factory()->create(['visit_id' => $visit->id]);

        $data = [
            'visit_id' => $visit->id,
            'encounter_id' => $encounter->id,
            'request_type' => 'Laboratory',
            'status_id' => 1,
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Laboratory data is required for laboratory requests');

        $this->action->execute($data);
    }

    public function test_throws_exception_when_imaging_data_missing()
    {
        $visit = Visit::factory()->create();
        $encounter = Encounter::factory()->create(['visit_id' => $visit->id]);

        $data = [
            'visit_id' => $visit->id,
            'encounter_id' => $encounter->id,
            'request_type' => 'Imaging',
            'status_id' => 1,
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Imaging data is required for imaging requests');

        $this->action->execute($data);
    }

    public function test_throws_exception_when_procedure_data_missing()
    {
        $visit = Visit::factory()->create();
        $encounter = Encounter::factory()->create(['visit_id' => $visit->id]);

        $data = [
            'visit_id' => $visit->id,
            'encounter_id' => $encounter->id,
            'request_type' => 'Procedure',
            'status_id' => 1,
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Procedure data is required for procedure requests');

        $this->action->execute($data);
    }

    public function test_sets_custom_ordered_at_when_provided()
    {
        $visit = Visit::factory()->create();
        $encounter = Encounter::factory()->create(['visit_id' => $visit->id]);
        $testConcept = Concept::factory()->create();
        $specimenConcept = Concept::factory()->create();
        $customOrderedAt = now()->subHours(2);

        $data = [
            'visit_id' => $visit->id,
            'encounter_id' => $encounter->id,
            'request_type' => 'Laboratory',
            'status_id' => 1,
            'ordered_at' => $customOrderedAt,
            'laboratory_data' => [
                'test_concept_id' => $testConcept->id,
                'specimen_type_concept_id' => $specimenConcept->id,
            ],
        ];

        $serviceRequest = $this->action->execute($data);

        $this->assertEquals($customOrderedAt->format('Y-m-d H:i:s'), $serviceRequest->ordered_at->format('Y-m-d H:i:s'));
    }

    public function test_loads_relationships_in_response()
    {
        $visit = Visit::factory()->create();
        $encounter = Encounter::factory()->create(['visit_id' => $visit->id]);
        $testConcept = Concept::factory()->create();
        $specimenConcept = Concept::factory()->create();

        $data = [
            'visit_id' => $visit->id,
            'encounter_id' => $encounter->id,
            'request_type' => 'Laboratory',
            'status_id' => 1,
            'laboratory_data' => [
                'test_concept_id' => $testConcept->id,
                'specimen_type_concept_id' => $specimenConcept->id,
            ],
        ];

        $serviceRequest = $this->action->execute($data);

        $this->assertTrue($serviceRequest->relationLoaded('visit'));
        $this->assertTrue($serviceRequest->relationLoaded('encounter'));
        $this->assertTrue($serviceRequest->relationLoaded('laboratoryRequest'));
        $this->assertTrue($serviceRequest->laboratoryRequest->relationLoaded('testConcept'));
        $this->assertTrue($serviceRequest->laboratoryRequest->relationLoaded('specimenTypeConcept'));
    }
}