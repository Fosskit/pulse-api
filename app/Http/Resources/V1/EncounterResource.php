<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EncounterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'visit_id' => $this->visit_id,
            'encounter_type_id' => $this->encounter_type_id,
            'encounter_form_id' => $this->encounter_form_id,
            'is_new' => $this->is_new,
            'started_at' => $this->started_at?->toISOString(),
            'ended_at' => $this->ended_at?->toISOString(),
            'is_active' => is_null($this->ended_at),
            'duration_minutes' => $this->started_at && $this->ended_at 
                ? $this->started_at->diffInMinutes($this->ended_at)
                : null,
            
            // Relationships
            'encounter_type' => $this->whenLoaded('encounterType', function () {
                return [
                    'id' => $this->encounterType->id,
                    'code' => $this->encounterType->code,
                    'name' => $this->encounterType->name,
                ];
            }),
            
            'clinical_form_template' => $this->whenLoaded('clinicalFormTemplate', function () {
                return [
                    'id' => $this->clinicalFormTemplate->id,
                    'name' => $this->clinicalFormTemplate->name,
                    'title' => $this->clinicalFormTemplate->title,
                    'category' => $this->clinicalFormTemplate->category,
                    'active' => $this->clinicalFormTemplate->active,
                ];
            }),
            
            'observations' => $this->whenLoaded('observations', function () {
                return ObservationResource::collection($this->observations);
            }),
            
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}