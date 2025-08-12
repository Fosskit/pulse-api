<?php

namespace Tests\Unit\Actions;

use App\Actions\CreatePatientAction;
use App\Models\Patient;
use App\Models\PatientDemographic;
use App\Models\PatientAddress;
use App\Models\PatientIdentity;
use App\Models\Card;
use App\Models\Facility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreatePatientActionTest extends TestCase
{
    use RefreshDatabase;

    private CreatePatientAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new CreatePatientAction();
    }

    public function test_creates_patient_with_demographics()
    {
        $facility = Facility::factory()->create();
        
        $data = [
            'facility_id' => $facility->id,
            'demographics' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'date_of_birth' => '1990-01-01',
                'gender' => 'male',
                'phone' => '123456789'
            ]
        ];

        $patient = $this->action->execute($data);

        $this->assertInstanceOf(Patient::class, $patient);
        $this->assertEquals($facility->id, $patient->facility_id);
        $this->assertNotNull($patient->code);
        
        $this->assertDatabaseHas('patient_demographics', [
            'patient_id' => $patient->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'phone' => '123456789'
        ]);
    }

    public function test_creates_patient_with_address()
    {
        $facility = Facility::factory()->create();
        
        $data = [
            'facility_id' => $facility->id,
            'demographics' => [
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'date_of_birth' => '1985-05-15',
                'gender' => 'female'
            ],
            'address' => [
                'address_line_1' => '123 Main St',
                'city' => 'Phnom Penh',
                'postal_code' => '12000'
            ]
        ];

        $patient = $this->action->execute($data);

        $this->assertDatabaseHas('patient_addresses', [
            'patient_id' => $patient->id,
            'address_line_1' => '123 Main St',
            'city' => 'Phnom Penh',
            'postal_code' => '12000'
        ]);
    }

    public function test_creates_patient_with_insurance_identity()
    {
        $facility = Facility::factory()->create();
        $card = Card::factory()->create();
        
        $data = [
            'facility_id' => $facility->id,
            'demographics' => [
                'first_name' => 'Bob',
                'last_name' => 'Johnson',
                'date_of_birth' => '1975-12-25',
                'gender' => 'male'
            ],
            'insurance' => [
                'card_id' => $card->id,
                'identity_code' => 'INS123456',
                'start_date' => '2024-01-01',
                'end_date' => '2024-12-31'
            ]
        ];

        $patient = $this->action->execute($data);

        $this->assertDatabaseHas('patient_identities', [
            'patient_id' => $patient->id,
            'card_id' => $card->id,
            'identity_code' => 'INS123456',
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31'
        ]);
    }

    public function test_generates_unique_patient_code()
    {
        $facility = Facility::factory()->create();
        
        $data = [
            'facility_id' => $facility->id,
            'demographics' => [
                'first_name' => 'Test',
                'last_name' => 'Patient',
                'date_of_birth' => '2000-01-01',
                'gender' => 'other'
            ]
        ];

        $patient1 = $this->action->execute($data);
        $patient2 = $this->action->execute($data);

        $this->assertNotEquals($patient1->code, $patient2->code);
        $this->assertNotNull($patient1->code);
        $this->assertNotNull($patient2->code);
    }

    public function test_handles_missing_optional_data()
    {
        $facility = Facility::factory()->create();
        
        $data = [
            'facility_id' => $facility->id,
            'demographics' => [
                'first_name' => 'Minimal',
                'last_name' => 'Patient',
                'date_of_birth' => '1995-06-15',
                'gender' => 'female'
            ]
        ];

        $patient = $this->action->execute($data);

        $this->assertInstanceOf(Patient::class, $patient);
        $this->assertEquals(0, $patient->addresses()->count());
        $this->assertEquals(0, $patient->identities()->count());
    }
}