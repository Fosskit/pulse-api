<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MedicationRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'visit_id' => $this->visit_id,
            'quantity' => $this->quantity,
            'total_dispensed' => $this->total_dispensed,
            'remaining_quantity' => $this->remaining_quantity,
            'is_fully_dispensed' => $this->is_fully_dispensed,
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
                            'demographics' => $this->whenLoaded('visit.patient.demographics', function () {
                                return [
                                    'name' => $this->visit->patient->demographics->name,
                                    'birthdate' => $this->visit->patient->demographics->birthdate,
                                    'sex' => $this->visit->patient->demographics->sex,
                                    'telephone' => $this->visit->patient->demographics->telephone,
                                ];
                            }),
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
            
            'intent' => $this->whenLoaded('intent', function () {
                return [
                    'id' => $this->intent->id,
                    'name' => $this->intent->name,
                    'display_name' => $this->intent->display_name,
                ];
            }),
            
            'medication' => $this->whenLoaded('medication', function () {
                return [
                    'id' => $this->medication->id,
                    'name' => $this->medication->name,
                    'display_name' => $this->medication->display_name,
                ];
            }),
            
            'requester' => $this->whenLoaded('requester', function () {
                return [
                    'id' => $this->requester->id,
                    'name' => $this->requester->name,
                    'email' => $this->requester->email,
                ];
            }),
            
            'unit' => $this->whenLoaded('unit', function () {
                return [
                    'id' => $this->unit->id,
                    'name' => $this->unit->name,
                    'display_name' => $this->unit->display_name,
                ];
            }),
            
            'instruction' => $this->whenLoaded('instruction', function () {
                return [
                    'id' => $this->instruction->id,
                    'ulid' => $this->instruction->ulid,
                    'morning' => (float) $this->instruction->morning,
                    'afternoon' => (float) $this->instruction->afternoon,
                    'evening' => (float) $this->instruction->evening,
                    'night' => (float) $this->instruction->night,
                    'days' => $this->instruction->days,
                    'quantity' => (float) $this->instruction->quantity,
                    'note' => $this->instruction->note,
                    'total_daily_dose' => $this->instruction->total_daily_dose,
                    'total_quantity_needed' => $this->instruction->total_quantity_needed,
                    'dosage_schedule' => $this->instruction->getDosageSchedule(),
                    'has_active_doses' => $this->instruction->hasActiveDoses(),
                    
                    'method' => $this->whenLoaded('instruction.method', function () {
                        return [
                            'id' => $this->instruction->method->id,
                            'name' => $this->instruction->method->name,
                            'display_name' => $this->instruction->method->display_name,
                        ];
                    }),
                    
                    'unit' => $this->whenLoaded('instruction.unit', function () {
                        return [
                            'id' => $this->instruction->unit->id,
                            'name' => $this->instruction->unit->name,
                            'display_name' => $this->instruction->unit->display_name,
                        ];
                    }),
                ];
            }),
            
            'dispenses' => $this->whenLoaded('dispenses', function () {
                return MedicationDispenseResource::collection($this->dispenses);
            }),
        ];
    }
}