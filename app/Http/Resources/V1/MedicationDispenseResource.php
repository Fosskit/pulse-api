<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MedicationDispenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'visit_id' => $this->visit_id,
            'medication_request_id' => $this->medication_request_id,
            'quantity' => $this->quantity,
            'is_dispensed' => $this->is_dispensed,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relationships
            'status' => $this->whenLoaded('status', function () {
                return [
                    'id' => $this->status->id,
                    'name' => $this->status->name,
                    'display_name' => $this->status->display_name,
                ];
            }),
            
            'dispenser' => $this->whenLoaded('dispenser', function () {
                return [
                    'id' => $this->dispenser->id,
                    'name' => $this->dispenser->name,
                    'email' => $this->dispenser->email,
                ];
            }),
            
            'unit' => $this->whenLoaded('unit', function () {
                return [
                    'id' => $this->unit->id,
                    'name' => $this->unit->name,
                    'display_name' => $this->unit->display_name,
                ];
            }),
            
            'medication_request' => $this->whenLoaded('medicationRequest', function () {
                return [
                    'id' => $this->medicationRequest->id,
                    'ulid' => $this->medicationRequest->ulid,
                    'quantity' => $this->medicationRequest->quantity,
                    'medication' => $this->whenLoaded('medicationRequest.medication', function () {
                        return [
                            'id' => $this->medicationRequest->medication->id,
                            'name' => $this->medicationRequest->medication->name,
                            'display_name' => $this->medicationRequest->medication->display_name,
                        ];
                    }),
                ];
            }),
        ];
    }
}