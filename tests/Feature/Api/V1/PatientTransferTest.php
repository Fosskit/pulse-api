<?php

namespace Tests\Feature\Api\V1;

use App\Models\Department;
use App\Models\Encounter;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\Room;
use App\Models\Term;
use App\Models\Terminology;
use App\Models\User;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PatientTransferTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create and authenticate a user
        $user = User::factory()->create();
        Sanctum::actingAs($user);
    }

    public function test_can_validate_patient_transfer()
    {
        // Create test data
        $facility = Facility::factory()->create();
        $department1 = Department::factory()->create(['facility_id' => $facility->id]);
        $department2 = Department::factory()->create(['facility_id' => $facility->id]);
        
        $terminology = Terminology::factory()->create();
        $roomType = Term::factory()->create(['terminology_id' => $terminology->id]);
        
        $sourceRoom = Room::factory()->create([
            'department_id' => $department1->id,
            'room_type_id' => $roomType->id
        ]);
        $destinationRoom = Room::factory()->create([
            'department_id' => $department2->id,
            'room_type_id' => $roomType->id
        ]);

        $patient = Patient::factory()->create(['facility_id' => $facility->id]);
        $visit = Visit::factory()->create([
            'patient_id' => $patient->id,
            'facility_id' => $facility->id,
            'admitted_at' => Carbon::now()->subHours(2),
            'discharged_at' => null
        ]);

        // Create an active encounter
        $encounter = Encounter::factory()->create([
            'visit_id' => $visit->id,
            'started_at' => Carbon::now()->subHours(1),
            'ended_at' => null
        ]);

        $response = $this->postJson('/api/v1/transfers/validate', [
            'visit_id' => $visit->id,
            'destination_room_id' => $destinationRoom->id
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'valid',
                        'errors',
                        'warnings',
                        'availability_check'
                    ]
                ]);
    }

    public function test_validates_transfer_request_data()
    {
        $response = $this->postJson('/api/v1/transfers/validate', [
            'visit_id' => 'invalid',
            'destination_room_id' => 'invalid'
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['visit_id', 'destination_room_id']);
    }

    public function test_validates_nonexistent_visit_for_transfer()
    {
        $facility = Facility::factory()->create();
        $department = Department::factory()->create(['facility_id' => $facility->id]);
        $terminology = Terminology::factory()->create();
        $roomType = Term::factory()->create(['terminology_id' => $terminology->id]);
        $room = Room::factory()->create([
            'department_id' => $department->id,
            'room_type_id' => $roomType->id
        ]);

        $response = $this->postJson('/api/v1/transfers/validate', [
            'visit_id' => 999,
            'destination_room_id' => $room->id
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['visit_id']);
    }

    public function test_validates_nonexistent_room_for_transfer()
    {
        $facility = Facility::factory()->create();
        $patient = Patient::factory()->create(['facility_id' => $facility->id]);
        $visit = Visit::factory()->create([
            'patient_id' => $patient->id,
            'facility_id' => $facility->id
        ]);

        $response = $this->postJson('/api/v1/transfers/validate', [
            'visit_id' => $visit->id,
            'destination_room_id' => 999
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['destination_room_id']);
    }
}