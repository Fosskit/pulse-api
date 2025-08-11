<?php

namespace App\Actions;

use App\Models\MedicationAdministration;
use App\Models\MedicationRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordMedicationAdministrationAction
{
    public function execute(array $data): MedicationAdministration
    {
        return DB::transaction(function () use ($data) {
            // Validate medication request exists
            $medicationRequest = MedicationRequest::with(['visit', 'instruction'])
                ->findOrFail($data['medication_request_id']);
            
            if (!$medicationRequest->visit->is_active) {
                throw ValidationException::withMessages([
                    'medication_request_id' => ['Cannot administer medication for discharged visit.']
                ]);
            }

            // Validate dose is within reasonable limits based on instruction
            $instruction = $medicationRequest->instruction;
            $maxSingleDose = max(
                $instruction->morning,
                $instruction->afternoon,
                $instruction->evening,
                $instruction->night
            );

            if ($data['dose_given'] > ($maxSingleDose * 2)) {
                throw ValidationException::withMessages([
                    'dose_given' => ["Dose given ({$data['dose_given']}) exceeds safe limits based on prescription."]
                ]);
            }

            // Create medication administration record
            $administration = MedicationAdministration::create([
                'visit_id' => $medicationRequest->visit_id,
                'medication_request_id' => $data['medication_request_id'],
                'status_id' => $data['status_id'],
                'administrator_id' => $data['administrator_id'],
                'dose_given' => $data['dose_given'],
                'dose_unit_id' => $data['dose_unit_id'],
                'administered_at' => $data['administered_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
                'vital_signs_before' => $data['vital_signs_before'] ?? null,
                'vital_signs_after' => $data['vital_signs_after'] ?? null,
                'adverse_reactions' => $data['adverse_reactions'] ?? null,
            ]);

            return $administration->load([
                'visit.patient',
                'medicationRequest.medication',
                'status',
                'administrator',
                'doseUnit'
            ]);
        });
    }

    public function validateData(array $data): array
    {
        $rules = [
            'medication_request_id' => 'required|exists:medication_requests,id',
            'status_id' => 'required|exists:terms,id',
            'administrator_id' => 'required|exists:users,id',
            'dose_given' => 'required|numeric|min:0.01|max:999.99',
            'dose_unit_id' => 'required|exists:terms,id',
            'administered_at' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
            'vital_signs_before' => 'nullable|array',
            'vital_signs_before.temperature' => 'nullable|numeric|min:30|max:50',
            'vital_signs_before.blood_pressure_systolic' => 'nullable|integer|min:50|max:300',
            'vital_signs_before.blood_pressure_diastolic' => 'nullable|integer|min:30|max:200',
            'vital_signs_before.heart_rate' => 'nullable|integer|min:30|max:250',
            'vital_signs_before.respiratory_rate' => 'nullable|integer|min:5|max:60',
            'vital_signs_before.oxygen_saturation' => 'nullable|integer|min:50|max:100',
            'vital_signs_after' => 'nullable|array',
            'vital_signs_after.temperature' => 'nullable|numeric|min:30|max:50',
            'vital_signs_after.blood_pressure_systolic' => 'nullable|integer|min:50|max:300',
            'vital_signs_after.blood_pressure_diastolic' => 'nullable|integer|min:30|max:200',
            'vital_signs_after.heart_rate' => 'nullable|integer|min:30|max:250',
            'vital_signs_after.respiratory_rate' => 'nullable|integer|min:5|max:60',
            'vital_signs_after.oxygen_saturation' => 'nullable|integer|min:50|max:100',
            'adverse_reactions' => 'nullable|string|max:2000',
        ];

        return validator($data, $rules)->validate();
    }
}