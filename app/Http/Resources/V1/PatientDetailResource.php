<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'facility_id' => $this->facility_id,
            'full_name' => $this->full_name,
            'age' => $this->age,
            'is_deceased' => $this->is_deceased,
            'has_active_insurance' => $this->hasActiveInsurance(),
            'payment_type_id' => $this->getPaymentTypeId(),
            'is_beneficiary' => $this->isBeneficiary(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Complete demographics
            'demographics' => $this->whenLoaded('demographics', function () {
                return [
                    'id' => $this->demographics->id,
                    'name' => $this->demographics->name,
                    'given_name' => $this->demographics->given_name,
                    'family_name' => $this->demographics->family_name,
                    'birthdate' => $this->demographics->birthdate,
                    'sex' => $this->demographics->sex,
                    'telecom' => $this->demographics->telecom,
                    'address' => $this->demographics->address,
                    'telephone' => $this->demographics->telephone,
                    'nationality_id' => $this->demographics->nationality_id,
                    'died_at' => $this->demographics->died_at,
                    'nationality' => $this->demographics->nationality ? [
                        'id' => $this->demographics->nationality->id,
                        'name' => $this->demographics->nationality->name,
                        'code' => $this->demographics->nationality->code,
                    ] : null,
                ];
            }),
            
            // All addresses with gazetteer information
            'addresses' => $this->whenLoaded('addresses', function () {
                return $this->addresses->map(function ($address) {
                    return [
                        'id' => $address->id,
                        'street_address' => $address->street_address,
                        'is_current' => $address->is_current,
                        'full_address' => $address->full_address,
                        'province' => $address->province ? [
                            'id' => $address->province->id,
                            'name' => $address->province->name,
                            'code' => $address->province->code,
                        ] : null,
                        'district' => $address->district ? [
                            'id' => $address->district->id,
                            'name' => $address->district->name,
                            'code' => $address->district->code,
                        ] : null,
                        'commune' => $address->commune ? [
                            'id' => $address->commune->id,
                            'name' => $address->commune->name,
                            'code' => $address->commune->code,
                        ] : null,
                        'village' => $address->village ? [
                            'id' => $address->village->id,
                            'name' => $address->village->name,
                            'code' => $address->village->code,
                        ] : null,
                        'address_type' => $address->addressType ? [
                            'id' => $address->addressType->id,
                            'name' => $address->addressType->name,
                            'code' => $address->addressType->code,
                        ] : null,
                    ];
                });
            }),
            
            // All identities with card information
            'identities' => $this->whenLoaded('identities', function () {
                return $this->identities->map(function ($identity) {
                    return [
                        'id' => $identity->id,
                        'code' => $identity->code,
                        'start_date' => $identity->start_date,
                        'end_date' => $identity->end_date,
                        'is_active' => $identity->is_active,
                        'is_expired' => $identity->is_expired,
                        'detail' => $identity->detail,
                        'card' => $identity->card ? [
                            'id' => $identity->card->id,
                            'code' => $identity->card->code,
                            'issue_date' => $identity->card->issue_date,
                            'expiry_date' => $identity->card->expiry_date,
                            'is_active' => $identity->card->is_active,
                            'is_expired' => $identity->card->is_expired,
                            'card_type' => $identity->card->cardType ? [
                                'id' => $identity->card->cardType->id,
                                'name' => $identity->card->cardType->name,
                                'code' => $identity->card->cardType->code,
                            ] : null,
                        ] : null,
                    ];
                });
            }),
            
            // Active insurance information
            'active_insurance' => $this->active_insurance ? [
                'identity_id' => $this->active_insurance->id,
                'code' => $this->active_insurance->code,
                'start_date' => $this->active_insurance->start_date,
                'end_date' => $this->active_insurance->end_date,
                'is_active' => $this->active_insurance->is_active,
                'card' => [
                    'id' => $this->active_insurance->card->id,
                    'code' => $this->active_insurance->card->code,
                    'card_type' => $this->active_insurance->card->cardType->name ?? 'Unknown',
                    'card_type_code' => $this->active_insurance->card->cardType->code ?? null,
                    'issue_date' => $this->active_insurance->card->issue_date,
                    'expiry_date' => $this->active_insurance->card->expiry_date,
                    'is_active' => $this->active_insurance->card->is_active,
                ],
            ] : null,

            // Beneficiary status for invoice generation
            'beneficiary_status' => [
                'is_beneficiary' => $this->isBeneficiary(),
                'payment_type_id' => $this->getPaymentTypeId(),
                'insurance_summary' => $this->getInsuranceSummaryForInvoice(),
            ],
            
            // Visit summary
            'visits_summary' => $this->whenLoaded('visits', function () {
                return [
                    'total_visits' => $this->visits->count(),
                    'active_visits' => $this->visits->where('discharged_at', null)->count(),
                    'last_visit_date' => $this->visits->max('admitted_at'),
                ];
            }),
            
            // Facility information
            'facility' => $this->whenLoaded('facility', function () {
                return [
                    'id' => $this->facility->id,
                    'name' => $this->facility->name ?? 'Unknown Facility',
                    'code' => $this->facility->code ?? null,
                ];
            }),
        ];
    }
}