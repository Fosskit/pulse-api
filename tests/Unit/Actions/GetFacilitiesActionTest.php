<?php

namespace Tests\Unit\Actions;

use App\Actions\GetFacilitiesAction;
use App\Models\Facility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetFacilitiesActionTest extends TestCase
{
    use RefreshDatabase;

    private GetFacilitiesAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new GetFacilitiesAction();
    }

    public function test_returns_all_facilities()
    {
        $facility1 = Facility::factory()->create(['name' => 'Hospital A']);
        $facility2 = Facility::factory()->create(['name' => 'Clinic B']);
        $facility3 = Facility::factory()->create(['name' => 'Health Center C']);

        $facilities = $this->action->execute();

        $this->assertCount(3, $facilities);
        $this->assertTrue($facilities->contains('name', 'Hospital A'));
        $this->assertTrue($facilities->contains('name', 'Clinic B'));
        $this->assertTrue($facilities->contains('name', 'Health Center C'));
    }

    public function test_returns_empty_collection_when_no_facilities()
    {
        $facilities = $this->action->execute();

        $this->assertCount(0, $facilities);
    }

    public function test_orders_facilities_alphabetically()
    {
        Facility::factory()->create(['name' => 'Zebra Hospital']);
        Facility::factory()->create(['name' => 'Alpha Clinic']);
        Facility::factory()->create(['name' => 'Beta Health Center']);

        $facilities = $this->action->execute();

        $this->assertEquals('Alpha Clinic', $facilities->first()->name);
        $this->assertEquals('Zebra Hospital', $facilities->last()->name);
    }

    public function test_filters_by_facility_type()
    {
        Facility::factory()->create([
            'name' => 'General Hospital',
            'facility_type' => 'hospital'
        ]);
        Facility::factory()->create([
            'name' => 'Primary Clinic',
            'facility_type' => 'clinic'
        ]);
        Facility::factory()->create([
            'name' => 'Community Health Center',
            'facility_type' => 'health_center'
        ]);

        $hospitals = $this->action->execute(['type' => 'hospital']);
        $clinics = $this->action->execute(['type' => 'clinic']);

        $this->assertCount(1, $hospitals);
        $this->assertEquals('General Hospital', $hospitals->first()->name);

        $this->assertCount(1, $clinics);
        $this->assertEquals('Primary Clinic', $clinics->first()->name);
    }

    public function test_filters_by_active_status()
    {
        Facility::factory()->create([
            'name' => 'Active Facility',
            'active' => true
        ]);
        Facility::factory()->create([
            'name' => 'Inactive Facility',
            'active' => false
        ]);

        $activeFacilities = $this->action->execute(['active' => true]);
        $inactiveFacilities = $this->action->execute(['active' => false]);

        $this->assertCount(1, $activeFacilities);
        $this->assertEquals('Active Facility', $activeFacilities->first()->name);

        $this->assertCount(1, $inactiveFacilities);
        $this->assertEquals('Inactive Facility', $inactiveFacilities->first()->name);
    }

    public function test_searches_by_name()
    {
        Facility::factory()->create(['name' => 'Central Hospital']);
        Facility::factory()->create(['name' => 'Regional Clinic']);
        Facility::factory()->create(['name' => 'Community Health Center']);

        $results = $this->action->execute(['search' => 'Hospital']);

        $this->assertCount(1, $results);
        $this->assertEquals('Central Hospital', $results->first()->name);
    }

    public function test_searches_case_insensitive()
    {
        Facility::factory()->create(['name' => 'Central Hospital']);

        $results = $this->action->execute(['search' => 'hospital']);

        $this->assertCount(1, $results);
        $this->assertEquals('Central Hospital', $results->first()->name);
    }

    public function test_combines_multiple_filters()
    {
        Facility::factory()->create([
            'name' => 'Active Hospital',
            'facility_type' => 'hospital',
            'active' => true
        ]);
        Facility::factory()->create([
            'name' => 'Inactive Hospital',
            'facility_type' => 'hospital',
            'active' => false
        ]);
        Facility::factory()->create([
            'name' => 'Active Clinic',
            'facility_type' => 'clinic',
            'active' => true
        ]);

        $results = $this->action->execute([
            'type' => 'hospital',
            'active' => true
        ]);

        $this->assertCount(1, $results);
        $this->assertEquals('Active Hospital', $results->first()->name);
    }

    public function test_includes_facility_attributes()
    {
        $facility = Facility::factory()->create([
            'name' => 'Test Facility',
            'facility_type' => 'hospital',
            'address' => '123 Main St',
            'phone' => '123-456-7890',
            'active' => true
        ]);

        $facilities = $this->action->execute();

        $retrievedFacility = $facilities->first();
        $this->assertEquals($facility->id, $retrievedFacility->id);
        $this->assertEquals('Test Facility', $retrievedFacility->name);
        $this->assertEquals('hospital', $retrievedFacility->facility_type);
        $this->assertEquals('123 Main St', $retrievedFacility->address);
        $this->assertEquals('123-456-7890', $retrievedFacility->phone);
        $this->assertTrue($retrievedFacility->active);
    }

    public function test_handles_empty_filters()
    {
        Facility::factory()->create(['name' => 'Test Facility']);

        $facilities = $this->action->execute([]);

        $this->assertCount(1, $facilities);
    }

    public function test_handles_invalid_filter_values()
    {
        Facility::factory()->create(['name' => 'Test Facility']);

        $facilities = $this->action->execute(['type' => 'nonexistent']);

        $this->assertCount(0, $facilities);
    }
}