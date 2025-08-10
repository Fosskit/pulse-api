<?php

namespace App\Actions;

use App\Models\Visit;
use App\Models\Encounter;
use App\Models\Term;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DischargePatientAction
{
    public function execute(int $visitId, array $data): Visit
    {
        return DB::transaction(function () use ($visitId, $data) {
            // Find the visit
            $visit = Visit::findOrFail($visitId);
            
            // Check if visit is already discharged
            if ($visit->discharged_at) {
                throw new \Exception('Patient is already discharged');
            }

            // Update visit with discharge information
            $visit->update([
                'discharged_at' => $data['discharged_at'] ?? now(),
                'discharge_type_id' => $data['discharge_type_id'],
                'visit_outcome_id' => $data['visit_outcome_id'] ?? null,
            ]);

            // End any active encounters
            $activeEncounters = $visit->encounters()->whereNull('ended_at')->get();
            foreach ($activeEncounters as $encounter) {
                $encounter->update([
                    'ended_at' => $visit->discharged_at
                ]);
            }

            // Create discharge encounter
            $dischargeEncounterType = Term::where('code', 'discharge')
                ->whereHas('terminology', function ($query) {
                    $query->where('name', 'encounter_types');
                })
                ->first();

            if ($dischargeEncounterType) {
                Encounter::create([
                    'visit_id' => $visit->id,
                    'encounter_type_id' => $dischargeEncounterType->id,
                    'encounter_form_id' => 1, // Default form ID - should be configurable
                    'started_at' => $visit->discharged_at,
                    'ended_at' => $visit->discharged_at,
                ]);
            }

            return $visit->load([
                'patient',
                'facility',
                'visitType',
                'admissionType',
                'dischargeType',
                'visitOutcome',
                'encounters.encounterType'
            ]);
        });
    }
}