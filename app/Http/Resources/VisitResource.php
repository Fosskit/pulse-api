<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VisitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'admitted_at' => $this->admitted_at?->format('Y-m-d H:i:s'),
            'discharged_at' => $this->discharged_at?->format('Y-m-d H:i:s'),
            'is_active' => is_null($this->discharged_at),
            'duration' => $this->discharged_at 
                ? $this->admitted_at->diffForHumans($this->discharged_at, true)
                : $this->admitted_at->diffForHumans(null, true),
            'duration_hours' => $this->discharged_at
                ? $this->admitted_at->diffInHours($this->discharged_at)
                : $this->admitted_at->diffInHours(now()),
            
            // Patient information
            'patient' => $this->whenLoaded('patient', function () {
                return [
                    'id' => $this->patient->id,
                    'code' => $this->patient->code,
                    'name' => $this->patient->surname . ' ' . $this->patient->name,
                    'sex' => $this->patient->sex,
                    'age' => $this->patient->birthdate ? now()->diffInYears($this->patient->birthdate) : null,
                    'initials' => substr($this->patient->surname, 0, 1) . substr($this->patient->name, 0, 1),
                ];
            }),
            
            // Visit types
            'visit_type' => $this->whenLoaded('visitType', fn() => [
                'id' => $this->visitType->id,
                'name' => $this->visitType->name,
            ]),
            
            'admission_type' => $this->whenLoaded('admissionType', fn() => [
                'id' => $this->admissionType->id,
                'name' => $this->admissionType->name,
            ]),
            
            'discharge_type' => $this->whenLoaded('dischargeType', fn() => [
                'id' => $this->dischargeType->id,
                'name' => $this->dischargeType->name,
            ]),
            
            'visit_outcome' => $this->whenLoaded('visitOutcome', fn() => [
                'id' => $this->visitOutcome->id,
                'name' => $this->visitOutcome->name,
            ]),
            
            // Facility
            'facility' => $this->whenLoaded('facility', fn() => [
                'id' => $this->facility->id,
                'name' => $this->facility->name,
                'code' => $this->facility->code,
            ]),
            
            // Encounters
            'encounters' => $this->whenLoaded('encounters', function () {
                return $this->encounters->map(function ($encounter) {
                    return [
                        'id' => $encounter->id,
                        'code' => $encounter->code,
                        'form_title' => $encounter->clinicalFormTemplate?->title,
                        'form_category' => $encounter->clinicalFormTemplate?->category,
                        'started_at' => $encounter->started_at?->format('Y-m-d H:i:s'),
                        'ended_at' => $encounter->ended_at?->format('Y-m-d H:i:s'),
                        'observations_count' => $encounter->observations_count ?? 0,
                    ];
                });
            }),
            
            // Caretakers
            'caretakers' => $this->whenLoaded('caretakers', function () {
                return $this->caretakers->map(function ($caretaker) {
                    return [
                        'id' => $caretaker->id,
                        'name' => $caretaker->name,
                        'phone' => $caretaker->phone,
                        'sex' => $caretaker->sex,
                        'relationship' => $caretaker->relationship?->name,
                    ];
                });
            }),
            
            // Statistics
            'stats' => [
                'forms_completed' => $this->encounters_count ?? $this->encounters->count(),
                'total_observations' => $this->whenLoaded('encounters', function() {
                    return $this->encounters->sum(fn($e) => $e->observations_count ?? 0);
                }),
            ],
            
            // Metadata
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'created_by' => $this->createdBy?->name ?? 'System',
        ];
    }
}