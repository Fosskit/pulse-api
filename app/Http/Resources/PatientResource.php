<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'surname' => $this->surname,
            'name' => $this->name,
            'full_name' => $this->surname . ' ' . $this->name,
            'initials' => substr($this->surname, 0, 1) . substr($this->name, 0, 1),
            'sex' => $this->sex,
            'sex_label' => $this->sex === 'M' ? 'Male' : 'Female',
            'birthdate' => $this->birthdate?->format('Y-m-d'),
            'age' => $this->birthdate ? now()->diffInYears($this->birthdate) : null,
            'phone' => $this->phone,
            'death_at' => $this->death_at?->format('Y-m-d H:i:s'),
            'is_alive' => is_null($this->death_at),
            
            // Relationships
            'nationality' => $this->whenLoaded('nationality', fn() => [
                'id' => $this->nationality->id,
                'name' => $this->nationality->name,
                'code' => $this->nationality->code,
            ]),
            
            'facility' => $this->whenLoaded('facility', fn() => [
                'id' => $this->facility->id,
                'name' => $this->facility->name,
                'code' => $this->facility->code,
            ]),
            
            'addresses' => $this->whenLoaded('addresses', function () {
                return $this->addresses->map(function ($address) {
                    return [
                        'id' => $address->id,
                        'street_address' => $address->street_address,
                        'is_current' => $address->is_current,
                        'province' => $address->province?->name,
                        'district' => $address->district?->name,
                        'commune' => $address->commune?->name,
                        'village' => $address->village?->name,
                    ];
                });
            }),
            
            'identities' => $this->whenLoaded('identities', function () {
                return $this->identities->map(function ($identity) {
                    return [
                        'id' => $identity->id,
                        'card_type' => $identity->cardType?->name,
                        'code' => $identity->code,
                        'issued_date' => $identity->issued_date?->format('Y-m-d'),
                        'expired_date' => $identity->expired_date?->format('Y-m-d'),
                        'is_active' => $identity->is_active,
                    ];
                });
            }),
            
            'visits' => $this->whenLoaded('visits', function () {
                return $this->visits->map(function ($visit) {
                    return [
                        'id' => $visit->id,
                        'code' => $visit->code,
                        'visit_type' => $visit->visitType?->name,
                        'admitted_at' => $visit->admitted_at?->format('Y-m-d H:i:s'),
                        'discharged_at' => $visit->discharged_at?->format('Y-m-d H:i:s'),
                        'is_active' => is_null($visit->discharged_at),
                    ];
                });
            }),
            
            // Metadata
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'created_by' => $this->createdBy?->name ?? 'System',
        ];
    }
}