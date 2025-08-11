<?php

namespace App\Actions;

use App\Models\Encounter;
use App\Models\Observation;
use App\Models\ClinicalFormTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProcessFormSubmissionAction
{
    protected ValidateFormDataAction $validateFormDataAction;
    protected GenerateObservationsAction $generateObservationsAction;

    public function __construct()
    {
        $this->validateFormDataAction = new ValidateFormDataAction();
        $this->generateObservationsAction = new GenerateObservationsAction();
    }

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

            // Validate form data using dedicated action
            $validationResult = $this->validateFormDataAction->execute($formTemplate, $formData);
            $validatedData = $validationResult['validated_data'];

            // Generate observations using dedicated action
            $observationResult = $this->generateObservationsAction->execute(
                $formTemplate,
                $validatedData,
                $encounterId,
                $encounter->visit->patient_id
            );

            $observations = $observationResult['observations'];

            // Update encounter to mark form as completed if it was active
            if ($encounter->is_active && !$encounter->ended_at) {
                $encounter->update([
                    'ended_at' => now()
                ]);
            }

            // Track form completion
            $this->trackFormCompletion($encounter, $formTemplate, $validationResult, $observationResult);

            return [
                'encounter' => $encounter->fresh(['clinicalFormTemplate', 'observations']),
                'observations' => $observations,
                'form_data' => $formData,
                'validated_data' => $validatedData,
                'observations_created' => count($observations),
                'validation_summary' => [
                    'total_fields' => $validationResult['field_count'],
                    'validated_fields' => $validationResult['validated_field_count'],
                    'validation_rules_applied' => count($validationResult['validation_rules']),
                ],
                'observation_summary' => $observationResult['processing_summary'],
                'form_completion_tracked' => true,
            ];
        });
    }

    /**
     * Track form completion for clinical documentation workflow
     */
    private function trackFormCompletion(
        Encounter $encounter,
        ClinicalFormTemplate $formTemplate,
        array $validationResult,
        array $observationResult
    ): void {
        // This could be extended to create audit logs, update completion status,
        // trigger notifications, or integrate with clinical workflow systems
        
        // For now, we'll log the completion details
        \Log::info('Clinical form completed', [
            'encounter_id' => $encounter->id,
            'patient_id' => $encounter->visit->patient_id,
            'visit_id' => $encounter->visit_id,
            'form_template_id' => $formTemplate->id,
            'form_template_name' => $formTemplate->name,
            'form_category' => $formTemplate->category,
            'observations_created' => $observationResult['observations_count'],
            'completed_at' => now()->toISOString(),
            'completed_by' => auth()->id(),
        ]);
    }
}