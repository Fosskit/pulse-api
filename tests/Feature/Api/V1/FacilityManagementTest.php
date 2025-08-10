<?php

namespace Tests\Feature\Api\V1;

use App\Models\Department;
use App\Models\Facility;
use App\Models\Room;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FacilityManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create and authenticate a user
        $user = User::factory()->create();
        Sanctum::actingAs($user);
    }

    public function test_can_get_facilities_list()
    {
        // Create test facilities
        $facility1 = Facility::factory()->create(['code' => 'FAC001']);
        $facility2 = Facility::factory()->create(['code' => 'FAC002']);

        $response = $this->getJson('/api/v1/facilities');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        '*' => [
                            'id',
                            'code',
                            'created_at',
                            'updated_at'
                        ]
                    ]
                ]);
    }

    public function test_can_get_single_facility()
    {
        $facility = Facility::factory()->create(['code' => 'FAC001']);

        $response = $this->getJson("/api/v1/facilities/{$facility->id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'id',
                        'code',
                        'created_at',
                        'updated_at'
                    ]
                ]);
    }

    public function test_can_get_facility_departments()
    {
        $facility = Facility::factory()->create();
        $department = Department::factory()->create([
            'facility_id' => $facility->id,
            'code' => 'DEPT001',
            'name' => 'Emergency Department'
        ]);

        $response = $this->getJson("/api/v1/facilities/{$facility->id}/departments");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        '*' => [
                            'id',
                            'facility_id',
                            'code',
                            'name',
                            'created_at',
                            'updated_at'
                        ]
                    ]
                ]);
    }

    public function test_can_get_department_rooms()
    {
        $facility = Facility::factory()->create();
        $department = Department::factory()->create(['facility_id' => $facility->id]);
        $roomType = Term::factory()->create();
        $room = Room::factory()->create([
            'department_id' => $department->id,
            'room_type_id' => $roomType->id,
            'code' => 'ROOM001',
            'name' => 'Emergency Room 1'
        ]);

        $response = $this->getJson("/api/v1/departments/{$department->id}/rooms");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        '*' => [
                            'id',
                            'department_id',
                            'room_type_id',
                            'code',
                            'name',
                            'created_at',
                            'updated_at'
                        ]
                    ]
                ]);
    }

    public function test_can_check_room_availability()
    {
        $facility = Facility::factory()->create();
        $department = Department::factory()->create(['facility_id' => $facility->id]);
        $roomType = Term::factory()->create();
        $room = Room::factory()->create([
            'department_id' => $department->id,
            'room_type_id' => $roomType->id
        ]);

        $response = $this->getJson("/api/v1/rooms/{$room->id}/availability");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'available',
                        'reason',
                        'room',
                        'conflicts',
                        'checked_period'
                    ]
                ]);
    }

    public function test_returns_404_for_nonexistent_facility()
    {
        $response = $this->getJson('/api/v1/facilities/999');

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'error' => [
                        'code' => 'NOT_FOUND',
                        'message' => 'Facility not found'
                    ]
                ]);
    }

    public function test_returns_404_for_nonexistent_department()
    {
        $response = $this->getJson('/api/v1/departments/999');

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'error' => [
                        'code' => 'NOT_FOUND',
                        'message' => 'Department not found'
                    ]
                ]);
    }

    public function test_returns_404_for_nonexistent_room()
    {
        $response = $this->getJson('/api/v1/rooms/999');

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'error' => [
                        'code' => 'NOT_FOUND',
                        'message' => 'Room not found'
                    ]
                ]);
    }
}