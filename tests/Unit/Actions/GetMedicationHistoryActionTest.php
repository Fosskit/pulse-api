<?php

namespace Tests\Unit\Actions;

use App\Actions\GetMedicationHistoryAction;
use App\Models\Patient;
use App\Models\Visit;
use App\Models\MedicationRequest;
use App\Models\MedicationDispense;
use App\Models\MedicationAdministration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetMedicationHistoryActionTest extends TestCase
{
    use RefreshDatabase;

    private GetMedicationHistoryAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new GetMedicationHistoryAction();
    }

    public function test_returns_medication_history_for_patient()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);

        $medicationRequest = MedicationRequest::factory()->create([
            'visit_id' => $visit->id,
            'medication_name' => 'Paracetamol'
        ]);

        $medicationDispense = MedicationDispense::factory()->create([
            'medication_request_id' => $medicationRequest->id,
            'quantity_dispensed' => 30
        ]);

        $medicationAdmin = MedicationAdministration::factory()->create([
            'medication_request_id' => $medicationRequest->id,
            'administered_at' => now()
        ]);

        $history = $this->action->execute($patient->id);

        $this->assertCount(1, $history['requests']);
        $this->assertCount(1, $history['dispenses']);
        $this->assertCount(1, $history['administrations']);
        
        $this->assertEquals('Paracetamol', $history['requests']->first()->medication_name);
        $this->assertEquals(30, $history['dispenses']->first()->quantity_dispensed);
    }

    public function test_returns_empty_history_for_patient_without_medications()
    {
        $patient = Patient::factory()->create();

        $history = $this->action->execute($patient->id);

        $this->assertCount(0, $history['requests']);
        $this->assertCount(0, $history['dispenses']);
        $this->assertCount(0, $history['administrations']);
    }

    public function test_filters_by_visit_id()
    {
        $patient = Patient::factory()->create();
        $visit1 = Visit::factory()->create(['patient_id' => $patient->id]);
        $visit2 = Visit::factory()->create(['patient_id' => $patient->id]);

        MedicationRequest::factory()->create([
            'visit_id' => $visit1->id,
            'medication_name' => 'Medication A'
        ]);
        MedicationRequest::factory()->create([
            'visit_id' => $visit2->id,
            'medication_name' => 'Medication B'
        ]);

        $history = $this->action->execute($patient->id, ['visit_id' => $visit1->id]);

        $this->assertCount(1, $history['requests']);
        $this->assertEquals('Medication A', $history['requests']->first()->medication_name);
    }

    public function test_filters_by_medication_name()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);

        MedicationRequest::factory()->create([
            'visit_id' => $visit->id,
            'medication_name' => 'Paracetamol'
        ]);
        MedicationRequest::factory()->create([
            'visit_id' => $visit->id,
            'medication_name' => 'Ibuprofen'
        ]);

        $history = $this->action->execute($patient->id, ['medication_name' => 'Paracetamol']);

        $this->assertCount(1, $history['requests']);
        $this->assertEquals('Paracetamol', $history['requests']->first()->medication_name);
    }

    public function test_filters_by_date_range()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);

        MedicationRequest::factory()->create([
            'visit_id' => $visit->id,
            'created_at' => now()->subDays(10)
        ]);
        MedicationRequest::factory()->create([
            'visit_id' => $visit->id,
            'created_at' => now()->subDays(5)
        ]);

        $history = $this->action->execute($patient->id, [
            'from_date' => now()->subDays(7)->format('Y-m-d'),
            'to_date' => now()->format('Y-m-d')
        ]);

        $this->assertCount(1, $history['requests']);
    }

    public function test_orders_by_most_recent_first()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);

        $oldRequest = MedicationRequest::factory()->create([
            'visit_id' => $visit->id,
            'medication_name' => 'Old Medication',
            'created_at' => now()->subDays(10)
        ]);
        $newRequest = MedicationRequest::factory()->create([
            'visit_id' => $visit->id,
            'medication_name' => 'New Medication',
            'created_at' => now()->subDays(1)
        ]);

        $history = $this->action->execute($patient->id);

        $this->assertEquals('New Medication', $history['requests']->first()->medication_name);
        $this->assertEquals('Old Medication', $history['requests']->last()->medication_name);
    }

    public function test_includes_related_data()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);

        $medicationRequest = MedicationRequest::factory()->create([
            'visit_id' => $visit->id
        ]);

        $history = $this->action->execute($patient->id);

        $request = $history['requests']->first();
        $this->assertNotNull($request->visit);
        $this->assertNotNull($request->medicationInstructions);
    }

    public function test_handles_nonexistent_patient()
    {
        $history = $this->action->execute(999);

        $this->assertCount(0, $history['requests']);
        $this->assertCount(0, $history['dispenses']);
        $this->assertCount(0, $history['administrations']);
    }

    public function test_validates_patient_id_parameter()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->action->execute(null);
    }

    public function test_includes_active_medications_only()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);

        MedicationRequest::factory()->create([
            'visit_id' => $visit->id,
            'status_id' => 1, // Active
            'medication_name' => 'Active Medication'
        ]);
        MedicationRequest::factory()->create([
            'visit_id' => $visit->id,
            'status_id' => 3, // Cancelled
            'medication_name' => 'Cancelled Medication'
        ]);

        $history = $this->action->execute($patient->id, ['active_only' => true]);

        $this->assertCount(1, $history['requests']);
        $this->assertEquals('Active Medication', $history['requests']->first()->medication_name);
    }

    public function test_groups_by_medication()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);

        MedicationRequest::factory()->count(2)->create([
            'visit_id' => $visit->id,
            'medication_name' => 'Paracetamol'
        ]);
        MedicationRequest::factory()->create([
            'visit_id' => $visit->id,
            'medication_name' => 'Ibuprofen'
        ]);

        $history = $this->action->execute($patient->id, ['group_by_medication' => true]);

        $this->assertArrayHasKey('grouped', $history);
        $this->assertCount(2, $history['grouped']); // Two different medications
        $this->assertCount(2, $history['grouped']['Paracetamol']); // Two requests for Paracetamol
        $this->assertCount(1, $history['grouped']['Ibuprofen']); // One request for Ibuprofen
    }
}