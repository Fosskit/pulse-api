<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LaboratoryRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'service_request_id' => $this->service_request_id,
            'test_concept_id' => $this->test_concept_id,
            'specimen_type_concept_id' => $this->specimen_type_concept_id,
            'reason_for_study' => $this->reason_for_study,
            'performed_at' => $this->performed_at?->toISOString(),
            'performed_by' => $this->performed_by,
            
            // Relationships
            'service_request' => new ServiceRequestResource($this->whenLoaded('serviceRequest')),
            'test_concept' => new ConceptResource($this->whenLoaded('testConcept')),
            'specimen_type_concept' => new ConceptResource($this->whenLoaded('specimenTypeConcept')),
            
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}