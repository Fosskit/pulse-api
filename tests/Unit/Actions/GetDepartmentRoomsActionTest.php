<?php

namespace Tests\Unit\Actions;

use App\Actions\GetDepartmentRoomsAction;
use App\Models\Facility;
use App\Models\Department;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetDepartmentRoomsActionTest extends TestCase
{
    use RefreshDatabase;

    private GetDepartmentRoomsAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new GetDepartmentRoomsAction();
    }

    public function test_returns_rooms_for_department()
    {
        $facility = Facility::factory()->create();
        $department = Department::factory()->create(['facility_id' => $facility->id]);

        $room1 = Room::factory()->create([
            'department_id' => $department->id,
            'name' => 'Room 101'
        ]);
        $room2 = Room::factory()->create([
            'department_id' => $department->id,
            'name' => 'Room 102'
        ]);

        // Room from different department
        $otherDepartment = Department::factory()->create(['facility_id' => $facility->id]);
        Room::factory()->create([
            'department_id' => $otherDepartment->id,
            'name' => 'Other Room'
        ]);

        $rooms = $this->action->execute($department->id);

        $this->assertCount(2, $rooms);
        $this->assertTrue($rooms->contains('name', 'Room 101'));
        $this->assertTrue($rooms->contains('name', 'Room 102'));
        $this->assertFalse($rooms->contains('name', 'Other Room'));
    }

    public function test_returns_empty_collection_for_department_without_rooms()
    {
        $facility = Facility::factory()->create();
        $department = Department::factory()->create(['facility_id' => $facility->id]);

        $rooms = $this->action->execute($department->id);

        $this->assertCount(0, $rooms);
    }

    public function test_orders_rooms_by_name()
    {
        $facility = Facility::factory()->create();
        $department = Department::factory()->create(['facility_id' => $facility->id]);

        Room::factory()->create([
            'department_id' => $department->id,
            'name' => 'Room 103'
        ]);
        Room::factory()->create([
            'department_id' => $department->id,
            'name' => 'Room 101'
        ]);
        Room::factory()->create([
            'department_id' => $department->id,
            'name' => 'Room 102'
        ]);

        $rooms = $this->action->execute($department->id);

        $this->assertEquals('Room 101', $rooms->first()->name);
        $this->assertEquals('Room 103', $rooms->last()->name);
    }

    public function test_filters_by_room_type()
    {
        $facility = Facility::factory()->create();
        $department = Department::factory()->create(['facility_id' => $facility->id]);

        Room::factory()->create([
            'department_id' => $department->id,
            'name' => 'Private Room',
            'room_type' => 'private'
        ]);
        Room::factory()->create([
            'department_id' => $department->id,
            'name' => 'Shared Room',
            'room_type' => 'shared'
        ]);
        Room::factory()->create([
            'department_id' => $department->id,
            'name' => 'ICU Room',
            'room_type' => 'icu'
        ]);

        $privateRooms = $this->action->execute($department->id, ['type' => 'private']);
        $icuRooms = $this->action->execute($department->id, ['type' => 'icu']);

        $this->assertCount(1, $privateRooms);
        $this->assertEquals('Private Room', $privateRooms->first()->name);

        $this->assertCount(1, $icuRooms);
        $this->assertEquals('ICU Room', $icuRooms->first()->name);
    }

    public function test_filters_by_availability()
    {
        $facility = Facility::factory()->create();
        $department = Department::factory()->create(['facility_id' => $facility->id]);

        Room::factory()->create([
            'department_id' => $department->id,
            'name' => 'Available Room',
            'occupied' => false
        ]);
        Room::factory()->create([
            'department_id' => $department->id,
            'name' => 'Occupied Room',
            'occupied' => true
        ]);

        $availableRooms = $this->action->execute($department->id, ['available' => true]);
        $occupiedRooms = $this->action->execute($department->id, ['available' => false]);

        $this->assertCount(1, $availableRooms);
        $this->assertEquals('Available Room', $availableRooms->first()->name);

        $this->assertCount(1, $occupiedRooms);
        $this->assertEquals('Occupied Room', $occupiedRooms->first()->name);
    }

    public function test_searches_by_room_name()
    {
        $facility = Facility::factory()->create();
        $department = Department::factory()->create(['facility_id' => $facility->id]);

        Room::factory()->create([
            'department_id' => $department->id,
            'name' => 'Operating Room 1'
        ]);
        Room::factory()->create([
            'department_id' => $department->id,
            'name' => 'Recovery Room 1'
        ]);
        Room::factory()->create([
            'department_id' => $department->id,
            'name' => 'Patient Room 101'
        ]);

        $results = $this->action->execute($department->id, ['search' => 'Operating']);

        $this->assertCount(1, $results);
        $this->assertEquals('Operating Room 1', $results->first()->name);
    }

    public function test_handles_nonexistent_department()
    {
        $rooms = $this->action->execute(999);

        $this->assertCount(0, $rooms);
    }

    public function test_includes_room_attributes()
    {
        $facility = Facility::factory()->create();
        $department = Department::factory()->create(['facility_id' => $facility->id]);

        $room = Room::factory()->create([
            'department_id' => $department->id,
            'name' => 'Test Room',
            'room_type' => 'private',
            'capacity' => 2,
            'occupied' => false
        ]);

        $rooms = $this->action->execute($department->id);

        $retrievedRoom = $rooms->first();
        $this->assertEquals($room->id, $retrievedRoom->id);
        $this->assertEquals('Test Room', $retrievedRoom->name);
        $this->assertEquals('private', $retrievedRoom->room_type);
        $this->assertEquals(2, $retrievedRoom->capacity);
        $this->assertEquals($department->id, $retrievedRoom->department_id);
        $this->assertFalse($retrievedRoom->occupied);
    }

    public function test_validates_department_id_parameter()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->action->execute(null);
    }

    public function test_handles_string_department_id()
    {
        $facility = Facility::factory()->create();
        $department = Department::factory()->create(['facility_id' => $facility->id]);

        Room::factory()->create([
            'department_id' => $department->id,
            'name' => 'Test Room'
        ]);

        $rooms = $this->action->execute((string) $department->id);

        $this->assertCount(1, $rooms);
        $this->assertEquals('Test Room', $rooms->first()->name);
    }

    public function test_combines_multiple_filters()
    {
        $facility = Facility::factory()->create();
        $department = Department::factory()->create(['facility_id' => $facility->id]);

        Room::factory()->create([
            'department_id' => $department->id,
            'name' => 'Available Private Room',
            'room_type' => 'private',
            'occupied' => false
        ]);
        Room::factory()->create([
            'department_id' => $department->id,
            'name' => 'Occupied Private Room',
            'room_type' => 'private',
            'occupied' => true
        ]);
        Room::factory()->create([
            'department_id' => $department->id,
            'name' => 'Available Shared Room',
            'room_type' => 'shared',
            'occupied' => false
        ]);

        $results = $this->action->execute($department->id, [
            'type' => 'private',
            'available' => true
        ]);

        $this->assertCount(1, $results);
        $this->assertEquals('Available Private Room', $results->first()->name);
    }

    public function test_handles_empty_filters()
    {
        $facility = Facility::factory()->create();
        $department = Department::factory()->create(['facility_id' => $facility->id]);

        Room::factory()->create([
            'department_id' => $department->id,
            'name' => 'Test Room'
        ]);

        $rooms = $this->action->execute($department->id, []);

        $this->assertCount(1, $rooms);
    }
}