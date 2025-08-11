<?php

namespace App\Actions;

use App\Models\MedicationDispense;
use App\Models\MedicationRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DispenseMedicationAction
{
    public function execute(array $data): MedicationDispense
    {
        return DB::transaction(function () use ($data) {
            // Validate medication request exists and is not fully dispensed
            $medicationRequest = MedicationRequest::with(['visit', 'dispenses'])
                ->findOrFail($data['medication_request_id']);
            
            if (!$medicationRequest->visit->is_active) {
                throw ValidationException::withMessages([
                    'medication_request_id' => ['Cannot dispense medication for discharged visit.']
                ]);
            }

            // Check if there's enough quantity remaining to dispense
            $remainingQuantity = $medicationRequest->remaining_quantity;
            if ($data['quantity'] > $remainingQuantity) {
                throw ValidationException::withMessages([
                    'quantity' => ["Cannot dispense {$data['quantity']} units. Only {$remainingQuantity} units remaining."]
                ]);
            }

            // Create medication dispense
            $dispense = MedicationDispense::create([
                'visit_id' => $medicationRequest->visit_id,
                'status_id' => $data['status_id'],
                'medication_request_id' => $data['medication_request_id'],
                'dispenser_id' => $data['dispenser_id'],
                'quantity' => $data['quantity'],
                'unit_id' => $data['unit_id'],
            ]);

            return $dispense->load([
                'visit.patient',
                'status',
                'medicationRequest.medication',
                'dispenser',
                'unit'
            ]);
        });
    }

    public function validateData(array $data): array
    {
        $rules = [
            'medication_request_id' => 'required|exists:medication_requests,id',
            'status_id' => 'required|exists:terms,id',
            'dispenser_id' => 'required|exists:users,id',
            'quantity' => 'required|integer|min:1',
            'unit_id' => 'required|exists:terms,id',
        ];

        return validator($data, $rules)->validate();
    }
}