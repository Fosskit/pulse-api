<?php

namespace App\Actions;

use App\Models\Visit;
use App\Models\Encounter;
use App\Models\Term;
use App\Models\Department;
use App\Models\Room;
use Illuminate\Support\Facades\DB;

class TransferPatientAction
{
    public function execute(int $visitId, array $transferData): array
    {
        return DB::transaction(function () use ($visitId, $transferData) {
            // Validate visit exists and is active
            $visit = Visit::with('patient')->findOrFail($visitId);
            
            if ($visit->discharged_at) {
                throw new \Exception('Cannot transfer discharged patient');
            }

            // Validate destination department and room
            $destinationDepartment = null;
            $destinationRoom = null;

            if (isset($transferData['destination_department_id'])) {
                $destinationDepartment = Department::findOrFail($transferData['destination_department_id']);
            }

            if (isset($transferData['destination_room_id'])) {
                $destinationRoom = Room::with('department')->findOrFail($transferData['destination_room_id']);
                
                // Validate room belongs to department if both are provided
                if ($destinationDepartment && $destinationRoom->department_id !== $destinationDepartment->id) {
                    throw new \Exception('Room does not belong to the specified department');
                }
                
                // Use room's department if department not explicitly provided
                if (!$destinationDepartment) {
                    $destinationDepartment = $destinationRoom->department;
                }
            }

            // Check room availability if room is specified
            if ($destinationRoom) {
                $roomAvailabilityAction = new CheckRoomAvailabilityAction();
                $transferTime = isset($transferData['transfer_at']) 
                    ? \Carbon\Carbon::parse($transferData['transfer_at'])
                    : now();
                    
                $availability = $roomAvailabilityAction->execute(
                    $destinationRoom->id, 
                    $transferTime,
                    $transferTime->copy()->addHours(1)
                );
                
                if (!$availability['available']) {
                    throw new \Exception('Destination room is not available: ' . $availability['reason']);
                }
            }

            $transferAt = $transferData['transfer_at'] ?? now();

            // End any active encounters
            $activeEncounters = $visit->encounters()->whereNull('ended_at')->get();
            foreach ($activeEncounters as $encounter) {
                $encounter->update([
                    'ended_at' => $transferAt
                ]);
            }

            // Get transfer encounter type
            $transferEncounterType = Term::where('code', 'transfer')
                ->whereHas('terminology', function ($query) {
                    $query->where('code', 'encounter_types');
                })
                ->first();

            if (!$transferEncounterType) {
                throw new \Exception('Transfer encounter type not found');
            }

            // Create transfer encounter
            $transferEncounter = Encounter::create([
                'visit_id' => $visitId,
                'encounter_type_id' => $transferEncounterType->id,
                'encounter_form_id' => $transferData['encounter_form_id'] ?? 1, // Default form ID
                'is_new' => false,
                'started_at' => $transferAt,
                'ended_at' => $transferAt,
            ]);

            // Create new encounter for the destination if encounter type is provided
            $destinationEncounter = null;
            if (isset($transferData['destination_encounter_type_id'])) {
                $destinationEncounter = Encounter::create([
                    'visit_id' => $visitId,
                    'encounter_type_id' => $transferData['destination_encounter_type_id'],
                    'encounter_form_id' => $transferData['destination_encounter_form_id'] ?? 1, // Default form ID
                    'is_new' => false,
                    'started_at' => $transferAt,
                    'ended_at' => null,
                ]);
            }

            // Load relationships for response
            $transferEncounter->load([
                'visit.patient',
                'encounterType',
                'clinicalFormTemplate',
                'observations'
            ]);

            if ($destinationEncounter) {
                $destinationEncounter->load([
                    'visit.patient',
                    'encounterType',
                    'clinicalFormTemplate',
                    'observations'
                ]);
            }

            return [
                'transfer_encounter' => $transferEncounter,
                'destination_encounter' => $destinationEncounter,
                'destination_department' => $destinationDepartment,
                'destination_room' => $destinationRoom,
                'transfer_at' => $transferAt,
                'reason' => $transferData['reason'] ?? null,
                'active_encounters_ended' => $activeEncounters->count(),
            ];
        });
    }
}