<?php

namespace App\Actions;

use App\Models\Encounter;
use App\Models\Observation;
use App\Models\ClinicalFormTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProcessFormSubmissionAction
{
    public function execute(int $encounterId, array $formData): array
    {
        return DB::transaction(function () use ($encounterId, $formData) {
            // Get the encounter with its clinical form template
            $encounter = Encounter::with('clinicalFormTemplate', 'visit.patient')
                ->findOrFail($encounterId);

            if (!$encounter->clinicalFormTemplate) {
                throw new \Exception('No clinical form template associated with this encounter');
            }

            if ($encounter->visit->discharged_at) {
                throw new \Exception('Cannot submit form for discharged patient');
            }

            $formTemplate = $encounter->clinicalFormTemplate;

            // Validate form data against schema
            $validationRules = $formTemplate->getValidationRules();
            if (!empty($validationRules)) {
                $validator = Validator::make($formData, $validationRules);
                if ($validator->fails()) {
                    throw new \Exception('Form validation failed: ' . implode(', ', $validator->errors()->all()));
                }
            }

            // Generate observations from form data
            $observationData = $formTemplate->generateObservations(
                $formData,
                $encounterId,
                $encounter->visit->patient_id
            );

            // Create observations
            $observations = [];
            foreach ($observationData as $data) {
                $observations[] = Observation::create($data);
            }

            // Update encounter to mark form as completed if it was active
            if ($encounter->is_active && !$encounter->ended_at) {
                $encounter->update([
                    'ended_at' => now()
                ]);
            }

            return [
                'encounter' => $encounter->fresh(['clinicalFormTemplate', 'observations']),
                'observations' => $observations,
                'form_data' => $formData,
                'observations_created' => count($observations)
            ];
        });
    }
}