<?php

namespace Tests\Unit\Actions;

use App\Actions\GetDistrictsByProvinceAction;
use App\Models\Gazetteer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetDistrictsByProvinceActionTest extends TestCase
{
    use RefreshDatabase;

    private GetDistrictsByProvinceAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new GetDistrictsByProvinceAction();
    }

    public function test_returns_districts_for_province()
    {
        $province = Gazetteer::factory()->create([
            'type' => 'Province',
            'name' => 'Phnom Penh'
        ]);

        $district1 = Gazetteer::factory()->create([
            'type' => 'District',
            'name' => 'Chamkar Mon',
            'parent_id' => $province->id
        ]);
        $district2 = Gazetteer::factory()->create([
            'type' => 'District',
            'name' => 'Daun Penh',
            'parent_id' => $province->id
        ]);

        // District from different province
        $otherProvince = Gazetteer::factory()->create([
            'type' => 'Province',
            'name' => 'Siem Reap'
        ]);
        Gazetteer::factory()->create([
            'type' => 'District',
            'name' => 'Other District',
            'parent_id' => $otherProvince->id
        ]);

        $districts = $this->action->execute($province->id);

        $this->assertCount(2, $districts);
        $this->assertTrue($districts->contains('name', 'Chamkar Mon'));
        $this->assertTrue($districts->contains('name', 'Daun Penh'));
        $this->assertFalse($districts->contains('name', 'Other District'));
    }

    public function test_returns_empty_collection_for_province_without_districts()
    {
        $province = Gazetteer::factory()->create([
            'type' => 'Province',
            'name' => 'Empty Province'
        ]);

        $districts = $this->action->execute($province->id);

        $this->assertCount(0, $districts);
    }

    public function test_filters_only_district_type()
    {
        $province = Gazetteer::factory()->create([
            'type' => 'Province',
            'name' => 'Test Province'
        ]);

        Gazetteer::factory()->create([
            'type' => 'District',
            'name' => 'Valid District',
            'parent_id' => $province->id
        ]);
        Gazetteer::factory()->create([
            'type' => 'Commune',
            'name' => 'Not District',
            'parent_id' => $province->id
        ]);
        Gazetteer::factory()->create([
            'type' => 'Village',
            'name' => 'Also Not District',
            'parent_id' => $province->id
        ]);

        $districts = $this->action->execute($province->id);

        $this->assertCount(1, $districts);
        $this->assertEquals('Valid District', $districts->first()->name);
    }

    public function test_orders_districts_alphabetically()
    {
        $province = Gazetteer::factory()->create([
            'type' => 'Province',
            'name' => 'Test Province'
        ]);

        Gazetteer::factory()->create([
            'type' => 'District',
            'name' => 'Zebra District',
            'parent_id' => $province->id
        ]);
        Gazetteer::factory()->create([
            'type' => 'District',
            'name' => 'Alpha District',
            'parent_id' => $province->id
        ]);
        Gazetteer::factory()->create([
            'type' => 'District',
            'name' => 'Beta District',
            'parent_id' => $province->id
        ]);

        $districts = $this->action->execute($province->id);

        $this->assertEquals('Alpha District', $districts->first()->name);
        $this->assertEquals('Zebra District', $districts->last()->name);
    }

    public function test_handles_nonexistent_province()
    {
        $districts = $this->action->execute(999);

        $this->assertCount(0, $districts);
    }

    public function test_includes_district_attributes()
    {
        $province = Gazetteer::factory()->create([
            'type' => 'Province',
            'name' => 'Test Province'
        ]);

        $district = Gazetteer::factory()->create([
            'type' => 'District',
            'name' => 'Test District',
            'code' => 'TD',
            'parent_id' => $province->id
        ]);

        $districts = $this->action->execute($province->id);

        $retrievedDistrict = $districts->first();
        $this->assertEquals($district->id, $retrievedDistrict->id);
        $this->assertEquals('Test District', $retrievedDistrict->name);
        $this->assertEquals('TD', $retrievedDistrict->code);
        $this->assertEquals('District', $retrievedDistrict->type);
        $this->assertEquals($province->id, $retrievedDistrict->parent_id);
    }

    public function test_validates_province_id_parameter()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->action->execute(null);
    }

    public function test_handles_string_province_id()
    {
        $province = Gazetteer::factory()->create([
            'type' => 'Province',
            'name' => 'Test Province'
        ]);

        Gazetteer::factory()->create([
            'type' => 'District',
            'name' => 'Test District',
            'parent_id' => $province->id
        ]);

        $districts = $this->action->execute((string) $province->id);

        $this->assertCount(1, $districts);
        $this->assertEquals('Test District', $districts->first()->name);
    }
}