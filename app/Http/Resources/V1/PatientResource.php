<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'facility_id' => $this->facility_id,
            'full_name' => $this->full_name,
            'age' => $this->age,
            'sex' => $this->demographics?->sex,
            'is_deceased' => $this->is_deceased,
            'has_active_insurance' => $this->hasActiveInsurance(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Basic demographics
            'demographics' => $this->whenLoaded('demographics', function () {
                return [
                    'name' => $this->demographics->name,
                    'birthdate' => $this->demographics->birthdate,
                    'sex' => $this->demographics->sex,
                    'telephone' => $this->demographics->telephone,
                    'nationality_id' => $this->demographics->nationality_id,
                ];
            }),
            
            // Current address only
            'current_address' => $this->whenLoaded('addresses', function () {
                $currentAddress = $this->addresses->where('is_current', true)->first();
                return $currentAddress ? [
                    'street_address' => $currentAddress->street_address,
                    'full_address' => $currentAddress->full_address,
                    'province_id' => $currentAddress->province_id,
                    'district_id' => $currentAddress->district_id,
                    'commune_id' => $currentAddress->commune_id,
                    'village_id' => $currentAddress->village_id,
                ] : null;
            }),
            
            // Active identities count
            'active_identities_count' => $this->whenLoaded('identities', function () {
                return $this->identities->filter(function ($identity) {
                    return $identity->is_active;
                })->count();
            }),
            
            // Facility info
            'facility' => $this->whenLoaded('facility', function () {
                return [
                    'id' => $this->facility->id,
                    'name' => $this->facility->name ?? 'Unknown Facility',
                ];
            }),
        ];
    }
}