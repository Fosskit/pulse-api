<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MedicationAdministrationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'visit_id' => $this->visit_id,
            'medication_request_id' => $this->medication_request_id,
            'dose_given' => (float) $this->dose_given,
            'administered_at' => $this->administered_at,
            'notes' => $this->notes,
            'vital_signs_before' => $this->vital_signs_before,
            'vital_signs_after' => $this->vital_signs_after,
            'adverse_reactions' => $this->adverse_reactions,
            'is_administered' => $this->is_administered,
            'has_adverse_reactions' => $this->has_adverse_reactions,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relationships
            'visit' => $this->whenLoaded('visit', function () {
                return [
                    'id' => $this->visit->id,
                    'ulid' => $this->visit->ulid,
                    'patient_id' => $this->visit->patient_id,
                    'admitted_at' => $this->visit->admitted_at,
                    'discharged_at' => $this->visit->discharged_at,
                    'is_active' => $this->visit->is_active,
                    'patient' => $this->whenLoaded('visit.patient', function () {
                        return [
                            'id' => $this->visit->patient->id,
                            'code' => $this->visit->patient->code,
                            'full_name' => $this->visit->patient->full_name,
                            'age' => $this->visit->patient->age,
                            'sex' => $this->visit->patient->demographics?->sex,
                        ];
                    }),
                ];
            }),
            
            'medication_request' => $this->whenLoaded('medicationRequest', function () {
                return [
                    'id' => $this->medicationRequest->id,
                    'ulid' => $this->medicationRequest->ulid,
                    'quantity' => $this->medicationRequest->quantity,
                    'medication' => $this->whenLoaded('medicationRequest.medication', function () {
                        return [
                            'id' => $this->medicationRequest->medication->id,
                            'name' => $this->medicationRequest->medication->name,
                            'display_name' => $this->medicationRequest->medication->display_name,
                        ];
                    }),
                ];
            }),
            
            'status' => $this->whenLoaded('status', function () {
                return [
                    'id' => $this->status->id,
                    'name' => $this->status->name,
                    'display_name' => $this->status->display_name,
                ];
            }),
            
            'administrator' => $this->whenLoaded('administrator', function () {
                return [
                    'id' => $this->administrator->id,
                    'name' => $this->administrator->name,
                    'email' => $this->administrator->email,
                ];
            }),
            
            'dose_unit' => $this->whenLoaded('doseUnit', function () {
                return [
                    'id' => $this->doseUnit->id,
                    'name' => $this->doseUnit->name,
                    'display_name' => $this->doseUnit->display_name,
                ];
            }),
        ];
    }
}