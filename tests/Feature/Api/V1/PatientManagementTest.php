<?php

namespace Tests\Feature\Api\V1;

use App\Models\Patient;
use App\Models\PatientDemographic;
use App\Models\PatientAddress;
use App\Models\PatientIdentity;
use App\Models\Card;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_create_patient_with_demographics()
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

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/patients', $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'code',
                    'facility_id',
                    'demographics' => [
                        'first_name',
                        'last_name',
                        'date_of_birth',
                        'gender',
                        'phone'
                    ]
                ]
            ]);

        $this->assertDatabaseHas('patients', [
            'facility_id' => $facility->id
        ]);

        $this->assertDatabaseHas('patient_demographics', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'phone' => '123456789'
        ]);
    }

    public function test_can_create_patient_with_address()
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

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/patients', $data);

        $response->assertStatus(201);

        $patient = Patient::where('facility_id', $facility->id)->first();
        $this->assertDatabaseHas('patient_addresses', [
            'patient_id' => $patient->id,
            'address_line_1' => '123 Main St',
            'city' => 'Phnom Penh',
            'postal_code' => '12000'
        ]);
    }

    public function test_can_create_patient_with_insurance()
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

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/patients', $data);

        $response->assertStatus(201);

        $patient = Patient::where('facility_id', $facility->id)->first();
        $this->assertDatabaseHas('patient_identities', [
            'patient_id' => $patient->id,
            'card_id' => $card->id,
            'identity_code' => 'INS123456',
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31'
        ]);
    }

    public function test_can_list_patients()
    {
        $facility = Facility::factory()->create();
        $patients = Patient::factory()->count(3)->create(['facility_id' => $facility->id]);

        foreach ($patients as $patient) {
            PatientDemographic::factory()->create(['patient_id' => $patient->id]);
        }

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/patients');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'code',
                        'facility_id',
                        'demographics'
                    ]
                ],
                'meta' => [
                    'current_page',
                    'total'
                ]
            ]);
    }

    public function test_can_search_patients_by_name()
    {
        $facility = Facility::factory()->create();
        $patient1 = Patient::factory()->create(['facility_id' => $facility->id]);
        $patient2 = Patient::factory()->create(['facility_id' => $facility->id]);

        PatientDemographic::factory()->create([
            'patient_id' => $patient1->id,
            'first_name' => 'John',
            'last_name' => 'Doe'
        ]);
        PatientDemographic::factory()->create([
            'patient_id' => $patient2->id,
            'first_name' => 'Jane',
            'last_name' => 'Smith'
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/patients?search=John');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('John', $response->json('data.0.demographics.first_name'));
    }

    public function test_can_search_patients_by_code()
    {
        $facility = Facility::factory()->create();
        $patient = Patient::factory()->create([
            'facility_id' => $facility->id,
            'code' => 'PAT001'
        ]);
        PatientDemographic::factory()->create(['patient_id' => $patient->id]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/patients?code=PAT001');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('PAT001', $response->json('data.0.code'));
    }

    public function test_can_get_patient_by_id()
    {
        $facility = Facility::factory()->create();
        $patient = Patient::factory()->create(['facility_id' => $facility->id]);
        $demographic = PatientDemographic::factory()->create(['patient_id' => $patient->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/patients/{$patient->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'code',
                    'facility_id',
                    'demographics',
                    'addresses',
                    'identities',
                    'visits'
                ]
            ]);
    }

    public function test_can_get_patient_by_code()
    {
        $facility = Facility::factory()->create();
        $patient = Patient::factory()->create([
            'facility_id' => $facility->id,
            'code' => 'PAT123'
        ]);
        PatientDemographic::factory()->create(['patient_id' => $patient->id]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/patients/code/PAT123');

        $response->assertStatus(200);
        $this->assertEquals($patient->id, $response->json('data.id'));
        $this->assertEquals('PAT123', $response->json('data.code'));
    }

    public function test_can_update_patient()
    {
        $facility = Facility::factory()->create();
        $patient = Patient::factory()->create(['facility_id' => $facility->id]);
        $demographic = PatientDemographic::factory()->create(['patient_id' => $patient->id]);

        $data = [
            'demographics' => [
                'first_name' => 'Updated',
                'last_name' => 'Name',
                'phone' => '987654321'
            ]
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/patients/{$patient->id}", $data);

        $response->assertStatus(200);

        $this->assertDatabaseHas('patient_demographics', [
            'patient_id' => $patient->id,
            'first_name' => 'Updated',
            'last_name' => 'Name',
            'phone' => '987654321'
        ]);
    }

    public function test_can_get_patient_summary()
    {
        $facility = Facility::factory()->create();
        $patient = Patient::factory()->create(['facility_id' => $facility->id]);
        PatientDemographic::factory()->create(['patient_id' => $patient->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/patients/{$patient->id}/summary");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'patient',
                    'demographics',
                    'active_visits_count',
                    'total_visits_count',
                    'last_visit_date'
                ]
            ]);
    }

    public function test_can_get_patient_visits()
    {
        $facility = Facility::factory()->create();
        $patient = Patient::factory()->create(['facility_id' => $facility->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/patients/{$patient->id}/visits");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [],
                'meta'
            ]);
    }

    public function test_can_get_patient_insurance_status()
    {
        $facility = Facility::factory()->create();
        $patient = Patient::factory()->create(['facility_id' => $facility->id]);
        $card = Card::factory()->create();
        
        PatientIdentity::factory()->create([
            'patient_id' => $patient->id,
            'card_id' => $card->id,
            'start_date' => now()->subDays(30),
            'end_date' => now()->addDays(30)
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/patients/{$patient->id}/insurance-status");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'has_active_insurance',
                    'active_cards',
                    'expired_cards'
                ]
            ]);
    }

    public function test_can_add_patient_insurance()
    {
        $facility = Facility::factory()->create();
        $patient = Patient::factory()->create(['facility_id' => $facility->id]);
        $card = Card::factory()->create();

        $data = [
            'card_id' => $card->id,
            'identity_code' => 'NEW123456',
            'start_date' => '2024-06-01',
            'end_date' => '2025-05-31'
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/patients/{$patient->id}/insurance", $data);

        $response->assertStatus(201);

        $this->assertDatabaseHas('patient_identities', [
            'patient_id' => $patient->id,
            'card_id' => $card->id,
            'identity_code' => 'NEW123456'
        ]);
    }

    public function test_validates_required_fields_on_create()
    {
        $data = [
            'demographics' => [
                'first_name' => 'John'
                // Missing required fields
            ]
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/patients', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['facility_id', 'demographics.last_name']);
    }

    public function test_returns_404_for_nonexistent_patient()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/patients/999');

        $response->assertStatus(404);
    }

    public function test_requires_authentication()
    {
        $response = $this->getJson('/api/v1/patients');

        $response->assertStatus(401);
    }

    public function test_can_soft_delete_patient()
    {
        $facility = Facility::factory()->create();
        $patient = Patient::factory()->create(['facility_id' => $facility->id]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/patients/{$patient->id}");

        $response->assertStatus(204);

        $this->assertSoftDeleted('patients', ['id' => $patient->id]);
    }
}