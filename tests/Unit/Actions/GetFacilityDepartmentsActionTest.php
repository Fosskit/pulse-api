<?php

namespace Tests\Unit\Actions;

use App\Actions\GetFacilityDepartmentsAction;
use App\Models\Facility;
use App\Models\Department;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetFacilityDepartmentsActionTest extends TestCase
{
    use RefreshDatabase;

    private GetFacilityDepartmentsAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new GetFacilityDepartmentsAction();
    }

    public function test_returns_departments_for_facility()
    {
        $facility = Facility::factory()->create(['name' => 'Test Hospital']);

        $department1 = Department::factory()->create([
            'facility_id' => $facility->id,
            'name' => 'Emergency'
        ]);
        $department2 = Department::factory()->create([
            'facility_id' => $facility->id,
            'name' => 'Surgery'
        ]);

        // Department from different facility
        $otherFacility = Facility::factory()->create();
        Department::factory()->create([
            'facility_id' => $otherFacility->id,
            'name' => 'Other Department'
        ]);

        $departments = $this->action->execute($facility->id);

        $this->assertCount(2, $departments);
        $this->assertTrue($departments->contains('name', 'Emergency'));
        $this->assertTrue($departments->contains('name', 'Surgery'));
        $this->assertFalse($departments->contains('name', 'Other Department'));
    }

    public function test_returns_empty_collection_for_facility_without_departments()
    {
        $facility = Facility::factory()->create();

        $departments = $this->action->execute($facility->id);

        $this->assertCount(0, $departments);
    }

    public function test_orders_departments_alphabetically()
    {
        $facility = Facility::factory()->create();

        Department::factory()->create([
            'facility_id' => $facility->id,
            'name' => 'Zebra Department'
        ]);
        Department::factory()->create([
            'facility_id' => $facility->id,
            'name' => 'Alpha Department'
        ]);
        Department::factory()->create([
            'facility_id' => $facility->id,
            'name' => 'Beta Department'
        ]);

        $departments = $this->action->execute($facility->id);

        $this->assertEquals('Alpha Department', $departments->first()->name);
        $this->assertEquals('Zebra Department', $departments->last()->name);
    }

    public function test_filters_by_active_status()
    {
        $facility = Facility::factory()->create();

        Department::factory()->create([
            'facility_id' => $facility->id,
            'name' => 'Active Department',
            'active' => true
        ]);
        Department::factory()->create([
            'facility_id' => $facility->id,
            'name' => 'Inactive Department',
            'active' => false
        ]);

        $activeDepartments = $this->action->execute($facility->id, ['active' => true]);
        $inactiveDepartments = $this->action->execute($facility->id, ['active' => false]);

        $this->assertCount(1, $activeDepartments);
        $this->assertEquals('Active Department', $activeDepartments->first()->name);

        $this->assertCount(1, $inactiveDepartments);
        $this->assertEquals('Inactive Department', $inactiveDepartments->first()->name);
    }

    public function test_searches_by_department_name()
    {
        $facility = Facility::factory()->create();

        Department::factory()->create([
            'facility_id' => $facility->id,
            'name' => 'Emergency Department'
        ]);
        Department::factory()->create([
            'facility_id' => $facility->id,
            'name' => 'Surgery Department'
        ]);
        Department::factory()->create([
            'facility_id' => $facility->id,
            'name' => 'Pediatric Ward'
        ]);

        $results = $this->action->execute($facility->id, ['search' => 'Department']);

        $this->assertCount(2, $results);
        $this->assertTrue($results->contains('name', 'Emergency Department'));
        $this->assertTrue($results->contains('name', 'Surgery Department'));
        $this->assertFalse($results->contains('name', 'Pediatric Ward'));
    }

    public function test_handles_nonexistent_facility()
    {
        $departments = $this->action->execute(999);

        $this->assertCount(0, $departments);
    }

    public function test_includes_department_attributes()
    {
        $facility = Facility::factory()->create();

        $department = Department::factory()->create([
            'facility_id' => $facility->id,
            'name' => 'Test Department',
            'description' => 'Test Description',
            'active' => true
        ]);

        $departments = $this->action->execute($facility->id);

        $retrievedDepartment = $departments->first();
        $this->assertEquals($department->id, $retrievedDepartment->id);
        $this->assertEquals('Test Department', $retrievedDepartment->name);
        $this->assertEquals('Test Description', $retrievedDepartment->description);
        $this->assertEquals($facility->id, $retrievedDepartment->facility_id);
        $this->assertTrue($retrievedDepartment->active);
    }

    public function test_validates_facility_id_parameter()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->action->execute(null);
    }

    public function test_handles_string_facility_id()
    {
        $facility = Facility::factory()->create();

        Department::factory()->create([
            'facility_id' => $facility->id,
            'name' => 'Test Department'
        ]);

        $departments = $this->action->execute((string) $facility->id);

        $this->assertCount(1, $departments);
        $this->assertEquals('Test Department', $departments->first()->name);
    }

    public function test_includes_room_count()
    {
        $facility = Facility::factory()->create();

        $department = Department::factory()->create([
            'facility_id' => $facility->id,
            'name' => 'Test Department'
        ]);

        // Create rooms for the department
        \App\Models\Room::factory()->count(3)->create([
            'department_id' => $department->id
        ]);

        $departments = $this->action->execute($facility->id);

        $retrievedDepartment = $departments->first();
        $this->assertEquals(3, $retrievedDepartment->rooms_count);
    }

    public function test_handles_empty_filters()
    {
        $facility = Facility::factory()->create();

        Department::factory()->create([
            'facility_id' => $facility->id,
            'name' => 'Test Department'
        ]);

        $departments = $this->action->execute($facility->id, []);

        $this->assertCount(1, $departments);
    }
}