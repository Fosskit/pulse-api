<?php

namespace App\Actions;

use App\Models\Patient;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class GetPatientDetailsAction
{
    public function execute(int $patientId): Patient
    {
        $patient = Patient::with([
            'demographics',
            'addresses.province',
            'addresses.district', 
            'addresses.commune',
            'addresses.village',
            'addresses.addressType',
            'identities.card.cardType',
            'visits.encounters',
            'visits.medicationRequests',
            'visits.serviceRequests',
            'visits.invoices.invoiceItems',
            'facility'
        ])->find($patientId);

        if (!$patient) {
            throw new ModelNotFoundException("Patient with ID {$patientId} not found");
        }

        return $patient;
    }

    public function executeByCode(string $patientCode): Patient
    {
        $patient = Patient::with([
            'demographics',
            'addresses.province',
            'addresses.district', 
            'addresses.commune',
            'addresses.village',
            'addresses.addressType',
            'identities.card.cardType',
            'visits.encounters',
            'visits.medicationRequests',
            'visits.serviceRequests',
            'visits.invoices.invoiceItems',
            'facility'
        ])->where('code', $patientCode)->first();

        if (!$patient) {
            throw new ModelNotFoundException("Patient with code {$patientCode} not found");
        }

        return $patient;
    }

    public function getPatientSummary(int $patientId): array
    {
        $patient = $this->execute($patientId);

        return [
            'patient' => [
                'id' => $patient->id,
                'code' => $patient->code,
                'full_name' => $patient->full_name,
                'age' => $patient->age,
                'sex' => $patient->demographics?->sex,
                'is_deceased' => $patient->is_deceased,
                'has_active_insurance' => $patient->hasActiveInsurance(),
                'active_insurance' => $patient->active_insurance,
            ],
            'demographics' => $patient->demographics,
            'current_address' => $patient->currentAddress,
            'active_identities' => $patient->identities()->active()->with('card.cardType')->get(),
            'recent_visits' => $patient->visits()->with('encounters')->latest()->take(5)->get(),
            'facility' => $patient->facility,
        ];
    }
}