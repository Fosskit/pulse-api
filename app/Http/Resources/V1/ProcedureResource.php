<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProcedureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'patient_id' => $this->patient_id,
            'encounter_id' => $this->encounter_id,
            'procedure_concept_id' => $this->procedure_concept_id,
            'outcome_id' => $this->outcome_id,
            'body_site_id' => $this->body_site_id,
            'performed_at' => $this->performed_at?->toISOString(),
            'performed_by' => $this->performed_by,
            
            // Relationships
            'patient' => new PatientResource($this->whenLoaded('patient')),
            'encounter' => new EncounterResource($this->whenLoaded('encounter')),
            'procedure_concept' => new ConceptResource($this->whenLoaded('procedureConcept')),
            'outcome' => new ConceptResource($this->whenLoaded('outcome')),
            'body_site' => new ConceptResource($this->whenLoaded('bodySite')),
            
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}