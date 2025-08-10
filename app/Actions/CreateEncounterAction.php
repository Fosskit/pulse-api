<?php

namespace App\Actions;

use App\Models\Visit;
use App\Models\Encounter;
use App\Models\Term;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CreateEncounterAction
{
    public function execute(int $visitId, array $data): Encounter
    {
        return DB::transaction(function () use ($visitId, $data) {
            // Validate visit exists and is active
            $visit = Visit::findOrFail($visitId);
            
            if ($visit->discharged_at) {
                throw new \Exception('Cannot create encounter for discharged patient');
            }

            // Create the encounter
            $encounter = Encounter::create([
                'visit_id' => $visitId,
                'encounter_type_id' => $data['encounter_type_id'],
                'encounter_form_id' => $data['encounter_form_id'] ?? 1,
                'is_new' => $data['is_new'] ?? false,
                'started_at' => $data['started_at'] ?? now(),
                'ended_at' => $data['ended_at'] ?? null,
            ]);

            return $encounter->load([
                'visit.patient',
                'encounterType',
                'clinicalFormTemplate',
                'observations'
            ]);
        });
    }
}