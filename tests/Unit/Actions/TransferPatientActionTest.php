<?php

namespace Tests\Unit\Actions;

use App\Actions\TransferPatientAction;
use App\Models\Visit;
use App\Models\Encounter;
use App\Models\Patient;
use App\Models\Department;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransferPatientActionTest extends TestCase
{
    use RefreshDatabase;

    private TransferPatientAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new TransferPatientAction();
    }

    public function test_creates_transfer_encounter()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $fromDepartment = Department::factory()->create();
        $toDepartment = Department::factory()->create();

        // Create current encounter
        $currentEncounter = Encounter::factory()->create([
            'visit_id' => $visit->id,
            'department_id' => $fromDepartment->id,
            'started_at' => now()->subHours(2),
            'ended_at' => null
        ]);

        $data = [
            'visit_id' => $visit->id,
            'from_department_id' => $fromDepartment->id,
            'to_department_id' => $toDepartment->id,
            'transfer_reason' => 'Requires specialized care',
            'transferred_at' => now()
        ];

        $transferEncounter = $this->action->execute($data);

        $this->assertInstanceOf(Encounter::class, $transferEncounter);
        $this->assertEquals($visit->id, $transferEncounter->visit_id);
        $this->assertEquals($toDepartment->id, $transferEncounter->department_id);
        $this->assertNotNull($transferEncounter->started_at);
    }

    public function test_ends_current_encounter()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $fromDepartment = Department::factory()->create();
        $toDepartment = Department::factory()->create();

        $currentEncounter = Encounter::factory()->create([
            'visit_id' => $visit->id,
            'department_id' => $fromDepartment->id,
            'started_at' => now()->subHours(3),
            'ended_at' => null
        ]);

        $data = [
            'visit_id' => $visit->id,
            'from_department_id' => $fromDepartment->id,
            'to_department_id' => $toDepartment->id,
            'transfer_reason' => 'Patient condition improved',
            'transferred_at' => now()
        ];

        $transferEncounter = $this->action->execute($data);

        $currentEncounter->refresh();
        $this->assertNotNull($currentEncounter->ended_at);
    }

    public function test_transfers_to_specific_room()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $fromDepartment = Department::factory()->create();
        $toDepartment = Department::factory()->create();
        $toRoom = Room::factory()->create(['department_id' => $toDepartment->id]);

        $currentEncounter = Encounter::factory()->create([
            'visit_id' => $visit->id,
            'department_id' => $fromDepartment->id,
            'ended_at' => null
        ]);

        $data = [
            'visit_id' => $visit->id,
            'from_department_id' => $fromDepartment->id,
            'to_department_id' => $toDepartment->id,
            'to_room_id' => $toRoom->id,
            'transfer_reason' => 'Room upgrade',
            'transferred_at' => now()
        ];

        $transferEncounter = $this->action->execute($data);

        $this->assertEquals($toRoom->id, $transferEncounter->room_id);
    }

    public function test_validates_room_belongs_to_department()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $fromDepartment = Department::factory()->create();
        $toDepartment = Department::factory()->create();
        $wrongRoom = Room::factory()->create(['department_id' => $fromDepartment->id]);

        $this->expectException(\InvalidArgumentException::class);

        $data = [
            'visit_id' => $visit->id,
            'from_department_id' => $fromDepartment->id,
            'to_department_id' => $toDepartment->id,
            'to_room_id' => $wrongRoom->id, // Room belongs to from_department, not to_department
            'transfer_reason' => 'Invalid room assignment',
            'transferred_at' => now()
        ];

        $this->action->execute($data);
    }

    public function test_handles_emergency_transfer()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $fromDepartment = Department::factory()->create();
        $toDepartment = Department::factory()->create();

        $currentEncounter = Encounter::factory()->create([
            'visit_id' => $visit->id,
            'department_id' => $fromDepartment->id,
            'ended_at' => null
        ]);

        $data = [
            'visit_id' => $visit->id,
            'from_department_id' => $fromDepartment->id,
            'to_department_id' => $toDepartment->id,
            'transfer_reason' => 'Emergency - patient deteriorating',
            'transfer_type' => 'emergency',
            'transferred_at' => now()
        ];

        $transferEncounter = $this->action->execute($data);

        $this->assertEquals($toDepartment->id, $transferEncounter->department_id);
    }

    public function test_handles_scheduled_transfer()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $fromDepartment = Department::factory()->create();
        $toDepartment = Department::factory()->create();

        $currentEncounter = Encounter::factory()->create([
            'visit_id' => $visit->id,
            'department_id' => $fromDepartment->id,
            'ended_at' => null
        ]);

        $data = [
            'visit_id' => $visit->id,
            'from_department_id' => $fromDepartment->id,
            'to_department_id' => $toDepartment->id,
            'transfer_reason' => 'Scheduled for surgery',
            'transfer_type' => 'scheduled',
            'transferred_at' => now()->addHour()
        ];

        $transferEncounter = $this->action->execute($data);

        $this->assertEquals($toDepartment->id, $transferEncounter->department_id);
    }

    public function test_validates_different_departments()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $department = Department::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        $data = [
            'visit_id' => $visit->id,
            'from_department_id' => $department->id,
            'to_department_id' => $department->id, // Same department
            'transfer_reason' => 'Invalid transfer',
            'transferred_at' => now()
        ];

        $this->action->execute($data);
    }

    public function test_validates_active_encounter_exists()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $fromDepartment = Department::factory()->create();
        $toDepartment = Department::factory()->create();

        // No active encounter exists
        $this->expectException(\InvalidArgumentException::class);

        $data = [
            'visit_id' => $visit->id,
            'from_department_id' => $fromDepartment->id,
            'to_department_id' => $toDepartment->id,
            'transfer_reason' => 'No active encounter',
            'transferred_at' => now()
        ];

        $this->action->execute($data);
    }

    public function test_records_transfer_details()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $fromDepartment = Department::factory()->create();
        $toDepartment = Department::factory()->create();

        $currentEncounter = Encounter::factory()->create([
            'visit_id' => $visit->id,
            'department_id' => $fromDepartment->id,
            'ended_at' => null
        ]);

        $transferTime = now();
        $data = [
            'visit_id' => $visit->id,
            'from_department_id' => $fromDepartment->id,
            'to_department_id' => $toDepartment->id,
            'transfer_reason' => 'Patient requires intensive care',
            'transferred_by' => 'Dr. Smith',
            'transferred_at' => $transferTime
        ];

        $transferEncounter = $this->action->execute($data);

        $this->assertEquals($transferTime->format('Y-m-d H:i:s'), $transferEncounter->started_at->format('Y-m-d H:i:s'));
    }
}