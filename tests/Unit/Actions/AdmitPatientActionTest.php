<?php

namespace Tests\Unit\Actions;

use App\Actions\AdmitPatientAction;
use App\Models\Patient;
use App\Models\Visit;
use App\Models\Encounter;
use App\Models\Facility;
use App\Models\Department;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdmitPatientActionTest extends TestCase
{
    use RefreshDatabase;

    private AdmitPatientAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new AdmitPatientAction();
    }

    public function test_creates_visit_with_admission_details()
    {
        $patient = Patient::factory()->create();
        $facility = Facility::factory()->create();

        $data = [
            'patient_id' => $patient->id,
            'facility_id' => $facility->id,
            'visit_type_id' => 1,
            'admission_type_id' => 1,
            'admitted_at' => now()
        ];

        $visit = $this->action->execute($data);

        $this->assertInstanceOf(Visit::class, $visit);
        $this->assertEquals($patient->id, $visit->patient_id);
        $this->assertEquals($facility->id, $visit->facility_id);
        $this->assertNotNull($visit->admitted_at);
        $this->assertNull($visit->discharged_at);
    }

    public function test_creates_initial_encounter()
    {
        $patient = Patient::factory()->create();
        $facility = Facility::factory()->create();
        $department = Department::factory()->create(['facility_id' => $facility->id]);

        $data = [
            'patient_id' => $patient->id,
            'facility_id' => $facility->id,
            'department_id' => $department->id,
            'visit_type_id' => 1,
            'admission_type_id' => 1,
            'admitted_at' => now()
        ];

        $visit = $this->action->execute($data);

        $this->assertCount(1, $visit->encounters);
        
        $encounter = $visit->encounters->first();
        $this->assertEquals($visit->id, $encounter->visit_id);
        $this->assertEquals($department->id, $encounter->department_id);
        $this->assertNotNull($encounter->started_at);
        $this->assertNull($encounter->ended_at);
    }

    public function test_assigns_room_if_provided()
    {
        $patient = Patient::factory()->create();
        $facility = Facility::factory()->create();
        $department = Department::factory()->create(['facility_id' => $facility->id]);
        $room = Room::factory()->create(['department_id' => $department->id]);

        $data = [
            'patient_id' => $patient->id,
            'facility_id' => $facility->id,
            'department_id' => $department->id,
            'room_id' => $room->id,
            'visit_type_id' => 1,
            'admission_type_id' => 1,
            'admitted_at' => now()
        ];

        $visit = $this->action->execute($data);

        $encounter = $visit->encounters->first();
        $this->assertEquals($room->id, $encounter->room_id);
    }

    public function test_sets_encounter_type_for_admission()
    {
        $patient = Patient::factory()->create();
        $facility = Facility::factory()->create();

        $data = [
            'patient_id' => $patient->id,
            'facility_id' => $facility->id,
            'visit_type_id' => 1,
            'admission_type_id' => 1,
            'admitted_at' => now()
        ];

        $visit = $this->action->execute($data);

        $encounter = $visit->encounters->first();
        $this->assertNotNull($encounter->encounter_type_id);
    }

    public function test_handles_emergency_admission()
    {
        $patient = Patient::factory()->create();
        $facility = Facility::factory()->create();

        $data = [
            'patient_id' => $patient->id,
            'facility_id' => $facility->id,
            'visit_type_id' => 3, // Emergency
            'admission_type_id' => 2, // Emergency admission
            'admitted_at' => now(),
            'chief_complaint' => 'Chest pain'
        ];

        $visit = $this->action->execute($data);

        $this->assertEquals(3, $visit->visit_type_id);
        $this->assertEquals(2, $visit->admission_type_id);
        
        $encounter = $visit->encounters->first();
        $this->assertNotNull($encounter);
    }

    public function test_handles_scheduled_admission()
    {
        $patient = Patient::factory()->create();
        $facility = Facility::factory()->create();

        $data = [
            'patient_id' => $patient->id,
            'facility_id' => $facility->id,
            'visit_type_id' => 2, // Inpatient
            'admission_type_id' => 1, // Scheduled
            'admitted_at' => now(),
            'scheduled_procedure' => 'Surgery'
        ];

        $visit = $this->action->execute($data);

        $this->assertEquals(2, $visit->visit_type_id);
        $this->assertEquals(1, $visit->admission_type_id);
    }

    public function test_generates_unique_visit_number()
    {
        $patient = Patient::factory()->create();
        $facility = Facility::factory()->create();

        $data = [
            'patient_id' => $patient->id,
            'facility_id' => $facility->id,
            'visit_type_id' => 1,
            'admission_type_id' => 1,
            'admitted_at' => now()
        ];

        $visit1 = $this->action->execute($data);
        $visit2 = $this->action->execute($data);

        $this->assertNotEquals($visit1->visit_number, $visit2->visit_number);
        $this->assertNotNull($visit1->visit_number);
        $this->assertNotNull($visit2->visit_number);
    }

    public function test_validates_required_fields()
    {
        $this->expectException(\InvalidArgumentException::class);

        $data = [
            'facility_id' => 1,
            'visit_type_id' => 1
            // Missing patient_id
        ];

        $this->action->execute($data);
    }
}