<?php

namespace Tests\Unit\Actions;

use App\Actions\DischargePatientAction;
use App\Models\Visit;
use App\Models\Encounter;
use App\Models\Patient;
use App\Models\Facility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DischargePatientActionTest extends TestCase
{
    use RefreshDatabase;

    private DischargePatientAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new DischargePatientAction();
    }

    public function test_updates_visit_with_discharge_details()
    {
        $patient = Patient::factory()->create();
        $facility = Facility::factory()->create();
        $visit = Visit::factory()->create([
            'patient_id' => $patient->id,
            'facility_id' => $facility->id,
            'admitted_at' => now()->subDays(3),
            'discharged_at' => null
        ]);

        $data = [
            'discharged_at' => now(),
            'discharge_type_id' => 1,
            'visit_outcome_id' => 1,
            'discharge_summary' => 'Patient recovered well'
        ];

        $updatedVisit = $this->action->execute($visit, $data);

        $this->assertNotNull($updatedVisit->discharged_at);
        $this->assertEquals(1, $updatedVisit->discharge_type_id);
        $this->assertEquals(1, $updatedVisit->visit_outcome_id);
    }

    public function test_creates_discharge_encounter()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create([
            'patient_id' => $patient->id,
            'admitted_at' => now()->subDays(2)
        ]);

        $data = [
            'discharged_at' => now(),
            'discharge_type_id' => 1,
            'visit_outcome_id' => 1
        ];

        $updatedVisit = $this->action->execute($visit, $data);

        // Should create a discharge encounter
        $dischargeEncounter = $visit->encounters()
            ->where('encounter_type_id', 5) // Assuming 5 is discharge type
            ->first();

        $this->assertNotNull($dischargeEncounter);
        $this->assertNotNull($dischargeEncounter->ended_at);
    }

    public function test_ends_active_encounters()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create([
            'patient_id' => $patient->id,
            'admitted_at' => now()->subDays(2)
        ]);

        $activeEncounter = Encounter::factory()->create([
            'visit_id' => $visit->id,
            'started_at' => now()->subDays(1),
            'ended_at' => null
        ]);

        $data = [
            'discharged_at' => now(),
            'discharge_type_id' => 1,
            'visit_outcome_id' => 1
        ];

        $updatedVisit = $this->action->execute($visit, $data);

        $activeEncounter->refresh();
        $this->assertNotNull($activeEncounter->ended_at);
    }

    public function test_handles_normal_discharge()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create([
            'patient_id' => $patient->id,
            'admitted_at' => now()->subDays(5)
        ]);

        $data = [
            'discharged_at' => now(),
            'discharge_type_id' => 1, // Normal discharge
            'visit_outcome_id' => 1, // Recovered
            'discharge_summary' => 'Patient fully recovered and ready for discharge'
        ];

        $updatedVisit = $this->action->execute($visit, $data);

        $this->assertEquals(1, $updatedVisit->discharge_type_id);
        $this->assertEquals(1, $updatedVisit->visit_outcome_id);
    }

    public function test_handles_transfer_discharge()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create([
            'patient_id' => $patient->id,
            'admitted_at' => now()->subDays(3)
        ]);

        $data = [
            'discharged_at' => now(),
            'discharge_type_id' => 2, // Transfer
            'visit_outcome_id' => 3, // Transferred
            'transfer_facility' => 'Specialized Hospital',
            'transfer_reason' => 'Requires specialized care'
        ];

        $updatedVisit = $this->action->execute($visit, $data);

        $this->assertEquals(2, $updatedVisit->discharge_type_id);
        $this->assertEquals(3, $updatedVisit->visit_outcome_id);
    }

    public function test_handles_death_discharge()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create([
            'patient_id' => $patient->id,
            'admitted_at' => now()->subDays(7)
        ]);

        $data = [
            'discharged_at' => now(),
            'discharge_type_id' => 4, // Death
            'visit_outcome_id' => 4, // Deceased
            'cause_of_death' => 'Cardiac arrest'
        ];

        $updatedVisit = $this->action->execute($visit, $data);

        $this->assertEquals(4, $updatedVisit->discharge_type_id);
        $this->assertEquals(4, $updatedVisit->visit_outcome_id);
    }

    public function test_validates_discharge_date_after_admission()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create([
            'patient_id' => $patient->id,
            'admitted_at' => now()
        ]);

        $this->expectException(\InvalidArgumentException::class);

        $data = [
            'discharged_at' => now()->subDay(), // Before admission
            'discharge_type_id' => 1,
            'visit_outcome_id' => 1
        ];

        $this->action->execute($visit, $data);
    }

    public function test_prevents_double_discharge()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create([
            'patient_id' => $patient->id,
            'admitted_at' => now()->subDays(3),
            'discharged_at' => now()->subDay() // Already discharged
        ]);

        $this->expectException(\InvalidArgumentException::class);

        $data = [
            'discharged_at' => now(),
            'discharge_type_id' => 1,
            'visit_outcome_id' => 1
        ];

        $this->action->execute($visit, $data);
    }

    public function test_calculates_length_of_stay()
    {
        $patient = Patient::factory()->create();
        $admissionDate = now()->subDays(5);
        $dischargeDate = now();
        
        $visit = Visit::factory()->create([
            'patient_id' => $patient->id,
            'admitted_at' => $admissionDate
        ]);

        $data = [
            'discharged_at' => $dischargeDate,
            'discharge_type_id' => 1,
            'visit_outcome_id' => 1
        ];

        $updatedVisit = $this->action->execute($visit, $data);

        $lengthOfStay = $admissionDate->diffInDays($dischargeDate);
        $this->assertEquals($lengthOfStay, $updatedVisit->length_of_stay);
    }
}