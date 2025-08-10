<?php

namespace Tests\Feature\Api\V1;

use App\Models\Patient;
use App\Models\Visit;
use App\Models\Facility;
use App\Models\Term;
use App\Models\Terminology;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VisitManagementTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;
    private Patient $patient;
    private Facility $facility;
    private Term $visitType;
    private Term $admissionType;
    private Term $dischargeType;
    private Term $visitOutcome;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);

        $this->patient = Patient::factory()->create();
        $this->facility = Facility::factory()->create();

        // Create terminology for visit types
        $terminology = Terminology::factory()->create(['name' => 'visit_types']);
        $this->visitType = Term::factory()->create([
            'terminology_id' => $terminology->id,
            'code' => 'outpatient',
            'name' => 'Outpatient Visit'
        ]);

        // Create terminology for admission types
        $admissionTerminology = Terminology::factory()->create(['name' => 'admission_types']);
        $this->admissionType = Term::factory()->create([
            'terminology_id' => $admissionTerminology->id,
            'code' => 'emergency',
            'name' => 'Emergency Admission'
        ]);

        // Create terminology for discharge types
        $dischargeTerminology = Terminology::factory()->create(['name' => 'discharge_types']);
        $this->dischargeType = Term::factory()->create([
            'terminology_id' => $dischargeTerminology->id,
            'code' => 'home',
            'name' => 'Discharged Home'
        ]);

        // Create terminology for visit outcomes
        $outcomeTerminology = Terminology::factory()->create(['name' => 'visit_outcomes']);
        $this->visitOutcome = Term::factory()->create([
            'terminology_id' => $outcomeTerminology->id,
            'code' => 'improved',
            'name' => 'Patient Improved'
        ]);

        // Create encounter types
        $encounterTerminology = Terminology::factory()->create(['name' => 'encounter_types']);
        Term::factory()->create([
            'terminology_id' => $encounterTerminology->id,
            'code' => 'admission',
            'name' => 'Admission Encounter'
        ]);
        Term::factory()->create([
            'terminology_id' => $encounterTerminology->id,
            'code' => 'discharge',
            'name' => 'Discharge Encounter'
        ]);
    }

    public function test_can_admit_patient()
    {
        $admissionData = [
            'patient_id' => $this->patient->id,
            'facility_id' => $this->facility->id,
            'visit_type_id' => $this->visitType->id,
            'admission_type_id' => $this->admissionType->id,
            'admitted_at' => now()->toISOString(),
        ];

        $response = $this->postJson('/api/v1/visits', $admissionData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'patient_id',
                    'facility_id',
                    'visit_type_id',
                    'admission_type_id',
                    'admitted_at',
                    'discharged_at',
                    'is_active',
                    'duration_days',
                    'encounters'
                ]
            ]);

        $this->assertDatabaseHas('visits', [
            'patient_id' => $this->patient->id,
            'facility_id' => $this->facility->id,
            'visit_type_id' => $this->visitType->id,
            'admission_type_id' => $this->admissionType->id,
        ]);

        // Check that admission encounter was created
        $visit = Visit::where('patient_id', $this->patient->id)->first();
        $this->assertDatabaseHas('encounters', [
            'visit_id' => $visit->id,
        ]);
    }

    public function test_cannot_admit_patient_with_active_visit()
    {
        // Create an active visit
        Visit::factory()->create([
            'patient_id' => $this->patient->id,
            'facility_id' => $this->facility->id,
            'admitted_at' => now(),
            'discharged_at' => null,
        ]);

        $admissionData = [
            'patient_id' => $this->patient->id,
            'facility_id' => $this->facility->id,
            'visit_type_id' => $this->visitType->id,
            'admission_type_id' => $this->admissionType->id,
        ];

        $response = $this->postJson('/api/v1/visits', $admissionData);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Failed to admit patient: Patient already has an active visit'
            ]);
    }

    public function test_can_discharge_patient()
    {
        $visit = Visit::factory()->create([
            'patient_id' => $this->patient->id,
            'facility_id' => $this->facility->id,
            'admitted_at' => now()->subDays(2),
            'discharged_at' => null,
        ]);

        $dischargeData = [
            'discharge_type_id' => $this->dischargeType->id,
            'visit_outcome_id' => $this->visitOutcome->id,
            'discharged_at' => now()->toISOString(),
        ];

        $response = $this->postJson("/api/v1/visits/{$visit->id}/discharge", $dischargeData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'discharged_at',
                    'discharge_type_id',
                    'visit_outcome_id',
                    'is_active'
                ]
            ]);

        $this->assertDatabaseHas('visits', [
            'id' => $visit->id,
            'discharge_type_id' => $this->dischargeType->id,
            'visit_outcome_id' => $this->visitOutcome->id,
        ]);

        $visit->refresh();
        $this->assertNotNull($visit->discharged_at);
        $this->assertFalse($visit->is_active);
    }

    public function test_cannot_discharge_already_discharged_patient()
    {
        $visit = Visit::factory()->create([
            'patient_id' => $this->patient->id,
            'facility_id' => $this->facility->id,
            'admitted_at' => now()->subDays(2),
            'discharged_at' => now()->subDay(),
            'discharge_type_id' => $this->dischargeType->id,
        ]);

        $dischargeData = [
            'discharge_type_id' => $this->dischargeType->id,
            'visit_outcome_id' => $this->visitOutcome->id,
        ];

        $response = $this->postJson("/api/v1/visits/{$visit->id}/discharge", $dischargeData);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Failed to discharge patient: Patient is already discharged'
            ]);
    }

    public function test_can_get_visit_details()
    {
        $visit = Visit::factory()->create([
            'patient_id' => $this->patient->id,
            'facility_id' => $this->facility->id,
            'visit_type_id' => $this->visitType->id,
            'admission_type_id' => $this->admissionType->id,
        ]);

        $response = $this->getJson("/api/v1/visits/{$visit->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'patient_id',
                    'facility_id',
                    'visit_type_id',
                    'admission_type_id',
                    'admitted_at',
                    'discharged_at',
                    'is_active',
                    'duration_days',
                    'patient',
                    'facility',
                    'visit_type',
                    'admission_type'
                ]
            ]);
    }

    public function test_can_get_visit_timeline()
    {
        $visit = Visit::factory()->create([
            'patient_id' => $this->patient->id,
            'facility_id' => $this->facility->id,
            'admitted_at' => now()->subDays(2),
        ]);

        $response = $this->getJson("/api/v1/visits/{$visit->id}/timeline");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'visit_id',
                    'patient_id',
                    'admitted_at',
                    'discharged_at',
                    'is_active',
                    'duration_days',
                    'encounters'
                ]
            ]);
    }

    public function test_admission_validation_rules()
    {
        $response = $this->postJson('/api/v1/visits', []);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'The given data was invalid.',
                    'details' => [
                        'patient_id' => ['Patient ID is required'],
                        'facility_id' => ['Facility ID is required'],
                        'visit_type_id' => ['Visit type is required'],
                        'admission_type_id' => ['Admission type is required']
                    ]
                ]
            ]);
    }

    public function test_discharge_validation_rules()
    {
        $visit = Visit::factory()->create([
            'patient_id' => $this->patient->id,
            'facility_id' => $this->facility->id,
            'discharged_at' => null,
        ]);

        $response = $this->postJson("/api/v1/visits/{$visit->id}/discharge", []);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'The given data was invalid.',
                    'details' => [
                        'discharge_type_id' => ['Discharge type is required']
                    ]
                ]
            ]);
    }
}