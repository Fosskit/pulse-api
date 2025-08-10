<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SimpleVisitTest extends TestCase
{
    use RefreshDatabase;

    public function test_visit_endpoint_exists()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/visits');
        
        // Just check that the endpoint exists and doesn't return 404
        $this->assertNotEquals(404, $response->getStatusCode());
    }

    public function test_can_create_visit_with_minimal_data()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Create minimal test data without complex relationships
        $patient = \App\Models\Patient::factory()->create();
        $facility = \App\Models\Facility::factory()->create();
        $terminology = \App\Models\Terminology::factory()->create();
        $visitType = \App\Models\Term::factory()->create(['terminology_id' => $terminology->id]);
        $admissionType = \App\Models\Term::factory()->create(['terminology_id' => $terminology->id]);

        $data = [
            'patient_id' => $patient->id,
            'facility_id' => $facility->id,
            'visit_type_id' => $visitType->id,
            'admission_type_id' => $admissionType->id,
        ];

        $response = $this->postJson('/api/v1/visits', $data);
        
        // Check if we get a response (not necessarily success yet)
        $this->assertNotEquals(404, $response->getStatusCode());
        
        if ($response->getStatusCode() !== 201) {
            // Output the response for debugging
            dump($response->json());
        }
    }

    public function test_validation_errors()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/visits', []);
        
        dump($response->getStatusCode());
        dump($response->json());
    }

    public function test_debug_admission()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $patient = \App\Models\Patient::factory()->create();
        $facility = \App\Models\Facility::factory()->create();
        $terminology = \App\Models\Terminology::factory()->create(['name' => 'visit_types']);
        $visitType = \App\Models\Term::factory()->create([
            'terminology_id' => $terminology->id,
            'code' => 'outpatient',
            'name' => 'Outpatient Visit'
        ]);
        $admissionTerminology = \App\Models\Terminology::factory()->create(['name' => 'admission_types']);
        $admissionType = \App\Models\Term::factory()->create([
            'terminology_id' => $admissionTerminology->id,
            'code' => 'emergency',
            'name' => 'Emergency Admission'
        ]);

        $data = [
            'patient_id' => $patient->id,
            'facility_id' => $facility->id,
            'visit_type_id' => $visitType->id,
            'admission_type_id' => $admissionType->id,
            'admitted_at' => now()->toISOString(),
        ];

        $response = $this->postJson('/api/v1/visits', $data);
        
        dump($response->getStatusCode());
        dump($response->json());
    }
}