<?php

namespace Tests\Unit\Actions;

use App\Actions\SearchPatientsAction;
use App\Models\Patient;
use App\Models\PatientDemographic;
use App\Models\PatientIdentity;
use App\Models\Card;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchPatientsActionTest extends TestCase
{
    use RefreshDatabase;

    private SearchPatientsAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new SearchPatientsAction();
    }

    public function test_searches_by_patient_code()
    {
        $patient1 = Patient::factory()->create(['code' => 'PAT001']);
        $patient2 = Patient::factory()->create(['code' => 'PAT002']);

        $results = $this->action->execute(['code' => 'PAT001']);

        $this->assertCount(1, $results);
        $this->assertEquals($patient1->id, $results->first()->id);
    }

    public function test_searches_by_first_name()
    {
        $patient1 = Patient::factory()->create();
        $patient2 = Patient::factory()->create();
        
        PatientDemographic::factory()->create([
            'patient_id' => $patient1->id,
            'first_name' => 'John'
        ]);
        PatientDemographic::factory()->create([
            'patient_id' => $patient2->id,
            'first_name' => 'Jane'
        ]);

        $results = $this->action->execute(['first_name' => 'John']);

        $this->assertCount(1, $results);
        $this->assertEquals($patient1->id, $results->first()->id);
    }

    public function test_searches_by_last_name()
    {
        $patient1 = Patient::factory()->create();
        $patient2 = Patient::factory()->create();
        
        PatientDemographic::factory()->create([
            'patient_id' => $patient1->id,
            'last_name' => 'Smith'
        ]);
        PatientDemographic::factory()->create([
            'patient_id' => $patient2->id,
            'last_name' => 'Johnson'
        ]);

        $results = $this->action->execute(['last_name' => 'Smith']);

        $this->assertCount(1, $results);
        $this->assertEquals($patient1->id, $results->first()->id);
    }

    public function test_searches_by_phone()
    {
        $patient1 = Patient::factory()->create();
        $patient2 = Patient::factory()->create();
        
        PatientDemographic::factory()->create([
            'patient_id' => $patient1->id,
            'phone' => '123456789'
        ]);
        PatientDemographic::factory()->create([
            'patient_id' => $patient2->id,
            'phone' => '987654321'
        ]);

        $results = $this->action->execute(['phone' => '123456789']);

        $this->assertCount(1, $results);
        $this->assertEquals($patient1->id, $results->first()->id);
    }

    public function test_searches_by_identity_code()
    {
        $patient1 = Patient::factory()->create();
        $patient2 = Patient::factory()->create();
        $card = Card::factory()->create();
        
        PatientIdentity::factory()->create([
            'patient_id' => $patient1->id,
            'card_id' => $card->id,
            'identity_code' => 'INS123456'
        ]);
        PatientIdentity::factory()->create([
            'patient_id' => $patient2->id,
            'card_id' => $card->id,
            'identity_code' => 'INS789012'
        ]);

        $results = $this->action->execute(['identity_code' => 'INS123456']);

        $this->assertCount(1, $results);
        $this->assertEquals($patient1->id, $results->first()->id);
    }

    public function test_searches_by_date_of_birth()
    {
        $patient1 = Patient::factory()->create();
        $patient2 = Patient::factory()->create();
        
        PatientDemographic::factory()->create([
            'patient_id' => $patient1->id,
            'date_of_birth' => '1990-01-01'
        ]);
        PatientDemographic::factory()->create([
            'patient_id' => $patient2->id,
            'date_of_birth' => '1985-05-15'
        ]);

        $results = $this->action->execute(['date_of_birth' => '1990-01-01']);

        $this->assertCount(1, $results);
        $this->assertEquals($patient1->id, $results->first()->id);
    }

    public function test_combines_multiple_search_criteria()
    {
        $patient1 = Patient::factory()->create();
        $patient2 = Patient::factory()->create();
        
        PatientDemographic::factory()->create([
            'patient_id' => $patient1->id,
            'first_name' => 'John',
            'last_name' => 'Smith'
        ]);
        PatientDemographic::factory()->create([
            'patient_id' => $patient2->id,
            'first_name' => 'John',
            'last_name' => 'Johnson'
        ]);

        $results = $this->action->execute([
            'first_name' => 'John',
            'last_name' => 'Smith'
        ]);

        $this->assertCount(1, $results);
        $this->assertEquals($patient1->id, $results->first()->id);
    }

    public function test_returns_empty_collection_when_no_matches()
    {
        Patient::factory()->create(['code' => 'PAT001']);

        $results = $this->action->execute(['code' => 'NONEXISTENT']);

        $this->assertCount(0, $results);
    }

    public function test_handles_partial_name_matching()
    {
        $patient = Patient::factory()->create();
        
        PatientDemographic::factory()->create([
            'patient_id' => $patient->id,
            'first_name' => 'Jonathan'
        ]);

        $results = $this->action->execute(['first_name' => 'John']);

        $this->assertCount(1, $results);
        $this->assertEquals($patient->id, $results->first()->id);
    }

    public function test_handles_case_insensitive_search()
    {
        $patient = Patient::factory()->create();
        
        PatientDemographic::factory()->create([
            'patient_id' => $patient->id,
            'first_name' => 'John'
        ]);

        $results = $this->action->execute(['first_name' => 'john']);

        $this->assertCount(1, $results);
        $this->assertEquals($patient->id, $results->first()->id);
    }
}