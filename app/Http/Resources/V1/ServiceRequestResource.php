<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'visit_id' => $this->visit_id,
            'encounter_id' => $this->encounter_id,
            'service_id' => $this->service_id,
            'request_type' => $this->request_type,
            'status_id' => $this->status_id,
            'ordered_at' => $this->ordered_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'scheduled_at' => $this->scheduled_at?->toISOString(),
            'scheduled_for' => $this->scheduled_for,
            'is_completed' => $this->isCompleted(),
            'is_pending' => $this->isPending(),
            
            // Relationships
            'visit' => new VisitResource($this->whenLoaded('visit')),
            'encounter' => new EncounterResource($this->whenLoaded('encounter')),
            'service' => new ServiceResource($this->whenLoaded('service')),
            'status' => new TermResource($this->whenLoaded('status')),
            
            // Specific request type data
            'laboratory_request' => new LaboratoryRequestResource($this->whenLoaded('laboratoryRequest')),
            'imaging_request' => new ImagingRequestResource($this->whenLoaded('imagingRequest')),
            'observations' => ObservationResource::collection($this->whenLoaded('observations')),
            
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}