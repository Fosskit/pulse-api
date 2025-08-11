<?php

namespace App\Actions;

use App\Models\MedicationRequest;
use App\Models\Patient;
use App\Models\Term;
use Illuminate\Support\Collection;

class ValidateMedicationSafetyAction
{
    public function execute(int $patientId, int $medicationId, array $options = []): array
    {
        $patient = Patient::with(['visits.medicationRequests.medication'])->findOrFail($patientId);
        $medication = Term::findOrFail($medicationId);

        $warnings = [];
        $errors = [];

        // Check for known allergies (this would typically come from a patient allergies table)
        $allergyWarnings = $this->checkAllergies($patient, $medication);
        $warnings = array_merge($warnings, $allergyWarnings);

        // Check for drug interactions with current medications
        $interactionWarnings = $this->checkDrugInteractions($patient, $medication);
        $warnings = array_merge($warnings, $interactionWarnings);

        // Check for duplicate medications
        $duplicateWarnings = $this->checkDuplicateMedications($patient, $medication);
        $warnings = array_merge($warnings, $duplicateWarnings);

        // Check for contraindications based on patient demographics
        $contraindicationWarnings = $this->checkContraindications($patient, $medication);
        $warnings = array_merge($warnings, $contraindicationWarnings);

        return [
            'is_safe' => empty($errors),
            'has_warnings' => !empty($warnings),
            'errors' => $errors,
            'warnings' => $warnings,
            'patient_id' => $patientId,
            'medication_id' => $medicationId,
            'medication_name' => $medication->name,
            'checked_at' => now(),
        ];
    }

    private function checkAllergies(Patient $patient, Term $medication): array
    {
        $warnings = [];

        // This is a simplified implementation
        // In a real system, you would have a patient_allergies table
        // and a comprehensive drug allergy database
        
        $commonAllergens = [
            'penicillin' => ['amoxicillin', 'ampicillin', 'penicillin'],
            'sulfa' => ['sulfamethoxazole', 'trimethoprim'],
            'aspirin' => ['aspirin', 'acetylsalicylic acid'],
        ];

        $medicationName = strtolower($medication->name);
        
        foreach ($commonAllergens as $allergen => $medications) {
            if (in_array($medicationName, $medications)) {
                $warnings[] = [
                    'type' => 'allergy',
                    'severity' => 'high',
                    'message' => "Patient may be allergic to {$allergen}. Verify allergy history before administering {$medication->name}.",
                    'recommendation' => 'Check patient allergy history and consider alternative medication if allergy confirmed.',
                ];
            }
        }

        return $warnings;
    }

    private function checkDrugInteractions(Patient $patient, Term $medication): array
    {
        $warnings = [];

        // Get current active medications for the patient
        $currentMedications = $this->getCurrentMedications($patient);
        
        // This is a simplified implementation
        // In a real system, you would have a comprehensive drug interaction database
        $knownInteractions = [
            'warfarin' => [
                'aspirin' => 'Increased bleeding risk',
                'ibuprofen' => 'Increased bleeding risk',
            ],
            'digoxin' => [
                'furosemide' => 'Increased risk of digoxin toxicity due to potassium loss',
            ],
            'metformin' => [
                'contrast dye' => 'Risk of lactic acidosis',
            ],
        ];

        $medicationName = strtolower($medication->name);

        foreach ($currentMedications as $currentMed) {
            $currentMedName = strtolower($currentMed);
            
            // Check both directions of interaction
            if (isset($knownInteractions[$medicationName][$currentMedName])) {
                $warnings[] = [
                    'type' => 'drug_interaction',
                    'severity' => 'medium',
                    'message' => "Potential interaction between {$medication->name} and {$currentMed}: {$knownInteractions[$medicationName][$currentMedName]}",
                    'recommendation' => 'Monitor patient closely and consider dose adjustment or alternative medication.',
                ];
            } elseif (isset($knownInteractions[$currentMedName][$medicationName])) {
                $warnings[] = [
                    'type' => 'drug_interaction',
                    'severity' => 'medium',
                    'message' => "Potential interaction between {$currentMed} and {$medication->name}: {$knownInteractions[$currentMedName][$medicationName]}",
                    'recommendation' => 'Monitor patient closely and consider dose adjustment or alternative medication.',
                ];
            }
        }

        return $warnings;
    }

    private function checkDuplicateMedications(Patient $patient, Term $medication): array
    {
        $warnings = [];
        $currentMedications = $this->getCurrentMedications($patient);
        
        if (in_array($medication->name, $currentMedications)) {
            $warnings[] = [
                'type' => 'duplicate_medication',
                'severity' => 'medium',
                'message' => "Patient is already prescribed {$medication->name}.",
                'recommendation' => 'Verify if additional prescription is intended or if existing prescription should be modified.',
            ];
        }

        return $warnings;
    }

    private function checkContraindications(Patient $patient, Term $medication): array
    {
        $warnings = [];

        // This is a simplified implementation
        // In a real system, you would check against patient conditions, age, pregnancy status, etc.
        
        $age = $patient->age ?? 0;
        $medicationName = strtolower($medication->name);

        // Age-based contraindications
        if ($age < 18) {
            $pediatricContraindications = ['aspirin', 'tetracycline', 'fluoroquinolones'];
            if (in_array($medicationName, $pediatricContraindications)) {
                $warnings[] = [
                    'type' => 'age_contraindication',
                    'severity' => 'high',
                    'message' => "{$medication->name} is not recommended for patients under 18 years old.",
                    'recommendation' => 'Consider pediatric-appropriate alternative medication.',
                ];
            }
        }

        if ($age > 65) {
            $geriatricCautions = ['benzodiazepines', 'anticholinergics'];
            foreach ($geriatricCautions as $caution) {
                if (strpos($medicationName, $caution) !== false) {
                    $warnings[] = [
                        'type' => 'geriatric_caution',
                        'severity' => 'medium',
                        'message' => "{$medication->name} requires caution in elderly patients.",
                        'recommendation' => 'Consider lower starting dose and monitor closely for adverse effects.',
                    ];
                }
            }
        }

        return $warnings;
    }

    private function getCurrentMedications(Patient $patient): array
    {
        $medications = [];

        foreach ($patient->visits as $visit) {
            if ($visit->is_active) {
                foreach ($visit->medicationRequests as $request) {
                    if ($request->medication) {
                        $medications[] = $request->medication->name;
                    }
                }
            }
        }

        return array_unique($medications);
    }
}