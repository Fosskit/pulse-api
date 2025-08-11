<?php

namespace Tests\Feature\Api\V1;

use App\Models\ServiceRequest;
use App\Models\LaboratoryRequest;
use App\Models\ImagingRequest;
use App\Models\Procedure;
use App\Models\Visit;
use App\Models\Encounter;
use App\Models\Patient;
use App\Models\Concept;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceRequestManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_create_laboratory_service_request()
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

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/service-requests', $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'visit_id',
                    'encounter_id',
                    'request_type',
                    'status_id',
                    'ordered_at',
                    'is_completed',
                    'is_pending',
                    'laboratory_request' => [
                        'id',
                        'test_concept_id',
                        'specimen_type_concept_id',
                        'reason_for_study',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('service_requests', [
            'visit_id' => $visit->id,
            'encounter_id' => $encounter->id,
            'request_type' => 'Laboratory',
        ]);

        $this->assertDatabaseHas('laboratory_requests', [
            'test_concept_id' => $testConcept->id,
            'specimen_type_concept_id' => $specimenConcept->id,
            'reason_for_study' => 'Routine blood work',
        ]);
    }

    public function test_can_create_imaging_service_request()
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

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/service-requests', $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'request_type',
                    'imaging_request' => [
                        'id',
                        'modality_concept_id',
                        'body_site_concept_id',
                        'reason_for_study',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('imaging_requests', [
            'modality_concept_id' => $modalityConcept->id,
            'body_site_concept_id' => $bodySiteConcept->id,
            'reason_for_study' => 'Chest pain evaluation',
        ]);
    }

    public function test_can_create_procedure_service_request()
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

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/service-requests', $data);

        $response->assertStatus(201);

        $this->assertDatabaseHas('procedures', [
            'patient_id' => $patient->id,
            'encounter_id' => $encounter->id,
            'procedure_concept_id' => $procedureConcept->id,
            'outcome_id' => $outcomeConcept->id,
            'body_site_id' => $bodySiteConcept->id,
        ]);
    }

    public function test_validation_fails_for_invalid_request_type()
    {
        $visit = Visit::factory()->create();
        $encounter = Encounter::factory()->create(['visit_id' => $visit->id]);

        $data = [
            'visit_id' => $visit->id,
            'encounter_id' => $encounter->id,
            'request_type' => 'InvalidType',
            'status_id' => 1,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/service-requests', $data);

        $response->assertStatus(422);
        
        $responseData = $response->json();
        $this->assertArrayHasKey('error', $responseData);
        $this->assertArrayHasKey('details', $responseData['error']);
        $this->assertArrayHasKey('request_type', $responseData['error']['details']);
    }

    public function test_validation_fails_when_laboratory_data_missing()
    {
        $visit = Visit::factory()->create();
        $encounter = Encounter::factory()->create(['visit_id' => $visit->id]);

        $data = [
            'visit_id' => $visit->id,
            'encounter_id' => $encounter->id,
            'request_type' => 'Laboratory',
            'status_id' => 1,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/service-requests', $data);

        $response->assertStatus(422);
        
        $responseData = $response->json();
        $this->assertArrayHasKey('error', $responseData);
        $this->assertArrayHasKey('details', $responseData['error']);
        $this->assertArrayHasKey('laboratory_data', $responseData['error']['details']);
    }

    public function test_can_list_service_requests()
    {
        $serviceRequests = ServiceRequest::factory()
            ->count(3)
            ->create();

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/service-requests');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'visit_id',
                        'encounter_id',
                        'request_type',
                        'status_id',
                        'ordered_at',
                        'is_completed',
                        'is_pending',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);
    }

    public function test_can_show_service_request_with_relationships()
    {
        $serviceRequest = ServiceRequest::factory()
            ->laboratory()
            ->create();
        
        LaboratoryRequest::factory()->create([
            'service_request_id' => $serviceRequest->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/service-requests/{$serviceRequest->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'request_type',
                    'visit',
                    'encounter',
                    'laboratory_request',
                ],
            ]);
    }

    public function test_can_filter_service_requests_by_type()
    {
        ServiceRequest::factory()->laboratory()->count(2)->create();
        ServiceRequest::factory()->imaging()->count(3)->create();

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/service-requests/type/Laboratory');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertCount(2, $data);
        
        foreach ($data as $item) {
            $this->assertEquals('Laboratory', $item['request_type']);
        }
    }

    public function test_can_get_pending_service_requests()
    {
        ServiceRequest::factory()->pending()->count(2)->create();
        ServiceRequest::factory()->completed()->count(3)->create();

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/service-requests/pending');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertCount(2, $data);
        
        foreach ($data as $item) {
            $this->assertTrue($item['is_pending']);
            $this->assertFalse($item['is_completed']);
        }
    }

    public function test_can_get_pending_requests_for_specific_visit()
    {
        $visit = Visit::factory()->create();
        $otherVisit = Visit::factory()->create();

        ServiceRequest::factory()->pending()->create(['visit_id' => $visit->id]);
        ServiceRequest::factory()->pending()->create(['visit_id' => $visit->id]);
        ServiceRequest::factory()->pending()->create(['visit_id' => $otherVisit->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/service-requests/pending?visit_id={$visit->id}");

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertCount(2, $data);
        
        foreach ($data as $item) {
            $this->assertEquals($visit->id, $item['visit_id']);
        }
    }

    public function test_can_filter_service_requests_with_query_builder()
    {
        $visit = Visit::factory()->create();
        ServiceRequest::factory()->create(['visit_id' => $visit->id, 'request_type' => 'Laboratory']);
        ServiceRequest::factory()->create(['request_type' => 'Imaging']);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/service-requests?filter[visit_id]={$visit->id}");

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($visit->id, $data[0]['visit_id']);
    }

    public function test_can_sort_service_requests()
    {
        $older = ServiceRequest::factory()->create(['ordered_at' => now()->subDays(2)]);
        $newer = ServiceRequest::factory()->create(['ordered_at' => now()->subDay()]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/service-requests?sort=ordered_at');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertEquals($older->id, $data[0]['id']);
        $this->assertEquals($newer->id, $data[1]['id']);
    }

    public function test_requires_authentication()
    {
        $response = $this->postJson('/api/v1/service-requests', []);
        $response->assertStatus(401);

        $response = $this->getJson('/api/v1/service-requests');
        $response->assertStatus(401);
    }

    public function test_returns_404_for_nonexistent_service_request()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/service-requests/999999');

        $response->assertStatus(404);
    }

    public function test_invalid_type_filter_returns_error()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/service-requests/type/InvalidType');

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'Invalid request type. Must be Laboratory, Imaging, or Procedure.',
            ]);
    }

    public function test_can_update_laboratory_service_request_results()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $encounter = Encounter::factory()->create(['visit_id' => $visit->id]);
        $serviceRequest = ServiceRequest::factory()->laboratory()->create([
            'visit_id' => $visit->id,
            'encounter_id' => $encounter->id,
            'completed_at' => null,
        ]);
        
        LaboratoryRequest::factory()->create([
            'service_request_id' => $serviceRequest->id,
        ]);

        $concept = Concept::factory()->create();
        $completedAt = now();

        $data = [
            'completed_at' => $completedAt->toISOString(),
            'performed_at' => now()->subHour()->toISOString(),
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

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/service-requests/{$serviceRequest->id}/results", $data);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'completed_at',
                    'is_completed',
                    'laboratory_request',
                    'observations',
                ],
            ]);

        $serviceRequest->refresh();
        $this->assertNotNull($serviceRequest->completed_at);
        $this->assertTrue($serviceRequest->isCompleted());

        $this->assertDatabaseHas('observations', [
            'service_request_id' => $serviceRequest->id,
            'concept_id' => $concept->id,
            'value_number' => 7.2,
            'interpretation' => 'Normal',
        ]);
    }

    public function test_can_mark_service_request_as_completed()
    {
        $serviceRequest = ServiceRequest::factory()->pending()->create();

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/service-requests/{$serviceRequest->id}/complete");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'completed_at',
                    'is_completed',
                ],
            ]);

        $serviceRequest->refresh();
        $this->assertNotNull($serviceRequest->completed_at);
        $this->assertTrue($serviceRequest->isCompleted());
    }

    public function test_validation_fails_for_invalid_results_data()
    {
        $serviceRequest = ServiceRequest::factory()->laboratory()->create();
        LaboratoryRequest::factory()->create(['service_request_id' => $serviceRequest->id]);

        $data = [
            'results' => [
                [
                    // Missing required concept_id
                    'value_number' => 7.2,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/service-requests/{$serviceRequest->id}/results", $data);

        $response->assertStatus(422);
        
        $responseData = $response->json();
        $this->assertArrayHasKey('error', $responseData);
        $this->assertArrayHasKey('details', $responseData['error']);
        $this->assertArrayHasKey('results.0.concept_id', $responseData['error']['details']);
    }

    public function test_can_update_results_with_multiple_observations()
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

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/service-requests/{$serviceRequest->id}/results", $data);

        $response->assertStatus(200);

        $this->assertDatabaseHas('observations', [
            'service_request_id' => $serviceRequest->id,
            'concept_id' => $concept1->id,
            'value_number' => 7.2,
        ]);

        $this->assertDatabaseHas('observations', [
            'service_request_id' => $serviceRequest->id,
            'concept_id' => $concept2->id,
            'value_string' => 'Positive',
        ]);
    }

    public function test_requires_authentication_for_updating_results()
    {
        $serviceRequest = ServiceRequest::factory()->create();

        $response = $this->putJson("/api/v1/service-requests/{$serviceRequest->id}/results", []);
        $response->assertStatus(401);

        $response = $this->putJson("/api/v1/service-requests/{$serviceRequest->id}/complete");
        $response->assertStatus(401);
    }
}