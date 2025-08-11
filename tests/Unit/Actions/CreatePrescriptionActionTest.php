<?php

namespace Tests\Unit\Actions;

use App\Actions\CreatePrescriptionAction;
use App\Models\MedicationInstruction;
use App\Models\MedicationRequest;
use App\Models\Patient;
use App\Models\Term;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CreatePrescriptionActionTest extends TestCase
{
    use RefreshDatabase;

    private CreatePrescriptionAction $action;
    private Visit $visit;
    private User $requester;
    private Term $status;
    private Term $intent;
    private Term $medication;
    private Term $unit;
    private Term $method;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->action = new CreatePrescriptionAction();
        
        // Create test data
        $patient = Patient::factory()->create();
        $this->visit = Visit::factory()->create([
            'patient_id' => $patient->id,
            'discharged_at' => null, // Active visit
        ]);
        
        $this->requester = User::factory()->create();
        $this->status = Term::factory()->create(['name' => 'active']);
        $this->intent = Term::factory()->create(['name' => 'order']);
        $this->medication = Term::factory()->create(['name' => 'Paracetamol']);
        $this->unit = Term::factory()->create(['name' => 'tablet']);
        $this->method = Term::factory()->create(['name' => 'oral']);
    }

    public function test_creates_prescription_successfully()
    {
        $data = [
            'visit_id' => $this->visit->id,
            'status_id' => $this->status->id,
            'intent_id' => $this->intent->id,
            'medication_id' => $this->medication->id,
            'requester_id' => $this->requester->id,
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

        $result = $this->action->execute($data);

        $this->assertInstanceOf(MedicationRequest::class, $result);
        $this->assertEquals($this->visit->id, $result->visit_id);
        $this->assertEquals($this->medication->id, $result->medication_id);
        $this->assertEquals(30, $result->quantity);
        
        // Check instruction was created
        $this->assertNotNull($result->instruction);
        $this->assertEquals(1.0, $result->instruction->morning);
        $this->assertEquals(1.0, $result->instruction->afternoon);
        $this->assertEquals(1.0, $result->instruction->evening);
        $this->assertEquals(0.0, $result->instruction->night);
        $this->assertEquals(7, $result->instruction->days);
        $this->assertEquals('Take with food', $result->instruction->note);
    }

    public function test_fails_for_discharged_visit()
    {
        $this->visit->update(['discharged_at' => now()]);

        $data = [
            'visit_id' => $this->visit->id,
            'status_id' => $this->status->id,
            'intent_id' => $this->intent->id,
            'medication_id' => $this->medication->id,
            'requester_id' => $this->requester->id,
            'quantity' => 30,
            'unit_id' => $this->unit->id,
            'instruction' => [
                'method_id' => $this->method->id,
                'morning' => 1.0,
                'afternoon' => 0.0,
                'evening' => 0.0,
                'night' => 0.0,
                'days' => 7,
            ],
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Cannot create prescription for discharged visit.');

        $this->action->execute($data);
    }

    public function test_fails_when_no_doses_provided()
    {
        $data = [
            'visit_id' => $this->visit->id,
            'status_id' => $this->status->id,
            'intent_id' => $this->intent->id,
            'medication_id' => $this->medication->id,
            'requester_id' => $this->requester->id,
            'quantity' => 30,
            'unit_id' => $this->unit->id,
            'instruction' => [
                'method_id' => $this->method->id,
                'morning' => 0.0,
                'afternoon' => 0.0,
                'evening' => 0.0,
                'night' => 0.0,
                'days' => 7,
            ],
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('At least one dose (morning, afternoon, evening, or night) must be greater than 0.');

        $this->action->execute($data);
    }

    public function test_fails_when_days_is_zero()
    {
        $data = [
            'visit_id' => $this->visit->id,
            'status_id' => $this->status->id,
            'intent_id' => $this->intent->id,
            'medication_id' => $this->medication->id,
            'requester_id' => $this->requester->id,
            'quantity' => 30,
            'unit_id' => $this->unit->id,
            'instruction' => [
                'method_id' => $this->method->id,
                'morning' => 1.0,
                'afternoon' => 0.0,
                'evening' => 0.0,
                'night' => 0.0,
                'days' => 0,
            ],
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Days must be greater than 0.');

        $this->action->execute($data);
    }

    public function test_validates_data_correctly()
    {
        $validData = [
            'visit_id' => $this->visit->id,
            'status_id' => $this->status->id,
            'intent_id' => $this->intent->id,
            'medication_id' => $this->medication->id,
            'requester_id' => $this->requester->id,
            'quantity' => 30,
            'unit_id' => $this->unit->id,
            'instruction' => [
                'method_id' => $this->method->id,
                'morning' => 1.0,
                'afternoon' => 1.0,
                'evening' => 1.0,
                'night' => 0.0,
                'days' => 7,
                'note' => 'Take with food',
            ],
        ];

        $result = $this->action->validateData($validData);
        
        $this->assertEquals($validData, $result);
    }

    public function test_loads_relationships_correctly()
    {
        $data = [
            'visit_id' => $this->visit->id,
            'status_id' => $this->status->id,
            'intent_id' => $this->intent->id,
            'medication_id' => $this->medication->id,
            'requester_id' => $this->requester->id,
            'quantity' => 30,
            'unit_id' => $this->unit->id,
            'instruction' => [
                'method_id' => $this->method->id,
                'morning' => 1.0,
                'afternoon' => 0.0,
                'evening' => 0.0,
                'night' => 0.0,
                'days' => 7,
            ],
        ];

        $result = $this->action->execute($data);

        $this->assertTrue($result->relationLoaded('visit'));
        $this->assertTrue($result->relationLoaded('status'));
        $this->assertTrue($result->relationLoaded('intent'));
        $this->assertTrue($result->relationLoaded('medication'));
        $this->assertTrue($result->relationLoaded('requester'));
        $this->assertTrue($result->relationLoaded('instruction'));
        $this->assertTrue($result->visit->relationLoaded('patient'));
    }
}