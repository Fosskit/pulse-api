<?php

namespace App\Actions;

use App\Models\Patient;
use App\Models\PatientDemographic;
use App\Models\PatientAddress;
use App\Models\PatientIdentity;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreatePatientAction
{
    public function execute(array $data): Patient
    {
        return DB::transaction(function () use ($data) {
            // Create the patient record
            $patient = Patient::create([
                'code' => $data['code'],
                'facility_id' => $data['facility_id'],
            ]);

            // Create demographics if provided
            if (isset($data['demographics'])) {
                $this->createDemographics($patient, $data['demographics']);
            }

            // Create addresses if provided
            if (isset($data['addresses'])) {
                $this->createAddresses($patient, $data['addresses']);
            }

            // Create identities if provided
            if (isset($data['identities'])) {
                $this->createIdentities($patient, $data['identities']);
            }

            return $patient->load(['demographics', 'addresses', 'identities']);
        });
    }

    private function createDemographics(Patient $patient, array $demographicsData): void
    {
        PatientDemographic::create([
            'patient_id' => $patient->id,
            'name' => $demographicsData['name'] ?? null,
            'birthdate' => $demographicsData['birthdate'] ?? null,
            'sex' => $demographicsData['sex'] ?? null,
            'telecom' => $demographicsData['telecom'] ?? null,
            'address' => $demographicsData['address'] ?? null,
            'nationality_id' => $demographicsData['nationality_id'] ?? null,
            'telephone' => $demographicsData['telephone'] ?? null,
        ]);
    }

    private function createAddresses(Patient $patient, array $addressesData): void
    {
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

    private function createIdentities(Patient $patient, array $identitiesData): void
    {
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