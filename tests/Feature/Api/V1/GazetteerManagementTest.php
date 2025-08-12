<?php

namespace Tests\Feature\Api\V1;

use App\Models\Gazetteer;
use App\Models\PatientAddress;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GazetteerManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_get_provinces()
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

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/reference/gazetteers/provinces');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'code',
                        'type'
                    ]
                ]
            ]);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_can_get_districts_by_province()
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

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/reference/gazetteers/districts/{$province->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'code',
                        'type',
                        'parent_id'
                    ]
                ]
            ]);

        $this->assertCount(2, $response->json('data'));
        $this->assertEquals($province->id, $response->json('data.0.parent_id'));
    }

    public function test_can_get_communes_by_district()
    {
        $province = Gazetteer::factory()->create(['type' => 'Province']);
        $district = Gazetteer::factory()->create([
            'type' => 'District',
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

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/reference/gazetteers/communes/{$district->id}");

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
        $this->assertEquals($district->id, $response->json('data.0.parent_id'));
    }

    public function test_can_get_villages_by_commune()
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

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/reference/gazetteers/villages/{$commune->id}");

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
        $this->assertEquals($commune->id, $response->json('data.0.parent_id'));
    }

    public function test_can_validate_address()
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
            'parent_id' => $commune->id
        ]);

        $data = [
            'province_id' => $province->id,
            'district_id' => $district->id,
            'commune_id' => $commune->id,
            'village_id' => $village->id
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/reference/gazetteers/validate', $data);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'valid' => true
                ]
            ]);
    }

    public function test_validates_invalid_address_hierarchy()
    {
        $province1 = Gazetteer::factory()->create(['type' => 'Province']);
        $province2 = Gazetteer::factory()->create(['type' => 'Province']);
        $district = Gazetteer::factory()->create([
            'type' => 'District',
            'parent_id' => $province1->id
        ]);

        $data = [
            'province_id' => $province2->id, // Wrong province
            'district_id' => $district->id
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/reference/gazetteers/validate', $data);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'valid' => false
                ]
            ]);
    }

    public function test_can_search_gazetteers()
    {
        Gazetteer::factory()->create([
            'type' => 'Province',
            'name' => 'Phnom Penh'
        ]);
        Gazetteer::factory()->create([
            'type' => 'District',
            'name' => 'Phnom Penh District'
        ]);
        Gazetteer::factory()->create([
            'type' => 'Village',
            'name' => 'Siem Reap Village'
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/reference/gazetteers/search?q=Phnom');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_can_get_gazetteer_path()
    {
        $province = Gazetteer::factory()->create([
            'type' => 'Province',
            'name' => 'Test Province'
        ]);
        $district = Gazetteer::factory()->create([
            'type' => 'District',
            'name' => 'Test District',
            'parent_id' => $province->id
        ]);
        $commune = Gazetteer::factory()->create([
            'type' => 'Commune',
            'name' => 'Test Commune',
            'parent_id' => $district->id
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/reference/gazetteers/{$commune->id}/path");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'path' => [
                        '*' => [
                            'id',
                            'name',
                            'type'
                        ]
                    ]
                ]
            ]);

        $this->assertCount(3, $response->json('data.path')); // Province, District, Commune
    }

    public function test_can_search_addresses()
    {
        $patient = Patient::factory()->create();
        $province = Gazetteer::factory()->create([
            'type' => 'Province',
            'name' => 'Phnom Penh'
        ]);

        PatientAddress::factory()->create([
            'patient_id' => $patient->id,
            'province_id' => $province->id,
            'address_line_1' => '123 Main Street'
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/reference/gazetteers/addresses/search?q=Main');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'address_line_1',
                        'province',
                        'patient'
                    ]
                ]
            ]);
    }

    public function test_can_get_addresses_in_area()
    {
        $province = Gazetteer::factory()->create(['type' => 'Province']);
        $district = Gazetteer::factory()->create([
            'type' => 'District',
            'parent_id' => $province->id
        ]);

        $patient1 = Patient::factory()->create();
        $patient2 = Patient::factory()->create();

        PatientAddress::factory()->create([
            'patient_id' => $patient1->id,
            'province_id' => $province->id,
            'district_id' => $district->id
        ]);
        PatientAddress::factory()->create([
            'patient_id' => $patient2->id,
            'province_id' => $province->id,
            'district_id' => $district->id
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/reference/gazetteers/addresses/in-area?district_id={$district->id}");

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_can_get_address_statistics()
    {
        $province = Gazetteer::factory()->create(['type' => 'Province']);
        $patient = Patient::factory()->create();

        PatientAddress::factory()->count(3)->create([
            'patient_id' => $patient->id,
            'province_id' => $province->id
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/reference/gazetteers/addresses/statistics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'total_addresses',
                    'by_province',
                    'by_district'
                ]
            ]);
    }

    public function test_can_validate_patient_address()
    {
        $province = Gazetteer::factory()->create(['type' => 'Province']);
        $district = Gazetteer::factory()->create([
            'type' => 'District',
            'parent_id' => $province->id
        ]);

        $data = [
            'province_id' => $province->id,
            'district_id' => $district->id,
            'address_line_1' => '123 Test Street'
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/reference/gazetteers/addresses/validate', $data);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'valid',
                    'errors'
                ]
            ]);
    }

    public function test_returns_empty_for_nonexistent_parent()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/reference/gazetteers/districts/999');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }

    public function test_orders_results_alphabetically()
    {
        $province = Gazetteer::factory()->create(['type' => 'Province']);

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

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/reference/gazetteers/districts/{$province->id}");

        $response->assertStatus(200);
        $this->assertEquals('Alpha District', $response->json('data.0.name'));
        $this->assertEquals('Zebra District', $response->json('data.1.name'));
    }

    public function test_requires_authentication()
    {
        $response = $this->getJson('/api/v1/reference/gazetteers/provinces');

        $response->assertStatus(401);
    }
}