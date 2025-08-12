<?php

namespace Tests\Unit\Actions;

use App\Actions\GetCommunesByDistrictAction;
use App\Models\Gazetteer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetCommunesByDistrictActionTest extends TestCase
{
    use RefreshDatabase;

    private GetCommunesByDistrictAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new GetCommunesByDistrictAction();
    }

    public function test_returns_communes_for_district()
    {
        $province = Gazetteer::factory()->create(['type' => 'Province']);
        $district = Gazetteer::factory()->create([
            'type' => 'District',
            'name' => 'Test District',
            'parent_id' => $province->id
        ]);

        $commune1 = Gazetteer::factory()->create([
            'type' => 'Commune',
            'name' => 'Commune A',
            'parent_id' => $district->id
        ]);
        $commune2 = Gazetteer::factory()->create([
            'type' => 'Commune',
            'name' => 'Commune B',
            'parent_id' => $district->id
        ]);

        // Commune from different district
        $otherDistrict = Gazetteer::factory()->create([
            'type' => 'District',
            'parent_id' => $province->id
        ]);
        Gazetteer::factory()->create([
            'type' => 'Commune',
            'name' => 'Other Commune',
            'parent_id' => $otherDistrict->id
        ]);

        $communes = $this->action->execute($district->id);

        $this->assertCount(2, $communes);
        $this->assertTrue($communes->contains('name', 'Commune A'));
        $this->assertTrue($communes->contains('name', 'Commune B'));
        $this->assertFalse($communes->contains('name', 'Other Commune'));
    }

    public function test_returns_empty_collection_for_district_without_communes()
    {
        $province = Gazetteer::factory()->create(['type' => 'Province']);
        $district = Gazetteer::factory()->create([
            'type' => 'District',
            'parent_id' => $province->id
        ]);

        $communes = $this->action->execute($district->id);

        $this->assertCount(0, $communes);
    }

    public function test_filters_only_commune_type()
    {
        $province = Gazetteer::factory()->create(['type' => 'Province']);
        $district = Gazetteer::factory()->create([
            'type' => 'District',
            'parent_id' => $province->id
        ]);

        Gazetteer::factory()->create([
            'type' => 'Commune',
            'name' => 'Valid Commune',
            'parent_id' => $district->id
        ]);
        Gazetteer::factory()->create([
            'type' => 'Village',
            'name' => 'Not Commune',
            'parent_id' => $district->id
        ]);
        Gazetteer::factory()->create([
            'type' => 'District',
            'name' => 'Also Not Commune',
            'parent_id' => $district->id
        ]);

        $communes = $this->action->execute($district->id);

        $this->assertCount(1, $communes);
        $this->assertEquals('Valid Commune', $communes->first()->name);
    }

    public function test_orders_communes_alphabetically()
    {
        $province = Gazetteer::factory()->create(['type' => 'Province']);
        $district = Gazetteer::factory()->create([
            'type' => 'District',
            'parent_id' => $province->id
        ]);

        Gazetteer::factory()->create([
            'type' => 'Commune',
            'name' => 'Zebra Commune',
            'parent_id' => $district->id
        ]);
        Gazetteer::factory()->create([
            'type' => 'Commune',
            'name' => 'Alpha Commune',
            'parent_id' => $district->id
        ]);
        Gazetteer::factory()->create([
            'type' => 'Commune',
            'name' => 'Beta Commune',
            'parent_id' => $district->id
        ]);

        $communes = $this->action->execute($district->id);

        $this->assertEquals('Alpha Commune', $communes->first()->name);
        $this->assertEquals('Zebra Commune', $communes->last()->name);
    }

    public function test_handles_nonexistent_district()
    {
        $communes = $this->action->execute(999);

        $this->assertCount(0, $communes);
    }

    public function test_includes_commune_attributes()
    {
        $province = Gazetteer::factory()->create(['type' => 'Province']);
        $district = Gazetteer::factory()->create([
            'type' => 'District',
            'parent_id' => $province->id
        ]);

        $commune = Gazetteer::factory()->create([
            'type' => 'Commune',
            'name' => 'Test Commune',
            'code' => 'TC',
            'parent_id' => $district->id
        ]);

        $communes = $this->action->execute($district->id);

        $retrievedCommune = $communes->first();
        $this->assertEquals($commune->id, $retrievedCommune->id);
        $this->assertEquals('Test Commune', $retrievedCommune->name);
        $this->assertEquals('TC', $retrievedCommune->code);
        $this->assertEquals('Commune', $retrievedCommune->type);
        $this->assertEquals($district->id, $retrievedCommune->parent_id);
    }

    public function test_validates_district_id_parameter()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->action->execute(null);
    }

    public function test_handles_string_district_id()
    {
        $province = Gazetteer::factory()->create(['type' => 'Province']);
        $district = Gazetteer::factory()->create([
            'type' => 'District',
            'parent_id' => $province->id
        ]);

        Gazetteer::factory()->create([
            'type' => 'Commune',
            'name' => 'Test Commune',
            'parent_id' => $district->id
        ]);

        $communes = $this->action->execute((string) $district->id);

        $this->assertCount(1, $communes);
        $this->assertEquals('Test Commune', $communes->first()->name);
    }
}