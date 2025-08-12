<?php

namespace Tests\Unit\Actions;

use App\Actions\GetVillagesByCommuneAction;
use App\Models\Gazetteer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetVillagesByCommuneActionTest extends TestCase
{
    use RefreshDatabase;

    private GetVillagesByCommuneAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new GetVillagesByCommuneAction();
    }

    public function test_returns_villages_for_commune()
    {
        $province = Gazetteer::factory()->create(['type' => 'Province']);
        $district = Gazetteer::factory()->create([
            'type' => 'District',
            'parent_id' => $province->id
        ]);
        $commune = Gazetteer::factory()->create([
            'type' => 'Commune',
            'name' => 'Test Commune',
            'parent_id' => $district->id
        ]);

        $village1 = Gazetteer::factory()->create([
            'type' => 'Village',
            'name' => 'Village A',
            'parent_id' => $commune->id
        ]);
        $village2 = Gazetteer::factory()->create([
            'type' => 'Village',
            'name' => 'Village B',
            'parent_id' => $commune->id
        ]);

        // Village from different commune
        $otherCommune = Gazetteer::factory()->create([
            'type' => 'Commune',
            'parent_id' => $district->id
        ]);
        Gazetteer::factory()->create([
            'type' => 'Village',
            'name' => 'Other Village',
            'parent_id' => $otherCommune->id
        ]);

        $villages = $this->action->execute($commune->id);

        $this->assertCount(2, $villages);
        $this->assertTrue($villages->contains('name', 'Village A'));
        $this->assertTrue($villages->contains('name', 'Village B'));
        $this->assertFalse($villages->contains('name', 'Other Village'));
    }

    public function test_returns_empty_collection_for_commune_without_villages()
    {
        $province = Gazetteer::factory()->create(['type' => 'Province']);
        $district = Gazetteer::factory()->create([
            'type' => 'District',
            'parent_id' => $province->id
        ]);
        $commune = Gazetteer::factory()->create([
            'type' => 'Commune',
            'parent_id' => $district->id
        ]);

        $villages = $this->action->execute($commune->id);

        $this->assertCount(0, $villages);
    }

    public function test_filters_only_village_type()
    {
        $province = Gazetteer::factory()->create(['type' => 'Province']);
        $district = Gazetteer::factory()->create([
            'type' => 'District',
            'parent_id' => $province->id
        ]);
        $commune = Gazetteer::factory()->create([
            'type' => 'Commune',
            'parent_id' => $district->id
        ]);

        Gazetteer::factory()->create([
            'type' => 'Village',
            'name' => 'Valid Village',
            'parent_id' => $commune->id
        ]);
        Gazetteer::factory()->create([
            'type' => 'Commune',
            'name' => 'Not Village',
            'parent_id' => $commune->id
        ]);
        Gazetteer::factory()->create([
            'type' => 'District',
            'name' => 'Also Not Village',
            'parent_id' => $commune->id
        ]);

        $villages = $this->action->execute($commune->id);

        $this->assertCount(1, $villages);
        $this->assertEquals('Valid Village', $villages->first()->name);
    }

    public function test_orders_villages_alphabetically()
    {
        $province = Gazetteer::factory()->create(['type' => 'Province']);
        $district = Gazetteer::factory()->create([
            'type' => 'District',
            'parent_id' => $province->id
        ]);
        $commune = Gazetteer::factory()->create([
            'type' => 'Commune',
            'parent_id' => $district->id
        ]);

        Gazetteer::factory()->create([
            'type' => 'Village',
            'name' => 'Zebra Village',
            'parent_id' => $commune->id
        ]);
        Gazetteer::factory()->create([
            'type' => 'Village',
            'name' => 'Alpha Village',
            'parent_id' => $commune->id
        ]);
        Gazetteer::factory()->create([
            'type' => 'Village',
            'name' => 'Beta Village',
            'parent_id' => $commune->id
        ]);

        $villages = $this->action->execute($commune->id);

        $this->assertEquals('Alpha Village', $villages->first()->name);
        $this->assertEquals('Zebra Village', $villages->last()->name);
    }

    public function test_handles_nonexistent_commune()
    {
        $villages = $this->action->execute(999);

        $this->assertCount(0, $villages);
    }

    public function test_includes_village_attributes()
    {
        $province = Gazetteer::factory()->create(['type' => 'Province']);
        $district = Gazetteer::factory()->create([
            'type' => 'District',
            'parent_id' => $province->id
        ]);
        $commune = Gazetteer::factory()->create([
            'type' => 'Commune',
            'parent_id' => $district->id
        ]);

        $village = Gazetteer::factory()->create([
            'type' => 'Village',
            'name' => 'Test Village',
            'code' => 'TV',
            'parent_id' => $commune->id
        ]);

        $villages = $this->action->execute($commune->id);

        $retrievedVillage = $villages->first();
        $this->assertEquals($village->id, $retrievedVillage->id);
        $this->assertEquals('Test Village', $retrievedVillage->name);
        $this->assertEquals('TV', $retrievedVillage->code);
        $this->assertEquals('Village', $retrievedVillage->type);
        $this->assertEquals($commune->id, $retrievedVillage->parent_id);
    }

    public function test_validates_commune_id_parameter()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->action->execute(null);
    }

    public function test_handles_string_commune_id()
    {
        $province = Gazetteer::factory()->create(['type' => 'Province']);
        $district = Gazetteer::factory()->create([
            'type' => 'District',
            'parent_id' => $province->id
        ]);
        $commune = Gazetteer::factory()->create([
            'type' => 'Commune',
            'parent_id' => $district->id
        ]);

        Gazetteer::factory()->create([
            'type' => 'Village',
            'name' => 'Test Village',
            'parent_id' => $commune->id
        ]);

        $villages = $this->action->execute((string) $commune->id);

        $this->assertCount(1, $villages);
        $this->assertEquals('Test Village', $villages->first()->name);
    }
}