<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Models\Patient;
use App\Models\Visit;
use App\Models\Encounter;
use App\Models\Facility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_get_dashboard_stats()
    {
        $facility = Facility::factory()->create();
        
        // Create some test data
        $patients = Patient::factory()->count(5)->create(['facility_id' => $facility->id]);
        
        foreach ($patients->take(3) as $patient) {
            $visit = Visit::factory()->create([
                'patient_id' => $patient->id,
                'admitted_at' => now()->subDays(2),
                'discharged_at' => null // Active visit
            ]);
        }

        foreach ($patients->skip(3) as $patient) {
            Visit::factory()->create([
                'patient_id' => $patient->id,
                'admitted_at' => now()->subDays(5),
                'discharged_at' => now()->subDays(1) // Completed visit
            ]);
        }

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/dashboard/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'total_patients',
                    'active_visits',
                    'completed_visits_today',
                    'pending_discharges',
                    'average_length_of_stay',
                    'bed_occupancy_rate'
                ]
            ]);

        $this->assertEquals(5, $response->json('data.total_patients'));
        $this->assertEquals(3, $response->json('data.active_visits'));
    }

    public function test_can_get_active_patients()
    {
        $facility = Facility::factory()->create();
        $patients = Patient::factory()->count(3)->create(['facility_id' => $facility->id]);

        foreach ($patients as $patient) {
            Visit::factory()->create([
                'patient_id' => $patient->id,
                'admitted_at' => now()->subDays(1),
                'discharged_at' => null // Active visit
            ]);
        }

        // Create a discharged patient (should not appear)
        $dischargedPatient = Patient::factory()->create(['facility_id' => $facility->id]);
        Visit::factory()->create([
            'patient_id' => $dischargedPatient->id,
            'admitted_at' => now()->subDays(3),
            'discharged_at' => now()->subDay()
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/dashboard/active-patients');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'code',
                        'demographics',
                        'current_visit' => [
                            'id',
                            'admitted_at',
                            'visit_type_id'
                        ],
                        'current_encounter',
                        'length_of_stay'
                    ]
                ]
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_can_get_recent_activity()
    {
        $facility = Facility::factory()->create();
        $patient = Patient::factory()->create(['facility_id' => $facility->id]);
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);

        // Create recent encounters
        Encounter::factory()->count(5)->create([
            'visit_id' => $visit->id,
            'started_at' => now()->subHours(rand(1, 24))
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/dashboard/recent-activity');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'type',
                        'description',
                        'timestamp',
                        'patient',
                        'user'
                    ]
                ]
            ]);
    }

    public function test_dashboard_stats_handles_empty_data()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/dashboard/stats');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'total_patients' => 0,
                    'active_visits' => 0,
                    'completed_visits_today' => 0,
                    'pending_discharges' => 0
                ]
            ]);
    }

    public function test_active_patients_returns_empty_when_no_active_visits()
    {
        $facility = Facility::factory()->create();
        $patient = Patient::factory()->create(['facility_id' => $facility->id]);
        
        // Create only discharged visits
        Visit::factory()->create([
            'patient_id' => $patient->id,
            'admitted_at' => now()->subDays(3),
            'discharged_at' => now()->subDay()
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/dashboard/active-patients');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }

    public function test_recent_activity_returns_empty_when_no_activity()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/dashboard/recent-activity');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }

    public function test_dashboard_stats_calculates_bed_occupancy()
    {
        $facility = Facility::factory()->create();
        
        // Create patients with active visits
        $patients = Patient::factory()->count(2)->create(['facility_id' => $facility->id]);
        
        foreach ($patients as $patient) {
            Visit::factory()->create([
                'patient_id' => $patient->id,
                'admitted_at' => now()->subDays(1),
                'discharged_at' => null
            ]);
        }

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/dashboard/stats');

        $response->assertStatus(200);
        $this->assertArrayHasKey('bed_occupancy_rate', $response->json('data'));
        $this->assertIsNumeric($response->json('data.bed_occupancy_rate'));
    }

    public function test_dashboard_stats_calculates_average_length_of_stay()
    {
        $facility = Facility::factory()->create();
        $patient = Patient::factory()->create(['facility_id' => $facility->id]);
        
        // Create completed visit with known length of stay
        Visit::factory()->create([
            'patient_id' => $patient->id,
            'admitted_at' => now()->subDays(5),
            'discharged_at' => now()->subDays(2) // 3 days stay
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/dashboard/stats');

        $response->assertStatus(200);
        $this->assertArrayHasKey('average_length_of_stay', $response->json('data'));
        $this->assertIsNumeric($response->json('data.average_length_of_stay'));
    }

    public function test_active_patients_includes_current_encounter()
    {
        $facility = Facility::factory()->create();
        $patient = Patient::factory()->create(['facility_id' => $facility->id]);
        $visit = Visit::factory()->create([
            'patient_id' => $patient->id,
            'admitted_at' => now()->subDay(),
            'discharged_at' => null
        ]);

        $encounter = Encounter::factory()->create([
            'visit_id' => $visit->id,
            'started_at' => now()->subHours(2),
            'ended_at' => null // Active encounter
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/dashboard/active-patients');

        $response->assertStatus(200);
        $this->assertNotNull($response->json('data.0.current_encounter'));
        $this->assertEquals($encounter->id, $response->json('data.0.current_encounter.id'));
    }

    public function test_recent_activity_orders_by_most_recent()
    {
        $facility = Facility::factory()->create();
        $patient = Patient::factory()->create(['facility_id' => $facility->id]);
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);

        $oldEncounter = Encounter::factory()->create([
            'visit_id' => $visit->id,
            'started_at' => now()->subHours(5)
        ]);

        $newEncounter = Encounter::factory()->create([
            'visit_id' => $visit->id,
            'started_at' => now()->subHour()
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/dashboard/recent-activity');

        $response->assertStatus(200);
        
        if (count($response->json('data')) > 1) {
            $firstActivity = $response->json('data.0');
            $secondActivity = $response->json('data.1');
            
            $this->assertGreaterThan(
                strtotime($secondActivity['timestamp']),
                strtotime($firstActivity['timestamp'])
            );
        }
    }

    public function test_dashboard_endpoints_require_authentication()
    {
        $response = $this->getJson('/api/v1/dashboard/stats');
        $response->assertStatus(401);

        $response = $this->getJson('/api/v1/dashboard/active-patients');
        $response->assertStatus(401);

        $response = $this->getJson('/api/v1/dashboard/recent-activity');
        $response->assertStatus(401);
    }

    public function test_dashboard_stats_includes_pending_discharges()
    {
        $facility = Facility::factory()->create();
        $patient = Patient::factory()->create(['facility_id' => $facility->id]);
        
        // Create visit that should be discharged (long stay)
        Visit::factory()->create([
            'patient_id' => $patient->id,
            'admitted_at' => now()->subDays(10),
            'discharged_at' => null,
            'expected_discharge_date' => now()->subDay()
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/dashboard/stats');

        $response->assertStatus(200);
        $this->assertArrayHasKey('pending_discharges', $response->json('data'));
        $this->assertIsNumeric($response->json('data.pending_discharges'));
    }
}