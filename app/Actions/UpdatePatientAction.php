<?php

namespace App\Actions;

use App\Models\Patient;
use App\Models\PatientDemographic;
use App\Models\PatientAddress;
use App\Models\PatientIdentity;
use Illuminate\Support\Facades\DB;

class UpdatePatientAction
{
    public function execute(Patient $patient, array $data): Patient
    {
        return DB::transaction(function () use ($patient, $data) {
            // Update patient basic information
            if (isset($data['code']) || isset($data['facility_id'])) {
                $patient->update(array_filter([
                    'code' => $data['code'] ?? null,
                    'facility_id' => $data['facility_id'] ?? null,
                ]));
            }

            // Update demographics if provided
            if (isset($data['demographics'])) {
                $this->updateDemographics($patient, $data['demographics']);
            }

            // Update addresses if provided
            if (isset($data['addresses'])) {
                $this->updateAddresses($patient, $data['addresses']);
            }

            // Update identities if provided
            if (isset($data['identities'])) {
                $this->updateIdentities($patient, $data['identities']);
            }

            return $patient->fresh(['demographics', 'addresses', 'identities']);
        });
    }

    private function updateDemographics(Patient $patient, array $demographicsData): void
    {
        $demographics = $patient->demographics;
        
        if ($demographics) {
            $demographics->update(array_filter([
                'name' => $demographicsData['name'] ?? null,
                'birthdate' => $demographicsData['birthdate'] ?? null,
                'sex' => $demographicsData['sex'] ?? null,
                'telecom' => $demographicsData['telecom'] ?? null,
                'address' => $demographicsData['address'] ?? null,
                'nationality_id' => $demographicsData['nationality_id'] ?? null,
                'telephone' => $demographicsData['telephone'] ?? null,
                'died_at' => $demographicsData['died_at'] ?? null,
            ]));
        } else {
            PatientDemographic::create([
                'patient_id' => $patient->id,
                'name' => $demographicsData['name'] ?? null,
                'birthdate' => $demographicsData['birthdate'] ?? null,
                'sex' => $demographicsData['sex'] ?? null,
                'telecom' => $demographicsData['telecom'] ?? null,
                'address' => $demographicsData['address'] ?? null,
                'nationality_id' => $demographicsData['nationality_id'] ?? null,
                'telephone' => $demographicsData['telephone'] ?? null,
                'died_at' => $demographicsData['died_at'] ?? null,
            ]);
        }
    }

    private function updateAddresses(Patient $patient, array $addressesData): void
    {
        // For simplicity, we'll replace all addresses
        // In a production system, you might want more sophisticated merging logic
        $patient->addresses()->delete();
        
        foreach ($addressesData as $addressData) {
            PatientAddress::create([
                'patient_id' => $patient->id,
                'province_id' => $addressData['province_id'],
                'district_id' => $addressData['district_id'],
                'commune_id' => $addressData['commune_id'],
                'village_id' => $addressData['village_id'],
                'street_address' => $addressData['street_address'] ?? '',
                'is_current' => $addressData['is_current'] ?? true,
                'address_type_id' => $addressData['address_type_id'] ?? null,
            ]);
        }
    }

    private function updateIdentities(Patient $patient, array $identitiesData): void
    {
        // For simplicity, we'll replace all identities
        // In a production system, you might want more sophisticated merging logic
        $patient->identities()->delete();
        
        foreach ($identitiesData as $identityData) {
            PatientIdentity::create([
                'patient_id' => $patient->id,
                'code' => $identityData['code'],
                'card_id' => $identityData['card_id'],
                'start_date' => $identityData['start_date'],
                'end_date' => $identityData['end_date'] ?? null,
                'detail' => $identityData['detail'] ?? [],
            ]);
        }
    }
}