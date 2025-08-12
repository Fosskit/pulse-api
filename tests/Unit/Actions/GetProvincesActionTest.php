<?php

namespace Tests\Unit\Actions;

use App\Actions\GetProvincesAction;
use App\Models\Gazetteer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetProvincesActionTest extends TestCase
{
    use RefreshDatabase;

    private GetProvincesAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new GetProvincesAction();
    }

    public function test_returns_all_provinces()
    {
        // Create provinces
        $province1 = Gazetteer::factory()->create([
            'type' => 'Province',
            'name' => 'Phnom Penh',
            'parent_id' => null
        ]);
        $province2 = Gazetteer::factory()->create([
            'type' => 'Province',
            'name' => 'Siem Reap',
            'parent_id' => null
        ]);

        // Create non-province entries
        Gazetteer::factory()->create([
            'type' => 'District',
            'name' => 'Some District',
            'parent_id' => $province1->id
        ]);

        $provinces = $this->action->execute();

        $this->assertCount(2, $provinces);
        $this->assertTrue($provinces->contains('name', 'Phnom Penh'));
        $this->assertTrue($provinces->contains('name', 'Siem Reap'));
    }

    public function test_returns_empty_collection_when_no_provinces()
    {
        // Create only non-province entries
        Gazetteer::factory()->create([
            'type' => 'District',
            'name' => 'Some District'
        ]);

        $provinces = $this->action->execute();

        $this->assertCount(0, $provinces);
    }

    public function test_orders_provinces_alphabetically()
    {
        Gazetteer::factory()->create([
            'type' => 'Province',
            'name' => 'Siem Reap',
            'parent_id' => null
        ]);
        Gazetteer::factory()->create([
            'type' => 'Province',
            'name' => 'Battambang',
            'parent_id' => null
        ]);
        Gazetteer::factory()->create([
            'type' => 'Province',
            'name' => 'Phnom Penh',
            'parent_id' => null
        ]);

        $provinces = $this->action->execute();

        $this->assertEquals('Battambang', $provinces->first()->name);
        $this->assertEquals('Siem Reap', $provinces->last()->name);
    }

    public function test_filters_only_province_type()
    {
        Gazetteer::factory()->create([
            'type' => 'Province',
            'name' => 'Valid Province',
            'parent_id' => null
        ]);
        Gazetteer::factory()->create([
            'type' => 'PROVINCE', // Different case
            'name' => 'Invalid Case',
            'parent_id' => null
        ]);
        Gazetteer::factory()->create([
            'type' => 'District',
            'name' => 'Not Province',
            'parent_id' => 1
        ]);

        $provinces = $this->action->execute();

        $this->assertCount(1, $provinces);
        $this->assertEquals('Valid Province', $provinces->first()->name);
    }

    public function test_includes_province_attributes()
    {
        $province = Gazetteer::factory()->create([
            'type' => 'Province',
            'name' => 'Test Province',
            'code' => 'TP',
            'parent_id' => null
        ]);

        $provinces = $this->action->execute();

        $retrievedProvince = $provinces->first();
        $this->assertEquals($province->id, $retrievedProvince->id);
        $this->assertEquals('Test Province', $retrievedProvince->name);
        $this->assertEquals('TP', $retrievedProvince->code);
        $this->assertEquals('Province', $retrievedProvince->type);
        $this->assertNull($retrievedProvince->parent_id);
    }

    public function test_handles_provinces_with_special_characters()
    {
        Gazetteer::factory()->create([
            'type' => 'Province',
            'name' => 'Koh Kong',
            'parent_id' => null
        ]);
        Gazetteer::factory()->create([
            'type' => 'Province',
            'name' => 'Preah Vihear',
            'parent_id' => null
        ]);

        $provinces = $this->action->execute();

        $this->assertCount(2, $provinces);
        $this->assertTrue($provinces->contains('name', 'Koh Kong'));
        $this->assertTrue($provinces->contains('name', 'Preah Vihear'));
    }
}