<?php

namespace App\Actions;

use App\Models\Patient;
use App\Models\Visit;
use App\Models\Encounter;
use App\Models\Term;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdmitPatientAction
{
    public function execute(array $data): Visit
    {
        return DB::transaction(function () use ($data) {
            // Validate patient exists
            $patient = Patient::findOrFail($data['patient_id']);
            
            // Check if patient has an active visit
            $activeVisit = $patient->visits()->whereNull('discharged_at')->first();
            if ($activeVisit) {
                throw new \Exception('Patient already has an active visit');
            }

            // Create the visit record
            $visit = Visit::create([
                'patient_id' => $data['patient_id'],
                'facility_id' => $data['facility_id'],
                'visit_type_id' => $data['visit_type_id'],
                'admission_type_id' => $data['admission_type_id'],
                'admitted_at' => $data['admitted_at'] ?? now(),
            ]);

            // Create initial admission encounter
            $admissionEncounterType = Term::where('code', 'admission')
                ->whereHas('terminology', function ($query) {
                    $query->where('name', 'encounter_types');
                })
                ->first();

            if ($admissionEncounterType) {
                Encounter::create([
                    'visit_id' => $visit->id,
                    'encounter_type_id' => $admissionEncounterType->id,
                    'encounter_form_id' => 1, // Default form ID - should be configurable
                    'started_at' => $visit->admitted_at,
                    'ended_at' => $visit->admitted_at,
                ]);
            }

            return $visit->load([
                'patient',
                'facility',
                'visitType',
                'admissionType',
                'encounters.encounterType'
            ]);
        });
    }
}