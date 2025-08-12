<?php

namespace Tests\Unit\Actions;

use App\Actions\GetPatientDetailsAction;
use App\Models\Patient;
use App\Models\PatientDemographic;
use App\Models\PatientAddress;
use App\Models\PatientIdentity;
use App\Models\Visit;
use App\Models\Card;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetPatientDetailsActionTest extends TestCase
{
    use RefreshDatabase;

    private GetPatientDetailsAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new GetPatientDetailsAction();
    }

    public function test_returns_patient_with_demographics()
    {
        $patient = Patient::factory()->create();
        $demographic = PatientDemographic::factory()->create([
            'patient_id' => $patient->id,
            'first_name' => 'John',
            'last_name' => 'Doe'
        ]);

        $result = $this->action->execute($patient->id);

        $this->assertEquals($patient->id, $result->id);
        $this->assertEquals('John', $result->demographics->first_name);
        $this->assertEquals('Doe', $result->demographics->last_name);
    }

    public function test_returns_patient_with_addresses()
    {
        $patient = Patient::factory()->create();
        $address = PatientAddress::factory()->create([
            'patient_id' => $patient->id,
            'address_line_1' => '123 Main St'
        ]);

        $result = $this->action->execute($patient->id);

        $this->assertEquals($patient->id, $result->id);
        $this->assertCount(1, $result->addresses);
        $this->assertEquals('123 Main St', $result->addresses->first()->address_line_1);
    }

    public function test_returns_patient_with_identities()
    {
        $patient = Patient::factory()->create();
        $card = Card::factory()->create();
        $identity = PatientIdentity::factory()->create([
            'patient_id' => $patient->id,
            'card_id' => $card->id,
            'identity_code' => 'INS123456'
        ]);

        $result = $this->action->execute($patient->id);

        $this->assertEquals($patient->id, $result->id);
        $this->assertCount(1, $result->identities);
        $this->assertEquals('INS123456', $result->identities->first()->identity_code);
    }

    public function test_returns_patient_with_visits()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);

        $result = $this->action->execute($patient->id);

        $this->assertEquals($patient->id, $result->id);
        $this->assertCount(1, $result->visits);
        $this->assertEquals($visit->id, $result->visits->first()->id);
    }

    public function test_returns_patient_with_all_relationships()
    {
        $patient = Patient::factory()->create();
        $demographic = PatientDemographic::factory()->create(['patient_id' => $patient->id]);
        $address = PatientAddress::factory()->create(['patient_id' => $patient->id]);
        $card = Card::factory()->create();
        $identity = PatientIdentity::factory()->create([
            'patient_id' => $patient->id,
            'card_id' => $card->id
        ]);
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);

        $result = $this->action->execute($patient->id);

        $this->assertEquals($patient->id, $result->id);
        $this->assertNotNull($result->demographics);
        $this->assertCount(1, $result->addresses);
        $this->assertCount(1, $result->identities);
        $this->assertCount(1, $result->visits);
    }

    public function test_returns_null_for_nonexistent_patient()
    {
        $result = $this->action->execute(999);

        $this->assertNull($result);
    }

    public function test_returns_patient_without_optional_relationships()
    {
        $patient = Patient::factory()->create();

        $result = $this->action->execute($patient->id);

        $this->assertEquals($patient->id, $result->id);
        $this->assertNull($result->demographics);
        $this->assertCount(0, $result->addresses);
        $this->assertCount(0, $result->identities);
        $this->assertCount(0, $result->visits);
    }

    public function test_includes_active_insurance_information()
    {
        $patient = Patient::factory()->create();
        $card = Card::factory()->create();
        $activeIdentity = PatientIdentity::factory()->create([
            'patient_id' => $patient->id,
            'card_id' => $card->id,
            'start_date' => now()->subDays(30),
            'end_date' => now()->addDays(30)
        ]);
        $expiredIdentity = PatientIdentity::factory()->create([
            'patient_id' => $patient->id,
            'card_id' => $card->id,
            'start_date' => now()->subDays(60),
            'end_date' => now()->subDays(30)
        ]);

        $result = $this->action->execute($patient->id);

        $this->assertEquals($patient->id, $result->id);
        $this->assertCount(2, $result->identities);
        
        // Should include method to determine active insurance
        $activeInsurance = $result->activeInsurance();
        $this->assertNotNull($activeInsurance);
        $this->assertEquals($activeIdentity->id, $activeInsurance->id);
    }
}