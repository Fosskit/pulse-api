<?php

namespace Tests\Feature\Api\V1;

use App\Models\MedicationRequest;
use App\Models\Patient;
use App\Models\Term;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedicationDispensingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Patient $patient;
    private Visit $visit;
    private MedicationRequest $medicationRequest;
    private Term $status;
    private Term $unit;

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
        
        $this->medicationRequest = MedicationRequest::factory()->create([
            'visit_id' => $this->visit->id,
            'quantity' => 30,
        ]);
        
        $this->status = Term::factory()->create(['name' => 'dispensed']);
        $this->unit = Term::factory()->create(['name' => 'tablet']);
    }

    public function test_can_dispense_medication()
    {
        $dispenseData = [
            'medication_request_id' => $this->medicationRequest->id,
            'status_id' => $this->status->id,
            'dispenser_id' => $this->user->id,
            'quantity' => 15,
            'unit_id' => $this->unit->id,
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/medications/dispense', $dispenseData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'ulid',
                    'visit_id',
                    'medication_request_id',
                    'quantity',
                    'is_dispensed',
                    'status',
                    'dispenser',
                    'unit',
                ],
            ]);

        $this->assertDatabaseHas('medication_dispenses', [
            'medication_request_id' => $this->medicationRequest->id,
            'quantity' => 15,
            'dispenser_id' => $this->user->id,
        ]);
    }

    public function test_can_record_medication_administration()
    {
        $administrationData = [
            'medication_request_id' => $this->medicationRequest->id,
            'status_id' => $this->status->id,
            'administrator_id' => $this->user->id,
            'dose_given' => 2.0,
            'dose_unit_id' => $this->unit->id,
            'administered_at' => now()->toISOString(),
            'notes' => 'Patient tolerated well',
            'vital_signs_before' => [
                'temperature' => 37.2,
                'blood_pressure_systolic' => 120,
                'blood_pressure_diastolic' => 80,
                'heart_rate' => 72,
            ],
            'vital_signs_after' => [
                'temperature' => 37.0,
                'blood_pressure_systolic' => 118,
                'blood_pressure_diastolic' => 78,
                'heart_rate' => 70,
            ],
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/medications/administer', $administrationData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'ulid',
                    'visit_id',
                    'medication_request_id',
                    'dose_given',
                    'administered_at',
                    'notes',
                    'vital_signs_before',
                    'vital_signs_after',
                    'is_administered',
                    'has_adverse_reactions',
                    'status',
                    'administrator',
                    'dose_unit',
                ],
            ]);

        $this->assertDatabaseHas('medication_administrations', [
            'medication_request_id' => $this->medicationRequest->id,
            'dose_given' => 2.0,
            'administrator_id' => $this->user->id,
            'notes' => 'Patient tolerated well',
        ]);
    }

    public function test_can_validate_medication_safety()
    {
        $medication = Term::factory()->create(['name' => 'Paracetamol']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/medications/safety-check/{$this->patient->id}/{$medication->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'is_safe',
                    'has_warnings',
                    'errors',
                    'warnings',
                    'patient_id',
                    'medication_id',
                    'medication_name',
                    'checked_at',
                ],
            ]);
    }

    public function test_can_get_visit_administrations()
    {
        // Create some administrations for the visit
        \App\Models\MedicationAdministration::factory()->count(2)->create([
            'visit_id' => $this->visit->id,
            'medication_request_id' => $this->medicationRequest->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/visits/{$this->visit->id}/administrations");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'visit_id',
                        'medication_request_id',
                        'dose_given',
                        'administered_at',
                        'is_administered',
                    ],
                ],
            ]);
    }

    public function test_can_get_patient_administrations()
    {
        // Create some administrations for the patient
        \App\Models\MedicationAdministration::factory()->count(3)->create([
            'visit_id' => $this->visit->id,
            'medication_request_id' => $this->medicationRequest->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/patients/{$this->patient->id}/administrations");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'visit_id',
                        'medication_request_id',
                        'dose_given',
                        'administered_at',
                        'is_administered',
                    ],
                ],
            ]);
    }

    public function test_dispense_validation_fails_with_invalid_data()
    {
        $invalidData = [
            'medication_request_id' => 999999, // Non-existent
            'status_id' => $this->status->id,
            'dispenser_id' => $this->user->id,
            'quantity' => -1, // Invalid quantity
            'unit_id' => $this->unit->id,
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/medications/dispense', $invalidData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'error' => [
                    'code',
                    'message',
                    'details' => [
                        'medication_request_id',
                        'quantity',
                    ],
                ],
            ]);
    }

    public function test_administration_validation_fails_with_invalid_data()
    {
        $invalidData = [
            'medication_request_id' => 999999, // Non-existent
            'status_id' => $this->status->id,
            'administrator_id' => $this->user->id,
            'dose_given' => -1, // Invalid dose
            'dose_unit_id' => $this->unit->id,
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/medications/administer', $invalidData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'error' => [
                    'code',
                    'message',
                    'details' => [
                        'medication_request_id',
                        'dose_given',
                    ],
                ],
            ]);
    }

    public function test_requires_authentication()
    {
        $response = $this->postJson('/api/v1/medications/dispense', []);
        $response->assertStatus(401);

        $response = $this->postJson('/api/v1/medications/administer', []);
        $response->assertStatus(401);

        $response = $this->getJson('/api/v1/medications/safety-check/1/1');
        $response->assertStatus(401);
    }

    public function test_handles_nonexistent_resources()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/visits/999999/administrations');
        $response->assertStatus(200); // Should return empty array

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/patients/999999/administrations');
        $response->assertStatus(200); // Should return empty array for nonexistent patient
    }

    public function test_can_filter_patient_administrations()
    {
        // Create administration without adverse reactions
        \App\Models\MedicationAdministration::factory()->create([
            'visit_id' => $this->visit->id,
            'medication_request_id' => $this->medicationRequest->id,
            'administered_at' => now()->subDays(2),
            'adverse_reactions' => null, // Explicitly no adverse reactions
        ]);

        // Create administration with adverse reactions
        \App\Models\MedicationAdministration::factory()->create([
            'visit_id' => $this->visit->id,
            'medication_request_id' => $this->medicationRequest->id,
            'administered_at' => now(),
            'adverse_reactions' => 'Mild nausea',
        ]);

        // Filter by date
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/patients/{$this->patient->id}/administrations?date_from=" . now()->toDateString());

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);

        // Filter by adverse reactions
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/patients/{$this->patient->id}/administrations?with_adverse_reactions=true");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertNotNull($data[0]['adverse_reactions']);
    }
}