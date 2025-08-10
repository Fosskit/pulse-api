<?php

namespace Tests\Feature\Api\V1;

use App\Models\Department;
use App\Models\Encounter;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\Room;
use App\Models\Term;
use App\Models\Terminology;
use App\Models\User;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FacilityUtilizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create and authenticate a user
        $user = User::factory()->create();
        Sanctum::actingAs($user);
    }

    public function test_can_get_facility_utilization_report()
    {
        // Create test data
        $facility = Facility::factory()->create();
        $department = Department::factory()->create(['facility_id' => $facility->id]);
        $terminology = Terminology::factory()->create();
        $roomType = Term::factory()->create(['terminology_id' => $terminology->id]);
        $room = Room::factory()->create([
            'department_id' => $department->id,
            'room_type_id' => $roomType->id
        ]);

        // Create some visits and encounters for utilization data
        $patient = Patient::factory()->create(['facility_id' => $facility->id]);
        $visit = Visit::factory()->create([
            'patient_id' => $patient->id,
            'facility_id' => $facility->id,
            'admitted_at' => Carbon::now()->subHours(2),
            'discharged_at' => null
        ]);

        $response = $this->getJson("/api/v1/facilities/{$facility->id}/utilization");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'facility' => [
                            'id',
                            'code'
                        ],
                        'report_period' => [
                            'start',
                            'end',
                            'generated_at'
                        ],
                        'utilization_stats' => [
                            'total_capacity',
                            'active_patients',
                            'active_encounters',
                            'overall_utilization_percentage'
                        ],
                        'department_utilization' => [
                            '*' => [
                                'department_id',
                                'department_name',
                                'room_count',
                                'estimated_capacity',
                                'utilization_percentage',
                                'status'
                            ]
                        ],
                        'room_availability',
                        'recommendations'
                    ]
                ]);
    }

    public function test_can_get_utilization_report_with_date_range()
    {
        $facility = Facility::factory()->create();
        $department = Department::factory()->create(['facility_id' => $facility->id]);

        $startDate = Carbon::now()->subDays(7)->toDateString();
        $endDate = Carbon::now()->toDateString();

        $response = $this->getJson("/api/v1/facilities/{$facility->id}/utilization?start_date={$startDate}&end_date={$endDate}");

        $response->assertStatus(200)
                ->assertJsonPath('data.report_period.start', Carbon::parse($startDate)->startOfDay()->toISOString())
                ->assertJsonPath('data.report_period.end', Carbon::parse($endDate)->startOfDay()->toISOString());
    }

    public function test_returns_404_for_nonexistent_facility_utilization()
    {
        $response = $this->getJson('/api/v1/facilities/999/utilization');

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'error' => [
                        'code' => 'NOT_FOUND',
                        'message' => 'Facility not found'
                    ]
                ]);
    }

    public function test_validates_date_range_for_utilization_report()
    {
        $facility = Facility::factory()->create();

        // Test invalid date range (end before start)
        $startDate = Carbon::now()->toDateString();
        $endDate = Carbon::now()->subDays(1)->toDateString();

        $response = $this->getJson("/api/v1/facilities/{$facility->id}/utilization?start_date={$startDate}&end_date={$endDate}");

        $response->assertStatus(422);
        // Let's see what validation errors we actually get
        $this->assertTrue($response->json('success') === false);
    }
}