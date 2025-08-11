<?php

namespace Tests\Unit\Actions;

use App\Actions\DispenseMedicationAction;
use App\Models\MedicationDispense;
use App\Models\MedicationRequest;
use App\Models\Patient;
use App\Models\Term;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DispenseMedicationActionTest extends TestCase
{
    use RefreshDatabase;

    private DispenseMedicationAction $action;
    private MedicationRequest $medicationRequest;
    private User $dispenser;
    private Term $status;
    private Term $unit;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->action = new DispenseMedicationAction();
        
        // Create test data
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create([
            'patient_id' => $patient->id,
            'discharged_at' => null, // Active visit
        ]);
        
        $this->medicationRequest = MedicationRequest::factory()->create([
            'visit_id' => $visit->id,
            'quantity' => 30,
        ]);
        
        $this->dispenser = User::factory()->create();
        $this->status = Term::factory()->create(['name' => 'dispensed']);
        $this->unit = Term::factory()->create(['name' => 'tablet']);
    }

    public function test_dispenses_medication_successfully()
    {
        $data = [
            'medication_request_id' => $this->medicationRequest->id,
            'status_id' => $this->status->id,
            'dispenser_id' => $this->dispenser->id,
            'quantity' => 15,
            'unit_id' => $this->unit->id,
        ];

        $result = $this->action->execute($data);

        $this->assertInstanceOf(MedicationDispense::class, $result);
        $this->assertEquals($this->medicationRequest->id, $result->medication_request_id);
        $this->assertEquals(15, $result->quantity);
        $this->assertEquals($this->dispenser->id, $result->dispenser_id);
        
        $this->assertDatabaseHas('medication_dispenses', [
            'medication_request_id' => $this->medicationRequest->id,
            'quantity' => 15,
            'dispenser_id' => $this->dispenser->id,
        ]);
    }

    public function test_fails_for_discharged_visit()
    {
        $this->medicationRequest->visit->update(['discharged_at' => now()]);

        $data = [
            'medication_request_id' => $this->medicationRequest->id,
            'status_id' => $this->status->id,
            'dispenser_id' => $this->dispenser->id,
            'quantity' => 15,
            'unit_id' => $this->unit->id,
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Cannot dispense medication for discharged visit.');

        $this->action->execute($data);
    }

    public function test_fails_when_quantity_exceeds_remaining()
    {
        // Create a dispense that uses up most of the quantity
        MedicationDispense::factory()->create([
            'medication_request_id' => $this->medicationRequest->id,
            'quantity' => 25,
        ]);

        $data = [
            'medication_request_id' => $this->medicationRequest->id,
            'status_id' => $this->status->id,
            'dispenser_id' => $this->dispenser->id,
            'quantity' => 10, // Only 5 remaining, but trying to dispense 10
            'unit_id' => $this->unit->id,
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Cannot dispense 10 units. Only 5 units remaining.');

        $this->action->execute($data);
    }

    public function test_validates_data_correctly()
    {
        $validData = [
            'medication_request_id' => $this->medicationRequest->id,
            'status_id' => $this->status->id,
            'dispenser_id' => $this->dispenser->id,
            'quantity' => 15,
            'unit_id' => $this->unit->id,
        ];

        $result = $this->action->validateData($validData);
        
        $this->assertEquals($validData, $result);
    }

    public function test_loads_relationships_correctly()
    {
        $data = [
            'medication_request_id' => $this->medicationRequest->id,
            'status_id' => $this->status->id,
            'dispenser_id' => $this->dispenser->id,
            'quantity' => 15,
            'unit_id' => $this->unit->id,
        ];

        $result = $this->action->execute($data);

        $this->assertTrue($result->relationLoaded('visit'));
        $this->assertTrue($result->relationLoaded('status'));
        $this->assertTrue($result->relationLoaded('medicationRequest'));
        $this->assertTrue($result->relationLoaded('dispenser'));
        $this->assertTrue($result->relationLoaded('unit'));
        $this->assertTrue($result->visit->relationLoaded('patient'));
    }
}