<?php

namespace App\Actions;

use App\Models\MedicationInstruction;
use App\Models\MedicationRequest;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreatePrescriptionAction
{
    public function execute(array $data): MedicationRequest
    {
        return DB::transaction(function () use ($data) {
            // Validate visit exists and is active
            $visit = Visit::findOrFail($data['visit_id']);
            
            if (!$visit->is_active) {
                throw ValidationException::withMessages([
                    'visit_id' => ['Cannot create prescription for discharged visit.']
                ]);
            }

            // Create medication instruction first
            $instruction = $this->createMedicationInstruction($data['instruction']);

            // Create medication request
            $medicationRequest = MedicationRequest::create([
                'visit_id' => $data['visit_id'],
                'status_id' => $data['status_id'],
                'intent_id' => $data['intent_id'],
                'medication_id' => $data['medication_id'],
                'requester_id' => $data['requester_id'],
                'instruction_id' => $instruction->id,
                'quantity' => $data['quantity'],
                'unit_id' => $data['unit_id'],
            ]);

            return $medicationRequest->load([
                'visit.patient',
                'status',
                'intent',
                'medication',
                'requester',
                'instruction.method',
                'instruction.unit',
                'unit'
            ]);
        });
    }

    private function createMedicationInstruction(array $instructionData): MedicationInstruction
    {
        // Validate that at least one dose is provided
        $totalDose = ($instructionData['morning'] ?? 0) + 
                    ($instructionData['afternoon'] ?? 0) + 
                    ($instructionData['evening'] ?? 0) + 
                    ($instructionData['night'] ?? 0);

        if ($totalDose <= 0) {
            throw ValidationException::withMessages([
                'instruction' => ['At least one dose (morning, afternoon, evening, or night) must be greater than 0.']
            ]);
        }

        if (($instructionData['days'] ?? 0) <= 0) {
            throw ValidationException::withMessages([
                'instruction.days' => ['Days must be greater than 0.']
            ]);
        }

        return MedicationInstruction::create([
            'method_id' => $instructionData['method_id'],
            'unit_id' => $instructionData['unit_id'] ?? null,
            'morning' => $instructionData['morning'] ?? 0,
            'afternoon' => $instructionData['afternoon'] ?? 0,
            'evening' => $instructionData['evening'] ?? 0,
            'night' => $instructionData['night'] ?? 0,
            'days' => $instructionData['days'],
            'quantity' => $instructionData['quantity'] ?? 0,
            'note' => $instructionData['note'] ?? null,
        ]);
    }

    public function validateData(array $data): array
    {
        $rules = [
            'visit_id' => 'required|exists:visits,id',
            'status_id' => 'required|exists:terms,id',
            'intent_id' => 'required|exists:terms,id',
            'medication_id' => 'required|exists:terms,id',
            'requester_id' => 'required|exists:users,id',
            'quantity' => 'required|integer|min:1',
            'unit_id' => 'required|exists:terms,id',
            'instruction' => 'required|array',
            'instruction.method_id' => 'required|exists:terms,id',
            'instruction.unit_id' => 'nullable|exists:terms,id',
            'instruction.morning' => 'nullable|numeric|min:0',
            'instruction.afternoon' => 'nullable|numeric|min:0',
            'instruction.evening' => 'nullable|numeric|min:0',
            'instruction.night' => 'nullable|numeric|min:0',
            'instruction.days' => 'required|integer|min:1',
            'instruction.quantity' => 'nullable|numeric|min:0',
            'instruction.note' => 'nullable|string|max:1000',
        ];

        return validator($data, $rules)->validate();
    }
}