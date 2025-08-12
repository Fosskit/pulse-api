<?php

namespace Tests\Unit\Actions;

use App\Actions\ValidateMedicationSafetyAction;
use App\Models\Patient;
use App\Models\PatientDemographic;
use App\Models\Visit;
use App\Models\MedicationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValidateMedicationSafetyActionTest extends TestCase
{
    use RefreshDatabase;

    private ValidateMedicationSafetyAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new ValidateMedicationSafetyAction();
    }

    public function test_returns_safe_when_no_conflicts()
    {
        $patient = Patient::factory()->create();
        PatientDemographic::factory()->create([
            'patient_id' => $patient->id,
            'allergies' => null
        ]);

        $result = $this->action->execute($patient->id, 1);

        $this->assertTrue($result['safe']);
        $this->assertEmpty($result['warnings']);
        $this->assertEmpty($result['contraindications']);
    }

    public function test_detects_allergy_conflicts()
    {
        $patient = Patient::factory()->create();
        PatientDemographic::factory()->create([
            'patient_id' => $patient->id,
            'allergies' => json_encode(['penicillin', 'sulfa'])
        ]);

        $result = $this->action->execute($patient->id, 1, [
            'medication_name' => 'Penicillin',
            'check_allergies' => true
        ]);

        $this->assertFalse($result['safe']);
        $this->assertNotEmpty($result['contraindications']);
        $this->assertStringContainsString('allergy', strtolower($result['contraindications'][0]));
    }

    public function test_detects_drug_interactions()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);

        // Create existing medication request
        MedicationRequest::factory()->create([
            'visit_id' => $visit->id,
            'medication_name' => 'Warfarin',
            'status_id' => 1 // Active
        ]);

        $result = $this->action->execute($patient->id, 2, [
            'medication_name' => 'Aspirin',
            'check_interactions' => true
        ]);

        $this->assertFalse($result['safe']);
        $this->assertNotEmpty($result['warnings']);
        $this->assertStringContainsString('interaction', strtolower($result['warnings'][0]));
    }

    public function test_validates_age_restrictions()
    {
        $patient = Patient::factory()->create();
        PatientDemographic::factory()->create([
            'patient_id' => $patient->id,
            'date_of_birth' => now()->subYears(5) // 5 years old
        ]);

        $result = $this->action->execute($patient->id, 3, [
            'medication_name' => 'Adult Medication',
            'min_age' => 18,
            'check_age' => true
        ]);

        $this->assertFalse($result['safe']);
        $this->assertNotEmpty($result['contraindications']);
        $this->assertStringContainsString('age', strtolower($result['contraindications'][0]));
    }

    public function test_validates_pregnancy_restrictions()
    {
        $patient = Patient::factory()->create();
        PatientDemographic::factory()->create([
            'patient_id' => $patient->id,
            'gender' => 'female',
            'date_of_birth' => now()->subYears(25),
            'pregnancy_status' => 'pregnant'
        ]);

        $result = $this->action->execute($patient->id, 4, [
            'medication_name' => 'Teratogenic Drug',
            'pregnancy_category' => 'X',
            'check_pregnancy' => true
        ]);

        $this->assertFalse($result['safe']);
        $this->assertNotEmpty($result['contraindications']);
        $this->assertStringContainsString('pregnancy', strtolower($result['contraindications'][0]));
    }

    public function test_validates_kidney_function()
    {
        $patient = Patient::factory()->create();
        PatientDemographic::factory()->create([
            'patient_id' => $patient->id,
            'medical_conditions' => json_encode(['chronic_kidney_disease'])
        ]);

        $result = $this->action->execute($patient->id, 5, [
            'medication_name' => 'Nephrotoxic Drug',
            'kidney_adjustment' => true,
            'check_kidney_function' => true
        ]);

        $this->assertFalse($result['safe']);
        $this->assertNotEmpty($result['warnings']);
        $this->assertStringContainsString('kidney', strtolower($result['warnings'][0]));
    }

    public function test_validates_liver_function()
    {
        $patient = Patient::factory()->create();
        PatientDemographic::factory()->create([
            'patient_id' => $patient->id,
            'medical_conditions' => json_encode(['liver_disease'])
        ]);

        $result = $this->action->execute($patient->id, 6, [
            'medication_name' => 'Hepatotoxic Drug',
            'liver_adjustment' => true,
            'check_liver_function' => true
        ]);

        $this->assertFalse($result['safe']);
        $this->assertNotEmpty($result['warnings']);
        $this->assertStringContainsString('liver', strtolower($result['warnings'][0]));
    }

    public function test_checks_dosage_limits()
    {
        $patient = Patient::factory()->create();
        PatientDemographic::factory()->create([
            'patient_id' => $patient->id,
            'weight' => 70 // kg
        ]);

        $result = $this->action->execute($patient->id, 7, [
            'medication_name' => 'Test Drug',
            'dosage' => 1000, // mg
            'max_daily_dose' => 500, // mg
            'check_dosage' => true
        ]);

        $this->assertFalse($result['safe']);
        $this->assertNotEmpty($result['warnings']);
        $this->assertStringContainsString('dosage', strtolower($result['warnings'][0]));
    }

    public function test_handles_multiple_safety_issues()
    {
        $patient = Patient::factory()->create();
        PatientDemographic::factory()->create([
            'patient_id' => $patient->id,
            'allergies' => json_encode(['penicillin']),
            'date_of_birth' => now()->subYears(10), // 10 years old
            'medical_conditions' => json_encode(['kidney_disease'])
        ]);

        $result = $this->action->execute($patient->id, 8, [
            'medication_name' => 'Penicillin',
            'min_age' => 18,
            'kidney_adjustment' => true,
            'check_allergies' => true,
            'check_age' => true,
            'check_kidney_function' => true
        ]);

        $this->assertFalse($result['safe']);
        $this->assertNotEmpty($result['contraindications']);
        $this->assertNotEmpty($result['warnings']);
        $this->assertGreaterThan(1, count($result['contraindications']) + count($result['warnings']));
    }

    public function test_handles_nonexistent_patient()
    {
        $result = $this->action->execute(999, 1);

        $this->assertFalse($result['safe']);
        $this->assertNotEmpty($result['contraindications']);
        $this->assertStringContainsString('patient not found', strtolower($result['contraindications'][0]));
    }

    public function test_validates_patient_id_parameter()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->action->execute(null, 1);
    }

    public function test_validates_medication_id_parameter()
    {
        $patient = Patient::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        $this->action->execute($patient->id, null);
    }

    public function test_returns_safety_score()
    {
        $patient = Patient::factory()->create();
        PatientDemographic::factory()->create([
            'patient_id' => $patient->id,
            'allergies' => json_encode(['sulfa']) // One minor allergy
        ]);

        $result = $this->action->execute($patient->id, 9, [
            'medication_name' => 'Safe Drug',
            'check_allergies' => true,
            'include_score' => true
        ]);

        $this->assertArrayHasKey('safety_score', $result);
        $this->assertIsNumeric($result['safety_score']);
        $this->assertGreaterThanOrEqual(0, $result['safety_score']);
        $this->assertLessThanOrEqual(100, $result['safety_score']);
    }

    public function test_provides_recommendations()
    {
        $patient = Patient::factory()->create();
        PatientDemographic::factory()->create([
            'patient_id' => $patient->id,
            'medical_conditions' => json_encode(['kidney_disease'])
        ]);

        $result = $this->action->execute($patient->id, 10, [
            'medication_name' => 'Nephrotoxic Drug',
            'kidney_adjustment' => true,
            'check_kidney_function' => true,
            'include_recommendations' => true
        ]);

        $this->assertArrayHasKey('recommendations', $result);
        $this->assertNotEmpty($result['recommendations']);
        $this->assertStringContainsString('monitor', strtolower($result['recommendations'][0]));
    }
}