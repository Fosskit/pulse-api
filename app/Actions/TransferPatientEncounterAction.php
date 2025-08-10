<?php

namespace App\Actions;

use App\Models\Visit;
use App\Models\Encounter;
use App\Models\Term;
use App\Models\Department;
use App\Models\Room;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TransferPatientEncounterAction
{
    public function execute(int $visitId, array $data): Encounter
    {
        return DB::transaction(function () use ($visitId, $data) {
            // Validate visit exists and is active
            $visit = Visit::findOrFail($visitId);
            
            if ($visit->discharged_at) {
                throw new \Exception('Cannot transfer discharged patient');
            }

            // Validate destination department/room if provided
            if (isset($data['destination_department_id'])) {
                $department = Department::findOrFail($data['destination_department_id']);
            }

            if (isset($data['destination_room_id'])) {
                $room = Room::findOrFail($data['destination_room_id']);
                
                // Validate room belongs to department if both are provided
                if (isset($data['destination_department_id']) && $room->department_id !== $data['destination_department_id']) {
                    throw new \Exception('Room does not belong to the specified department');
                }
            }

            // End any active encounters
            $activeEncounters = $visit->encounters()->whereNull('ended_at')->get();
            foreach ($activeEncounters as $encounter) {
                $encounter->update([
                    'ended_at' => $data['transfer_at'] ?? now()
                ]);
            }

            // Get transfer encounter type
            $transferEncounterType = Term::where('code', 'transfer')
                ->whereHas('terminology', function ($query) {
                    $query->where('name', 'encounter_types');
                })
                ->first();

            if (!$transferEncounterType) {
                throw new \Exception('Transfer encounter type not found');
            }

            // Create transfer encounter
            $transferEncounter = Encounter::create([
                'visit_id' => $visitId,
                'encounter_type_id' => $transferEncounterType->id,
                'encounter_form_id' => $data['encounter_form_id'] ?? 1,
                'is_new' => false,
                'started_at' => $data['transfer_at'] ?? now(),
                'ended_at' => $data['transfer_at'] ?? now(),
            ]);

            // Create new encounter for the destination
            if (isset($data['destination_encounter_type_id'])) {
                Encounter::create([
                    'visit_id' => $visitId,
                    'encounter_type_id' => $data['destination_encounter_type_id'],
                    'encounter_form_id' => $data['destination_encounter_form_id'] ?? 1,
                    'is_new' => false,
                    'started_at' => $data['transfer_at'] ?? now(),
                    'ended_at' => null,
                ]);
            }

            return $transferEncounter->load([
                'visit.patient',
                'encounterType',
                'observations'
            ]);
        });
    }
}