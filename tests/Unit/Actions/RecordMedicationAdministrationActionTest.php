<?php

namespace Tests\Unit\Actions;

use App\Actions\RecordMedicationAdministrationAction;
use App\Models\MedicationAdministration;
use App\Models\MedicationInstruction;
use App\Models\MedicationRequest;
use App\Models\Patient;
use App\Models\Term;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RecordMedicationAdministrationActionTest extends TestCase
{
    use RefreshDatabase;

    private RecordMedicationAdministrationAction $action;
    private MedicationRequest $medicationRequest;
    private User $administrator;
    private Term $status;
    private Term $doseUnit;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->action = new RecordMedicationAdministrationAction();
        
        // Create test data
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create([
            'patient_id' => $patient->id,
            'discharged_at' => null, // Active visit
        ]);
        
        $instruction = MedicationInstruction::factory()->create([
            'morning' => 2.0,
            'afternoon' => 2.0,
            'evening' => 1.0,
            'night' => 0.0,
        ]);
        
        $this->medicationRequest = MedicationRequest::factory()->create([
            'visit_id' => $visit->id,
            'instruction_id' => $instruction->id,
        ]);
        
        $this->administrator = User::factory()->create();
        $this->status = Term::factory()->create(['name' => 'administered']);
        $this->doseUnit = Term::factory()->create(['name' => 'mg']);
    }

    public function test_records_administration_successfully()
    {
        $data = [
            'medication_request_id' => $this->medicationRequest->id,
            'status_id' => $this->status->id,
            'administrator_id' => $this->administrator->id,
            'dose_given' => 2.0,
            'dose_unit_id' => $this->doseUnit->id,
            'administered_at' => now(),
            'notes' => 'Patient tolerated well',
        ];

        $result = $this->action->execute($data);

        $this->assertInstanceOf(MedicationAdministration::class, $result);
        $this->assertEquals($this->medicationRequest->id, $result->medication_request_id);
        $this->assertEquals(2.0, $result->dose_given);
        $this->assertEquals($this->administrator->id, $result->administrator_id);
        $this->assertEquals('Patient tolerated well', $result->notes);
        
        $this->assertDatabaseHas('medication_administrations', [
            'medication_request_id' => $this->medicationRequest->id,
            'dose_given' => 2.0,
            'administrator_id' => $this->administrator->id,
            'notes' => 'Patient tolerated well',
        ]);
    }

    public function test_fails_for_discharged_visit()
    {
        $this->medicationRequest->visit->update(['discharged_at' => now()]);

        $data = [
            'medication_request_id' => $this->medicationRequest->id,
            'status_id' => $this->status->id,
            'administrator_id' => $this->administrator->id,
            'dose_given' => 2.0,
            'dose_unit_id' => $this->doseUnit->id,
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Cannot administer medication for discharged visit.');

        $this->action->execute($data);
    }

    public function test_fails_when_dose_exceeds_safe_limits()
    {
        $data = [
            'medication_request_id' => $this->medicationRequest->id,
            'status_id' => $this->status->id,
            'administrator_id' => $this->administrator->id,
            'dose_given' => 10.0, // Max single dose is 2.0, so 10.0 exceeds 2 * 2.0 = 4.0
            'dose_unit_id' => $this->doseUnit->id,
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Dose given (10) exceeds safe limits based on prescription.');

        $this->action->execute($data);
    }

    public function test_records_administration_with_vital_signs()
    {
        $vitalSignsBefore = [
            'temperature' => 37.2,
            'blood_pressure_systolic' => 120,
            'blood_pressure_diastolic' => 80,
            'heart_rate' => 72,
        ];

        $vitalSignsAfter = [
            'temperature' => 37.0,
            'blood_pressure_systolic' => 118,
            'blood_pressure_diastolic' => 78,
            'heart_rate' => 70,
        ];

        $data = [
            'medication_request_id' => $this->medicationRequest->id,
            'status_id' => $this->status->id,
            'administrator_id' => $this->administrator->id,
            'dose_given' => 2.0,
            'dose_unit_id' => $this->doseUnit->id,
            'vital_signs_before' => $vitalSignsBefore,
            'vital_signs_after' => $vitalSignsAfter,
        ];

        $result = $this->action->execute($data);

        $this->assertEquals($vitalSignsBefore, $result->vital_signs_before);
        $this->assertEquals($vitalSignsAfter, $result->vital_signs_after);
    }

    public function test_records_administration_with_adverse_reactions()
    {
        $data = [
            'medication_request_id' => $this->medicationRequest->id,
            'status_id' => $this->status->id,
            'administrator_id' => $this->administrator->id,
            'dose_given' => 2.0,
            'dose_unit_id' => $this->doseUnit->id,
            'adverse_reactions' => 'Patient experienced mild nausea',
        ];

        $result = $this->action->execute($data);

        $this->assertEquals('Patient experienced mild nausea', $result->adverse_reactions);
        $this->assertTrue($result->has_adverse_reactions);
    }

    public function test_validates_data_correctly()
    {
        $validData = [
            'medication_request_id' => $this->medicationRequest->id,
            'status_id' => $this->status->id,
            'administrator_id' => $this->administrator->id,
            'dose_given' => 2.0,
            'dose_unit_id' => $this->doseUnit->id,
            'notes' => 'Test administration',
        ];

        $result = $this->action->validateData($validData);
        
        $this->assertEquals($validData, $result);
    }

    public function test_loads_relationships_correctly()
    {
        $data = [
            'medication_request_id' => $this->medicationRequest->id,
            'status_id' => $this->status->id,
            'administrator_id' => $this->administrator->id,
            'dose_given' => 2.0,
            'dose_unit_id' => $this->doseUnit->id,
        ];

        $result = $this->action->execute($data);

        $this->assertTrue($result->relationLoaded('visit'));
        $this->assertTrue($result->relationLoaded('medicationRequest'));
        $this->assertTrue($result->relationLoaded('status'));
        $this->assertTrue($result->relationLoaded('administrator'));
        $this->assertTrue($result->relationLoaded('doseUnit'));
        $this->assertTrue($result->visit->relationLoaded('patient'));
    }
}