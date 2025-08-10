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
            'encounter_id' => $this->encounter_id,
            'patient_id' => $this->patient_id,
            'observation_concept_id' => $this->observation_concept_id,
            'observation_status_id' => $this->observation_status_id,
            'value_string' => $this->value_string,
            'value_number' => $this->value_number,
            'value_datetime' => $this->value_datetime?->toISOString(),
            'value_complex' => $this->value_complex,
            'body_site_id' => $this->body_site_id,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}