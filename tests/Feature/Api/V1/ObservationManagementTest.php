<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Models\Patient;
use App\Models\Visit;
use App\Models\Encounter;
use App\Models\Observation;
use App\Models\Concept;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ObservationManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_get_observation()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $encounter = Encounter::factory()->create(['visit_id' => $visit->id]);
        $concept = Concept::factory()->create();
        
        $observation = Observation::factory()->create([
            'patient_id' => $patient->id,
            'encounter_id' => $encounter->id,
            'concept_id' => $concept->id,
            'value_number' => 37.5
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/observations/{$observation->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'patient_id',
                    'encounter_id',
                    'concept_id',
                    'value_number',
                    'observed_at',
                    'patient',
                    'encounter',
                    'concept'
                ]
            ]);
    }

    public function test_can_update_observation()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $encounter = Encounter::factory()->create(['visit_id' => $visit->id]);
        $concept = Concept::factory()->create();
        
        $observation = Observation::factory()->create([
            'patient_id' => $patient->id,
            'encounter_id' => $encounter->id,
            'concept_id' => $concept->id,
            'value_number' => 37.5
        ]);

        $data = [
            'value_number' => 38.0,
            'notes' => 'Updated temperature reading'
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/observations/{$observation->id}", $data);

        $response->assertStatus(200);

        $this->assertDatabaseHas('observations', [
            'id' => $observation->id,
            'value_number' => 38.0
        ]);
    }

    public function test_can_delete_observation()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $encounter = Encounter::factory()->create(['visit_id' => $visit->id]);
        $concept = Concept::factory()->create();
        
        $observation = Observation::factory()->create([
            'patient_id' => $patient->id,
            'encounter_id' => $encounter->id,
            'concept_id' => $concept->id
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/observations/{$observation->id}");

        $response->assertStatus(204);

        $this->assertSoftDeleted('observations', ['id' => $observation->id]);
    }

    public function test_can_get_encounter_observations()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $encounter = Encounter::factory()->create(['visit_id' => $visit->id]);
        $concept = Concept::factory()->create();

        // Create observations for this encounter
        Observation::factory()->count(3)->create([
            'patient_id' => $patient->id,
            'encounter_id' => $encounter->id,
            'concept_id' => $concept->id
        ]);

        // Create observation for different encounter
        $otherEncounter = Encounter::factory()->create(['visit_id' => $visit->id]);
        Observation::factory()->create([
            'patient_id' => $patient->id,
            'encounter_id' => $otherEncounter->id,
            'concept_id' => $concept->id
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/encounters/{$encounter->id}/observations");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'patient_id',
                        'encounter_id',
                        'concept_id',
                        'observed_at'
                    ]
                ]
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_observations_include_concept_details()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $encounter = Encounter::factory()->create(['visit_id' => $visit->id]);
        $concept = Concept::factory()->create([
            'name' => 'Body Temperature',
            'unit' => '°C'
        ]);
        
        $observation = Observation::factory()->create([
            'patient_id' => $patient->id,
            'encounter_id' => $encounter->id,
            'concept_id' => $concept->id,
            'value_number' => 37.5
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/observations/{$observation->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'concept' => [
                        'name' => 'Body Temperature',
                        'unit' => '°C'
                    ]
                ]
            ]);
    }

    public function test_observations_include_patient_details()
    {
        $patient = Patient::factory()->create(['code' => 'PAT001']);
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $encounter = Encounter::factory()->create(['visit_id' => $visit->id]);
        $concept = Concept::factory()->create();
        
        $observation = Observation::factory()->create([
            'patient_id' => $patient->id,
            'encounter_id' => $encounter->id,
            'concept_id' => $concept->id
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/observations/{$observation->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'patient' => [
                        'code' => 'PAT001'
                    ]
                ]
            ]);
    }

    public function test_observations_include_encounter_details()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $encounter = Encounter::factory()->create([
            'visit_id' => $visit->id,
            'encounter_type_id' => 1
        ]);
        $concept = Concept::factory()->create();
        
        $observation = Observation::factory()->create([
            'patient_id' => $patient->id,
            'encounter_id' => $encounter->id,
            'concept_id' => $concept->id
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/observations/{$observation->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'encounter' => [
                        'encounter_type_id' => 1
                    ]
                ]
            ]);
    }

    public function test_validates_observation_update_data()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $encounter = Encounter::factory()->create(['visit_id' => $visit->id]);
        $concept = Concept::factory()->create();
        
        $observation = Observation::factory()->create([
            'patient_id' => $patient->id,
            'encounter_id' => $encounter->id,
            'concept_id' => $concept->id
        ]);

        $data = [
            'value_number' => 'invalid_number',
            'value_datetime' => 'invalid_date'
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/observations/{$observation->id}", $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['value_number', 'value_datetime']);
    }

    public function test_returns_404_for_nonexistent_observation()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/observations/999');

        $response->assertStatus(404);
    }

    public function test_returns_404_for_nonexistent_encounter_observations()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/encounters/999/observations');

        $response->assertStatus(404);
    }

    public function test_orders_encounter_observations_by_observed_at()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $encounter = Encounter::factory()->create(['visit_id' => $visit->id]);
        $concept = Concept::factory()->create();

        $observation1 = Observation::factory()->create([
            'patient_id' => $patient->id,
            'encounter_id' => $encounter->id,
            'concept_id' => $concept->id,
            'observed_at' => now()->subHours(2)
        ]);

        $observation2 = Observation::factory()->create([
            'patient_id' => $patient->id,
            'encounter_id' => $encounter->id,
            'concept_id' => $concept->id,
            'observed_at' => now()->subHour()
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/encounters/{$encounter->id}/observations");

        $response->assertStatus(200);
        
        $observations = $response->json('data');
        $this->assertEquals($observation2->id, $observations[0]['id']); // Most recent first
        $this->assertEquals($observation1->id, $observations[1]['id']);
    }

    public function test_can_filter_observations_by_concept()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $encounter = Encounter::factory()->create(['visit_id' => $visit->id]);
        
        $tempConcept = Concept::factory()->create(['name' => 'Temperature']);
        $bpConcept = Concept::factory()->create(['name' => 'Blood Pressure']);

        Observation::factory()->create([
            'patient_id' => $patient->id,
            'encounter_id' => $encounter->id,
            'concept_id' => $tempConcept->id
        ]);

        Observation::factory()->create([
            'patient_id' => $patient->id,
            'encounter_id' => $encounter->id,
            'concept_id' => $bpConcept->id
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/encounters/{$encounter->id}/observations?concept_id={$tempConcept->id}");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($tempConcept->id, $response->json('data.0.concept_id'));
    }

    public function test_requires_authentication()
    {
        $observation = Observation::factory()->create();

        $response = $this->getJson("/api/v1/observations/{$observation->id}");

        $response->assertStatus(401);
    }
}