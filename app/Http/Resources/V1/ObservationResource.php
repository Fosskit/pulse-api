<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ObservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'patient_id' => $this->patient_id,
            'encounter_id' => $this->encounter_id,
            'code' => $this->code,
            'concept_id' => $this->concept_id,
            'body_site_id' => $this->body_site_id,
            'value_type' => $this->value_type,
            'formatted_value' => $this->formatted_value,
            'value_string' => $this->value_string,
            'value_number' => $this->value_number,
            'value_text' => $this->value_text,
            'value_complex' => $this->value_complex,
            'value_datetime' => $this->value_datetime?->toISOString(),
            'observed_at' => $this->observed_at?->toISOString(),
            'observed_by' => $this->observed_by,
            
            // Relationships
            'observation_concept' => $this->whenLoaded('observationConcept', function () {
                return [
                    'id' => $this->observationConcept->id,
                    'code' => $this->observationConcept->code,
                    'name' => $this->observationConcept->name,
                ];
            }),
            
            'observation_status' => $this->whenLoaded('observationStatus', function () {
                return [
                    'id' => $this->observationStatus->id,
                    'code' => $this->observationStatus->code,
                    'name' => $this->observationStatus->name,
                ];
            }),
            
            'body_site' => $this->whenLoaded('bodySite', function () {
                return [
                    'id' => $this->bodySite->id,
                    'code' => $this->bodySite->code,
                    'name' => $this->bodySite->name,
                ];
            }),
            
            'parent' => $this->whenLoaded('parent', function () {
                return new ObservationResource($this->parent);
            }),
            
            'children' => $this->whenLoaded('children', function () {
                return ObservationResource::collection($this->children);
            }),
            
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}