<?php

namespace Tests\Unit\Actions;

use App\Actions\CheckRoomAvailabilityAction;
use App\Models\Facility;
use App\Models\Department;
use App\Models\Room;
use App\Models\Patient;
use App\Models\Visit;
use App\Models\Encounter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckRoomAvailabilityActionTest extends TestCase
{
    use RefreshDatabase;

    private CheckRoomAvailabilityAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new CheckRoomAvailabilityAction();
    }

    public function test_returns_true_for_available_room()
    {
        $facility = Facility::factory()->create();
        $department = Department::factory()->create(['facility_id' => $facility->id]);
        $room = Room::factory()->create([
            'department_id' => $department->id,
            'occupied' => false
        ]);

        $isAvailable = $this->action->execute($room->id);

        $this->assertTrue($isAvailable);
    }

    public function test_returns_false_for_occupied_room()
    {
        $facility = Facility::factory()->create();
        $department = Department::factory()->create(['facility_id' => $facility->id]);
        $room = Room::factory()->create([
            'department_id' => $department->id,
            'occupied' => true
        ]);

        $isAvailable = $this->action->execute($room->id);

        $this->assertFalse($isAvailable);
    }

    public function test_checks_active_encounters_in_room()
    {
        $facility = Facility::factory()->create();
        $department = Department::factory()->create(['facility_id' => $facility->id]);
        $room = Room::factory()->create([
            'department_id' => $department->id,
            'occupied' => false // Room marked as not occupied
        ]);

        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);

        // Create active encounter in the room
        Encounter::factory()->create([
            'visit_id' => $visit->id,
            'room_id' => $room->id,
            'started_at' => now()->subHour(),
            'ended_at' => null // Active encounter
        ]);

        $isAvailable = $this->action->execute($room->id);

        $this->assertFalse($isAvailable);
    }

    public function test_ignores_ended_encounters_in_room()
    {
        $facility = Facility::factory()->create();
        $department = Department::factory()->create(['facility_id' => $facility->id]);
        $room = Room::factory()->create([
            'department_id' => $department->id,
            'occupied' => false
        ]);

        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);

        // Create ended encounter in the room
        Encounter::factory()->create([
            'visit_id' => $visit->id,
            'room_id' => $room->id,
            'started_at' => now()->subHours(2),
            'ended_at' => now()->subHour() // Ended encounter
        ]);

        $isAvailable = $this->action->execute($room->id);

        $this->assertTrue($isAvailable);
    }

    public function test_checks_room_capacity()
    {
        $facility = Facility::factory()->create();
        $department = Department::factory()->create(['facility_id' => $facility->id]);
        $room = Room::factory()->create([
            'department_id' => $department->id,
            'capacity' => 2,
            'occupied' => false
        ]);

        $patient1 = Patient::factory()->create();
        $patient2 = Patient::factory()->create();
        $visit1 = Visit::factory()->create(['patient_id' => $patient1->id]);
        $visit2 = Visit::factory()->create(['patient_id' => $patient2->id]);

        // Create one active encounter (room should still be available)
        Encounter::factory()->create([
            'visit_id' => $visit1->id,
            'room_id' => $room->id,
            'started_at' => now()->subHour(),
            'ended_at' => null
        ]);

        $isAvailable = $this->action->execute($room->id);
        $this->assertTrue($isAvailable);

        // Create second active encounter (room should now be at capacity)
        Encounter::factory()->create([
            'visit_id' => $visit2->id,
            'room_id' => $room->id,
            'started_at' => now()->subMinutes(30),
            'ended_at' => null
        ]);

        $isAvailable = $this->action->execute($room->id);
        $this->assertFalse($isAvailable);
    }

    public function test_handles_room_with_unlimited_capacity()
    {
        $facility = Facility::factory()->create();
        $department = Department::factory()->create(['facility_id' => $facility->id]);
        $room = Room::factory()->create([
            'department_id' => $department->id,
            'capacity' => null, // Unlimited capacity
            'occupied' => false
        ]);

        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);

        // Create multiple active encounters
        Encounter::factory()->count(5)->create([
            'visit_id' => $visit->id,
            'room_id' => $room->id,
            'started_at' => now()->subHour(),
            'ended_at' => null
        ]);

        $isAvailable = $this->action->execute($room->id);

        $this->assertTrue($isAvailable);
    }

    public function test_checks_availability_for_specific_time_range()
    {
        $facility = Facility::factory()->create();
        $department = Department::factory()->create(['facility_id' => $facility->id]);
        $room = Room::factory()->create([
            'department_id' => $department->id,
            'occupied' => false
        ]);

        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);

        // Create encounter that ends before our check time
        Encounter::factory()->create([
            'visit_id' => $visit->id,
            'room_id' => $room->id,
            'started_at' => now()->subHours(3),
            'ended_at' => now()->subHours(2)
        ]);

        $checkTime = now()->subHour();
        $isAvailable = $this->action->execute($room->id, $checkTime);

        $this->assertTrue($isAvailable);
    }

    public function test_returns_false_for_nonexistent_room()
    {
        $isAvailable = $this->action->execute(999);

        $this->assertFalse($isAvailable);
    }

    public function test_handles_maintenance_status()
    {
        $facility = Facility::factory()->create();
        $department = Department::factory()->create(['facility_id' => $facility->id]);
        $room = Room::factory()->create([
            'department_id' => $department->id,
            'occupied' => false,
            'maintenance' => true
        ]);

        $isAvailable = $this->action->execute($room->id);

        $this->assertFalse($isAvailable);
    }

    public function test_validates_room_id_parameter()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->action->execute(null);
    }

    public function test_returns_availability_details()
    {
        $facility = Facility::factory()->create();
        $department = Department::factory()->create(['facility_id' => $facility->id]);
        $room = Room::factory()->create([
            'department_id' => $department->id,
            'capacity' => 3,
            'occupied' => false
        ]);

        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);

        // Create one active encounter
        Encounter::factory()->create([
            'visit_id' => $visit->id,
            'room_id' => $room->id,
            'started_at' => now()->subHour(),
            'ended_at' => null
        ]);

        $details = $this->action->execute($room->id, null, true);

        $this->assertIsArray($details);
        $this->assertTrue($details['available']);
        $this->assertEquals(3, $details['capacity']);
        $this->assertEquals(1, $details['current_occupancy']);
        $this->assertEquals(2, $details['available_spots']);
    }
}