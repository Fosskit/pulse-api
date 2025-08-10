<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Models\Patient;
use App\Models\Visit;
use App\Models\Encounter;
use App\Models\ClinicalFormTemplate;
use App\Models\Term;
use App\Models\Terminology;
use App\Models\Department;
use App\Models\Room;
use App\Models\Facility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class EncounterManagementTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $patient;
    protected $visit;
    protected $facility;
    protected $department;
    protected $room;
    protected $encounterType;
    protected $clinicalForm;
    protected $encounterTypesTerminology;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user
        $this->user = User::factory()->create();

        // Create test facility
        $this->facility = Facility::factory()->create();

        // Create test department and room
        $this->department = Department::factory()->create([
            'facility_id' => $this->facility->id
        ]);

        $this->room = Room::factory()->create([
            'department_id' => $this->department->id
        ]);

        // Create test patient and visit
        $this->patient = Patient::factory()->create([
            'facility_id' => $this->facility->id
        ]);

        $this->visit = Visit::factory()->create([
            'patient_id' => $this->patient->id,
            'facility_id' => $this->facility->id,
            'admitted_at' => now(),
            'discharged_at' => null,
        ]);

        // Create terminology for encounter types
        $this->encounterTypesTerminology = Terminology::factory()->create([
            'code' => 'encounter_types',
            'name' => 'Encounter Types'
        ]);

        // Create encounter type
        $this->encounterType = Term::factory()->create([
            'code' => 'consultation',
            'name' => 'Consultation',
            'terminology_id' => $this->encounterTypesTerminology->id
        ]);

        // Create transfer encounter type
        Term::factory()->create([
            'code' => 'transfer',
            'name' => 'Transfer',
            'terminology_id' => $this->encounterTypesTerminology->id
        ]);

        // Create clinical form template
        $this->clinicalForm = ClinicalFormTemplate::factory()->vitalSigns()->create();
    }

    public function test_can_create_encounter_with_clinical_form()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/encounters', [
                'visit_id' => $this->visit->id,
                'encounter_type_id' => $this->encounterType->id,
                'encounter_form_id' => $this->clinicalForm->id,
                'is_new' => true,
                'started_at' => now()->toISOString(),
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'visit_id',
                    'encounter_type_id',
                    'encounter_form_id',
                    'is_new',
                    'started_at',
                    'ended_at',
                    'is_active',
                    'encounter_type',
                    'clinical_form_template',
                ]
            ]);

        $this->assertDatabaseHas('encounters', [
            'visit_id' => $this->visit->id,
            'encounter_type_id' => $this->encounterType->id,
            'encounter_form_id' => $this->clinicalForm->id,
            'is_new' => true,
        ]);
    }

    public function test_can_submit_form_data_for_encounter()
    {
        $encounter = Encounter::factory()->create([
            'visit_id' => $this->visit->id,
            'encounter_type_id' => $this->encounterType->id,
            'encounter_form_id' => $this->clinicalForm->id,
            'started_at' => now(),
            'ended_at' => null,
        ]);

        $formData = [
            'temperature' => 37.5,
            'blood_pressure_systolic' => 120,
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/encounters/{$encounter->id}/forms", [
                'form_data' => $formData,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'encounter',
                    'observations',
                    'observations_created'
                ]
            ]);

        // Verify observations were created
        $this->assertDatabaseHas('observations', [
            'encounter_id' => $encounter->id,
            'patient_id' => $this->patient->id,
            'value_number' => 37.5,
        ]);

        $this->assertDatabaseHas('observations', [
            'encounter_id' => $encounter->id,
            'patient_id' => $this->patient->id,
            'value_number' => 120,
        ]);
    }

    public function test_can_transfer_patient_between_departments()
    {
        $destinationDepartment = Department::factory()->create([
            'facility_id' => $this->facility->id
        ]);

        $destinationRoom = Room::factory()->create([
            'department_id' => $destinationDepartment->id
        ]);

        $destinationEncounterType = Term::factory()->create([
            'code' => 'inpatient',
            'name' => 'Inpatient Care',
            'terminology_id' => $this->encounterTypesTerminology->id
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/encounters/transfer-patient', [
                'visit_id' => $this->visit->id,
                'destination_department_id' => $destinationDepartment->id,
                'destination_room_id' => $destinationRoom->id,
                'destination_encounter_type_id' => $destinationEncounterType->id,
                'reason' => 'Patient needs specialized care',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'transfer_encounter',
                    'destination_encounter',
                    'transfer_details' => [
                        'destination_department',
                        'destination_room',
                        'transfer_at',
                        'reason',
                        'active_encounters_ended'
                    ]
                ]
            ]);

        // Verify transfer encounter was created
        $this->assertDatabaseHas('encounters', [
            'visit_id' => $this->visit->id,
            'encounter_type_id' => Term::where('code', 'transfer')->first()->id,
        ]);

        // Verify destination encounter was created
        $this->assertDatabaseHas('encounters', [
            'visit_id' => $this->visit->id,
            'encounter_type_id' => $destinationEncounterType->id,
        ]);
    }

    public function test_can_get_chronological_encounters_for_visit()
    {
        // Create multiple encounters
        $encounter1 = Encounter::factory()->create([
            'visit_id' => $this->visit->id,
            'encounter_type_id' => $this->encounterType->id,
            'encounter_form_id' => $this->clinicalForm->id,
            'started_at' => now()->subHours(2),
            'ended_at' => now()->subHours(1),
        ]);

        $encounter2 = Encounter::factory()->create([
            'visit_id' => $this->visit->id,
            'encounter_type_id' => $this->encounterType->id,
            'encounter_form_id' => $this->clinicalForm->id,
            'started_at' => now()->subHour(),
            'ended_at' => null, // Active encounter
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/visits/{$this->visit->id}/encounters/chronological");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'visit_id',
                    'patient_id',
                    'visit_status',
                    'encounters' => [
                        '*' => [
                            'id',
                            'type',
                            'type_code',
                            'started_at',
                            'ended_at',
                            'duration_minutes',
                            'is_active',
                            'status',
                            'clinical_form',
                            'observations_summary',
                        ]
                    ],
                    'summary' => [
                        'total_encounters',
                        'active_encounters',
                        'completed_encounters',
                        'total_duration_minutes',
                        'total_observations',
                    ]
                ]
            ]);

        $responseData = $response->json('data');
        $this->assertEquals(2, $responseData['summary']['total_encounters']);
        $this->assertEquals(1, $responseData['summary']['active_encounters']);
        $this->assertEquals(1, $responseData['summary']['completed_encounters']);
    }

    public function test_cannot_submit_form_for_discharged_patient()
    {
        // Discharge the patient
        $this->visit->update(['discharged_at' => now()]);

        $encounter = Encounter::factory()->create([
            'visit_id' => $this->visit->id,
            'encounter_type_id' => $this->encounterType->id,
            'encounter_form_id' => $this->clinicalForm->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/encounters/{$encounter->id}/forms", [
                'form_data' => ['temperature' => 37.5],
            ]);

        $response->assertStatus(400)
            ->assertJsonFragment([
                'success' => false
            ]);
    }

    public function test_form_validation_works_correctly()
    {
        $encounter = Encounter::factory()->create([
            'visit_id' => $this->visit->id,
            'encounter_type_id' => $this->encounterType->id,
            'encounter_form_id' => $this->clinicalForm->id,
        ]);

        // Submit invalid data (temperature too high)
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/encounters/{$encounter->id}/forms", [
                'form_data' => [
                    'temperature' => 50.0, // Too high
                    'blood_pressure_systolic' => 120,
                ],
            ]);

        $response->assertStatus(400)
            ->assertJsonFragment([
                'success' => false
            ]);
    }
}