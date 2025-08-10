<?php

namespace Tests\Feature\Api\V1;

use App\Models\Patient;
use App\Models\Visit;
use App\Models\Encounter;
use App\Models\Facility;
use App\Models\Department;
use App\Models\Room;
use App\Models\Term;
use App\Models\Terminology;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EncounterManagementTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;
    private Patient $patient;
    private Visit $visit;
    private Facility $facility;
    private Term $encounterType;
    private Term $transferEncounterType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);

        $this->patient = Patient::factory()->create();
        $this->facility = Facility::factory()->create();

        // Create encounter types
        $encounterTerminology = Terminology::factory()->create(['name' => 'encounter_types']);
        $this->encounterType = Term::factory()->create([
            'terminology_id' => $encounterTerminology->id,
            'code' => 'consultation',
            'name' => 'Consultation'
        ]);
        $this->transferEncounterType = Term::factory()->create([
            'terminology_id' => $encounterTerminology->id,
            'code' => 'transfer',
            'name' => 'Transfer'
        ]);

        // Create an active visit
        $this->visit = Visit::factory()->create([
            'patient_id' => $this->patient->id,
            'facility_id' => $this->facility->id,
            'admitted_at' => now()->subHours(2),
            'discharged_at' => null,
        ]);
    }

    public function test_can_create_encounter()
    {
        $encounterData = [
            'visit_id' => $this->visit->id,
            'encounter_type_id' => $this->encounterType->id,
            'encounter_form_id' => 1,
            'is_new' => true,
            'started_at' => now()->toISOString(),
        ];

        $response = $this->postJson('/api/v1/encounters', $encounterData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'visit_id',
                    'encounter_type_id',
                    'encounter_form_id',
                    'started_at',
                    'ended_at',
                    'is_active',
                    'encounter_type'
                ]
            ]);

        $this->assertDatabaseHas('encounters', [
            'visit_id' => $this->visit->id,
            'encounter_type_id' => $this->encounterType->id,
            'is_new' => 1,
        ]);
    }

    public function test_cannot_create_encounter_for_discharged_patient()
    {
        // Discharge the patient
        $this->visit->update(['discharged_at' => now()]);

        $encounterData = [
            'visit_id' => $this->visit->id,
            'encounter_type_id' => $this->encounterType->id,
        ];

        $response = $this->postJson('/api/v1/encounters', $encounterData);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Failed to create encounter: Cannot create encounter for discharged patient'
            ]);
    }

    public function test_can_get_encounter_details()
    {
        $encounter = Encounter::factory()->create([
            'visit_id' => $this->visit->id,
            'encounter_type_id' => $this->encounterType->id,
        ]);

        $response = $this->getJson("/api/v1/encounters/{$encounter->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'visit_id',
                    'encounter_type_id',
                    'started_at',
                    'ended_at',
                    'encounter_type'
                ]
            ]);
    }

    public function test_can_transfer_patient()
    {
        $department = Department::factory()->create(['facility_id' => $this->facility->id]);
        $room = Room::factory()->create(['department_id' => $department->id]);

        // Create an active encounter
        $activeEncounter = Encounter::factory()->create([
            'visit_id' => $this->visit->id,
            'encounter_type_id' => $this->encounterType->id,
            'started_at' => now()->subHour(),
            'ended_at' => null,
        ]);

        $transferData = [
            'visit_id' => $this->visit->id,
            'destination_department_id' => $department->id,
            'destination_room_id' => $room->id,
            'destination_encounter_type_id' => $this->encounterType->id,
            'transfer_at' => now()->toISOString(),
            'reason' => 'Transfer to ICU for monitoring',
        ];

        $response = $this->postJson('/api/v1/encounters/transfer', $transferData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'visit_id',
                    'encounter_type_id',
                    'started_at',
                    'ended_at'
                ]
            ]);

        // Check that the active encounter was ended
        $activeEncounter->refresh();
        $this->assertNotNull($activeEncounter->ended_at);

        // Check that transfer encounter was created
        $this->assertDatabaseHas('encounters', [
            'visit_id' => $this->visit->id,
            'encounter_type_id' => $this->transferEncounterType->id,
        ]);
    }

    public function test_cannot_transfer_discharged_patient()
    {
        // Discharge the patient
        $this->visit->update(['discharged_at' => now()]);

        $department = Department::factory()->create(['facility_id' => $this->facility->id]);
        
        $transferData = [
            'visit_id' => $this->visit->id,
            'destination_department_id' => $department->id,
        ];

        $response = $this->postJson('/api/v1/encounters/transfer', $transferData);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Failed to transfer patient: Cannot transfer discharged patient'
            ]);
    }

    public function test_can_get_chronological_encounters()
    {
        // Create multiple encounters
        $encounter1 = Encounter::factory()->create([
            'visit_id' => $this->visit->id,
            'encounter_type_id' => $this->encounterType->id,
            'started_at' => now()->subHours(2),
            'ended_at' => now()->subHours(1),
        ]);

        $encounter2 = Encounter::factory()->create([
            'visit_id' => $this->visit->id,
            'encounter_type_id' => $this->encounterType->id,
            'started_at' => now()->subHour(),
            'ended_at' => null,
        ]);

        $response = $this->getJson("/api/v1/visits/{$this->visit->id}/encounters/chronological");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'visit_id',
                    'patient_id',
                    'encounters' => [
                        '*' => [
                            'id',
                            'type',
                            'type_code',
                            'started_at',
                            'ended_at',
                            'duration_minutes',
                            'is_active',
                            'observations_count',
                            'is_new'
                        ]
                    ],
                    'total_encounters',
                    'active_encounters'
                ]
            ]);

        $responseData = $response->json('data');
        $this->assertEquals(2, $responseData['total_encounters']);
        $this->assertEquals(1, $responseData['active_encounters']);
        
        // Check chronological order
        $encounters = $responseData['encounters'];
        $this->assertTrue(
            strtotime($encounters[0]['started_at']) <= strtotime($encounters[1]['started_at'])
        );
    }

    public function test_encounter_validation_rules()
    {
        $response = $this->postJson('/api/v1/encounters', []);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'The given data was invalid.',
                    'details' => [
                        'visit_id' => ['Visit ID is required'],
                        'encounter_type_id' => ['Encounter type is required']
                    ]
                ]
            ]);
    }

    public function test_transfer_validation_rules()
    {
        $response = $this->postJson('/api/v1/encounters/transfer', []);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'The given data was invalid.',
                    'details' => [
                        'visit_id' => ['Visit ID is required']
                    ]
                ]
            ]);
    }
}