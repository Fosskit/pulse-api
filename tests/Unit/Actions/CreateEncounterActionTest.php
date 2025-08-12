<?php

namespace Tests\Unit\Actions;

use App\Actions\CreateEncounterAction;
use App\Models\Visit;
use App\Models\Encounter;
use App\Models\Patient;
use App\Models\Department;
use App\Models\Room;
use App\Models\ClinicalFormTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateEncounterActionTest extends TestCase
{
    use RefreshDatabase;

    private CreateEncounterAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new CreateEncounterAction();
    }

    public function test_creates_encounter_with_basic_details()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $department = Department::factory()->create();

        $data = [
            'visit_id' => $visit->id,
            'department_id' => $department->id,
            'encounter_type_id' => 1,
            'started_at' => now()
        ];

        $encounter = $this->action->execute($data);

        $this->assertInstanceOf(Encounter::class, $encounter);
        $this->assertEquals($visit->id, $encounter->visit_id);
        $this->assertEquals($department->id, $encounter->department_id);
        $this->assertEquals(1, $encounter->encounter_type_id);
        $this->assertNotNull($encounter->started_at);
        $this->assertNull($encounter->ended_at);
    }

    public function test_creates_encounter_with_room_assignment()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $department = Department::factory()->create();
        $room = Room::factory()->create(['department_id' => $department->id]);

        $data = [
            'visit_id' => $visit->id,
            'department_id' => $department->id,
            'room_id' => $room->id,
            'encounter_type_id' => 1,
            'started_at' => now()
        ];

        $encounter = $this->action->execute($data);

        $this->assertEquals($room->id, $encounter->room_id);
    }

    public function test_creates_encounter_with_clinical_form()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $department = Department::factory()->create();
        $formTemplate = ClinicalFormTemplate::factory()->create();

        $data = [
            'visit_id' => $visit->id,
            'department_id' => $department->id,
            'encounter_type_id' => 1,
            'encounter_form_id' => $formTemplate->id,
            'started_at' => now()
        ];

        $encounter = $this->action->execute($data);

        $this->assertEquals($formTemplate->id, $encounter->encounter_form_id);
    }

    public function test_creates_outpatient_encounter()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $department = Department::factory()->create();

        $data = [
            'visit_id' => $visit->id,
            'department_id' => $department->id,
            'encounter_type_id' => 1, // Outpatient
            'started_at' => now(),
            'chief_complaint' => 'Routine checkup'
        ];

        $encounter = $this->action->execute($data);

        $this->assertEquals(1, $encounter->encounter_type_id);
    }

    public function test_creates_emergency_encounter()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $department = Department::factory()->create();

        $data = [
            'visit_id' => $visit->id,
            'department_id' => $department->id,
            'encounter_type_id' => 3, // Emergency
            'started_at' => now(),
            'chief_complaint' => 'Severe chest pain',
            'triage_level' => 'urgent'
        ];

        $encounter = $this->action->execute($data);

        $this->assertEquals(3, $encounter->encounter_type_id);
    }

    public function test_creates_surgery_encounter()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $department = Department::factory()->create();

        $data = [
            'visit_id' => $visit->id,
            'department_id' => $department->id,
            'encounter_type_id' => 4, // Surgery
            'started_at' => now(),
            'procedure_name' => 'Appendectomy'
        ];

        $encounter = $this->action->execute($data);

        $this->assertEquals(4, $encounter->encounter_type_id);
    }

    public function test_ends_previous_encounter_when_creating_new_one()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $department = Department::factory()->create();

        // Create first encounter
        $firstEncounter = Encounter::factory()->create([
            'visit_id' => $visit->id,
            'started_at' => now()->subHour(),
            'ended_at' => null
        ]);

        $data = [
            'visit_id' => $visit->id,
            'department_id' => $department->id,
            'encounter_type_id' => 2,
            'started_at' => now()
        ];

        $secondEncounter = $this->action->execute($data);

        $firstEncounter->refresh();
        $this->assertNotNull($firstEncounter->ended_at);
        $this->assertNull($secondEncounter->ended_at);
    }

    public function test_validates_required_fields()
    {
        $this->expectException(\InvalidArgumentException::class);

        $data = [
            'department_id' => 1,
            'encounter_type_id' => 1
            // Missing visit_id
        ];

        $this->action->execute($data);
    }

    public function test_validates_visit_exists()
    {
        $department = Department::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        $data = [
            'visit_id' => 999, // Non-existent visit
            'department_id' => $department->id,
            'encounter_type_id' => 1,
            'started_at' => now()
        ];

        $this->action->execute($data);
    }

    public function test_sets_encounter_status()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $department = Department::factory()->create();

        $data = [
            'visit_id' => $visit->id,
            'department_id' => $department->id,
            'encounter_type_id' => 1,
            'started_at' => now(),
            'encounter_status_id' => 1 // Active
        ];

        $encounter = $this->action->execute($data);

        $this->assertEquals(1, $encounter->encounter_status_id);
    }
}