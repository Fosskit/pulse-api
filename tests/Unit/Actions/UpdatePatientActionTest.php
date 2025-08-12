<?php

namespace Tests\Unit\Actions;

use App\Actions\UpdatePatientAction;
use App\Models\Patient;
use App\Models\PatientDemographic;
use App\Models\PatientAddress;
use App\Models\PatientIdentity;
use App\Models\Card;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdatePatientActionTest extends TestCase
{
    use RefreshDatabase;

    private UpdatePatientAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new UpdatePatientAction();
    }

    public function test_updates_patient_demographics()
    {
        $patient = Patient::factory()->create();
        $demographic = PatientDemographic::factory()->create(['patient_id' => $patient->id]);

        $data = [
            'demographics' => [
                'first_name' => 'Updated',
                'last_name' => 'Name',
                'phone' => '987654321'
            ]
        ];

        $updatedPatient = $this->action->execute($patient, $data);

        $this->assertEquals($patient->id, $updatedPatient->id);
        $this->assertDatabaseHas('patient_demographics', [
            'patient_id' => $patient->id,
            'first_name' => 'Updated',
            'last_name' => 'Name',
            'phone' => '987654321'
        ]);
    }

    public function test_updates_patient_address()
    {
        $patient = Patient::factory()->create();
        $address = PatientAddress::factory()->create(['patient_id' => $patient->id]);

        $data = [
            'address' => [
                'address_line_1' => 'Updated Address',
                'city' => 'Updated City',
                'postal_code' => '54321'
            ]
        ];

        $updatedPatient = $this->action->execute($patient, $data);

        $this->assertDatabaseHas('patient_addresses', [
            'patient_id' => $patient->id,
            'address_line_1' => 'Updated Address',
            'city' => 'Updated City',
            'postal_code' => '54321'
        ]);
    }

    public function test_adds_new_insurance_identity()
    {
        $patient = Patient::factory()->create();
        $card = Card::factory()->create();

        $data = [
            'insurance' => [
                'card_id' => $card->id,
                'identity_code' => 'NEW123456',
                'start_date' => '2024-06-01',
                'end_date' => '2025-05-31'
            ]
        ];

        $updatedPatient = $this->action->execute($patient, $data);

        $this->assertDatabaseHas('patient_identities', [
            'patient_id' => $patient->id,
            'card_id' => $card->id,
            'identity_code' => 'NEW123456',
            'start_date' => '2024-06-01',
            'end_date' => '2025-05-31'
        ]);
    }

    public function test_creates_demographics_if_not_exists()
    {
        $patient = Patient::factory()->create();

        $data = [
            'demographics' => [
                'first_name' => 'New',
                'last_name' => 'Demographics',
                'date_of_birth' => '1988-03-20',
                'gender' => 'male'
            ]
        ];

        $updatedPatient = $this->action->execute($patient, $data);

        $this->assertDatabaseHas('patient_demographics', [
            'patient_id' => $patient->id,
            'first_name' => 'New',
            'last_name' => 'Demographics',
            'date_of_birth' => '1988-03-20',
            'gender' => 'male'
        ]);
    }

    public function test_creates_address_if_not_exists()
    {
        $patient = Patient::factory()->create();

        $data = [
            'address' => [
                'address_line_1' => 'New Address',
                'city' => 'New City',
                'postal_code' => '11111'
            ]
        ];

        $updatedPatient = $this->action->execute($patient, $data);

        $this->assertDatabaseHas('patient_addresses', [
            'patient_id' => $patient->id,
            'address_line_1' => 'New Address',
            'city' => 'New City',
            'postal_code' => '11111'
        ]);
    }

    public function test_handles_partial_updates()
    {
        $patient = Patient::factory()->create();
        $demographic = PatientDemographic::factory()->create([
            'patient_id' => $patient->id,
            'first_name' => 'Original',
            'last_name' => 'Name',
            'phone' => '123456789'
        ]);

        $data = [
            'demographics' => [
                'phone' => '987654321'
            ]
        ];

        $updatedPatient = $this->action->execute($patient, $data);

        $this->assertDatabaseHas('patient_demographics', [
            'patient_id' => $patient->id,
            'first_name' => 'Original', // Should remain unchanged
            'last_name' => 'Name', // Should remain unchanged
            'phone' => '987654321' // Should be updated
        ]);
    }

    public function test_handles_empty_update_data()
    {
        $patient = Patient::factory()->create();
        $demographic = PatientDemographic::factory()->create(['patient_id' => $patient->id]);

        $data = [];

        $updatedPatient = $this->action->execute($patient, $data);

        $this->assertEquals($patient->id, $updatedPatient->id);
        // Should not create any new records or modify existing ones
    }
}