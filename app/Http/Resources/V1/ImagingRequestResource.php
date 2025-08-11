<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImagingRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'service_request_id' => $this->service_request_id,
            'modality_concept_id' => $this->modality_concept_id,
            'body_site_concept_id' => $this->body_site_concept_id,
            'reason_for_study' => $this->reason_for_study,
            'performed_at' => $this->performed_at?->toISOString(),
            'performed_by' => $this->performed_by,
            
            // Relationships
            'service_request' => new ServiceRequestResource($this->whenLoaded('serviceRequest')),
            'modality_concept' => new ConceptResource($this->whenLoaded('modalityConcept')),
            'body_site_concept' => new ConceptResource($this->whenLoaded('bodySiteConcept')),
            
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}