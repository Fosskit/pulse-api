<?php

namespace Tests\Feature\Api\V1;

use App\Models\MedicationRequest;
use App\Models\Patient;
use App\Models\Term;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedicationManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Patient $patient;
    private Visit $visit;
    private Term $status;
    private Term $intent;
    private Term $medication;
    private Term $unit;
    private Term $method;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        
        // Create test data
        $this->patient = Patient::factory()->create();
        $this->visit = Visit::factory()->create([
            'patient_id' => $this->patient->id,
            'discharged_at' => null, // Active visit
        ]);
        
        $this->status = Term::factory()->create(['name' => 'active']);
        $this->intent = Term::factory()->create(['name' => 'order']);
        $this->medication = Term::factory()->create(['name' => 'Paracetamol']);
        $this->unit = Term::factory()->create(['name' => 'tablet']);
        $this->method = Term::factory()->create(['name' => 'oral']);
    }

    public function test_can_create_prescription()
    {
        $prescriptionData = [
            'visit_id' => $this->visit->id,
            'status_id' => $this->status->id,
            'intent_id' => $this->intent->id,
            'medication_id' => $this->medication->id,
            'requester_id' => $this->user->id,
            'quantity' => 30,
            'unit_id' => $this->unit->id,
            'instruction' => [
                'method_id' => $this->method->id,
                'unit_id' => $this->unit->id,
                'morning' => 1.0,
                'afternoon' => 1.0,
                'evening' => 1.0,
                'night' => 0.0,
                'days' => 7,
                'quantity' => 21.0,
                'note' => 'Take with food',
            ],
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/prescriptions', $prescriptionData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'ulid',
                    'visit_id',
                    'quantity',
                    'total_dispensed',
                    'remaining_quantity',
                    'is_fully_dispensed',
                    'visit' => [
                        'id',
                        'ulid',
                        'patient_id',
                        'admitted_at',
                        'discharged_at',
                        'is_active',
                    ],
                    'medication' => [
                        'id',
                        'name',
                        'display_name',
                    ],
                    'instruction' => [
                        'id',
                        'morning',
                        'afternoon',
                        'evening',
                        'night',
                        'days',
                        'total_daily_dose',
                        'dosage_schedule',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('medication_requests', [
            'visit_id' => $this->visit->id,
            'medication_id' => $this->medication->id,
            'quantity' => 30,
        ]);

        $this->assertDatabaseHas('medication_instructions', [
            'morning' => 1.0,
            'afternoon' => 1.0,
            'evening' => 1.0,
            'night' => 0.0,
            'days' => 7,
            'note' => 'Take with food',
        ]);
    }

    public function test_prescription_validation_fails_with_invalid_data()
    {
        $invalidData = [
            'visit_id' => 999999, // Non-existent visit
            'status_id' => $this->status->id,
            'intent_id' => $this->intent->id,
            'medication_id' => $this->medication->id,
            'requester_id' => $this->user->id,
            'quantity' => -1, // Invalid quantity
            'unit_id' => $this->unit->id,
            'instruction' => [
                'method_id' => $this->method->id,
                'morning' => 0.0,
                'afternoon' => 0.0,
                'evening' => 0.0,
                'night' => 0.0, // No doses provided
                'days' => 0, // Invalid days
            ],
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/prescriptions', $invalidData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'error' => [
                    'code',
                    'message',
                    'details' => [
                        'visit_id',
                        'quantity',
                        'instruction.days',
                        'instruction.doses',
                    ],
                ],
            ]);
    }

    public function test_can_get_patient_medication_history()
    {
        // Create some medication requests for the patient
        MedicationRequest::factory()->count(3)->create([
            'visit_id' => $this->visit->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/patients/{$this->patient->id}/medications");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'visit_id',
                        'quantity',
                        'total_dispensed',
                        'remaining_quantity',
                        'is_fully_dispensed',
                        'created_at',
                    ],
                ],
            ]);
    }

    public function test_can_get_visit_medications()
    {
        // Create some medication requests for the visit
        MedicationRequest::factory()->count(2)->create([
            'visit_id' => $this->visit->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/visits/{$this->visit->id}/medications");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'visit_id',
                        'quantity',
                        'created_at',
                    ],
                ],
            ]);
    }

    public function test_can_get_active_prescriptions()
    {
        // Create active medication request
        MedicationRequest::factory()->create([
            'visit_id' => $this->visit->id,
            'status_id' => $this->status->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/patients/{$this->patient->id}/active-prescriptions");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'visit_id',
                        'quantity',
                        'medication',
                        'instruction',
                    ],
                ],
            ]);
    }

    public function test_can_get_pending_dispenses()
    {
        // Create medication request with pending dispenses
        MedicationRequest::factory()->create([
            'visit_id' => $this->visit->id,
            'quantity' => 30,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/visits/{$this->visit->id}/pending-dispenses");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'visit_id',
                        'quantity',
                        'remaining_quantity',
                    ],
                ],
            ]);
    }

    public function test_can_get_medication_summary()
    {
        // Create multiple medication requests
        MedicationRequest::factory()->count(3)->create([
            'visit_id' => $this->visit->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/patients/{$this->patient->id}/medication-summary");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'total_requests',
                    'total_dispensed',
                    'pending_requests',
                    'medications_by_name',
                ],
            ]);
    }

    public function test_requires_authentication()
    {
        $response = $this->postJson('/api/v1/prescriptions', []);

        $response->assertStatus(401);
    }

    public function test_handles_nonexistent_patient()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/patients/999999/medications');

        $response->assertStatus(500); // Should be handled by the action
    }

    public function test_handles_nonexistent_visit()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/visits/999999/medications');

        $response->assertStatus(500); // Should be handled by the action
    }

    public function test_can_filter_medication_history()
    {
        // Create medication requests with different statuses
        $activeStatus = Term::factory()->create(['name' => 'active']);
        $completedStatus = Term::factory()->create(['name' => 'completed']);
        
        MedicationRequest::factory()->create([
            'visit_id' => $this->visit->id,
            'status_id' => $activeStatus->id,
        ]);
        
        MedicationRequest::factory()->create([
            'visit_id' => $this->visit->id,
            'status_id' => $completedStatus->id,
        ]);

        // Filter by status
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/patients/{$this->patient->id}/medications?status=active");

        $response->assertStatus(200);
        
        // Should only return active medications
        $data = $response->json('data');
        $this->assertCount(1, $data);
    }
}