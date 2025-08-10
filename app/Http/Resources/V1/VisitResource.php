<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VisitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'facility_id' => $this->facility_id,
            'visit_type_id' => $this->visit_type_id,
            'admission_type_id' => $this->admission_type_id,
            'admitted_at' => $this->admitted_at?->toISOString(),
            'discharged_at' => $this->discharged_at?->toISOString(),
            'discharge_type_id' => $this->discharge_type_id,
            'visit_outcome_id' => $this->visit_outcome_id,
            'is_active' => $this->is_active,
            'duration_days' => $this->duration,
            
            // Relationships
            'patient' => $this->whenLoaded('patient', function () {
                return new PatientResource($this->patient);
            }),
            
            'facility' => $this->whenLoaded('facility', function () {
                return [
                    'id' => $this->facility->id,
                    'code' => $this->facility->code,
                ];
            }),
            
            'visit_type' => $this->whenLoaded('visitType', function () {
                return [
                    'id' => $this->visitType->id,
                    'code' => $this->visitType->code,
                    'name' => $this->visitType->name,
                ];
            }),
            
            'admission_type' => $this->whenLoaded('admissionType', function () {
                return [
                    'id' => $this->admissionType->id,
                    'code' => $this->admissionType->code,
                    'name' => $this->admissionType->name,
                ];
            }),
            
            'discharge_type' => $this->whenLoaded('dischargeType', function () {
                return [
                    'id' => $this->dischargeType->id,
                    'code' => $this->dischargeType->code,
                    'name' => $this->dischargeType->name,
                ];
            }),
            
            'visit_outcome' => $this->whenLoaded('visitOutcome', function () {
                return [
                    'id' => $this->visitOutcome->id,
                    'code' => $this->visitOutcome->code,
                    'name' => $this->visitOutcome->name,
                ];
            }),
            
            'encounters' => $this->whenLoaded('encounters', function () {
                return EncounterResource::collection($this->encounters);
            }),
            
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}