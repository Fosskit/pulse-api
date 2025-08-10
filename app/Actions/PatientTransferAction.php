<?php

namespace App\Actions;

use App\Models\Encounter;
use App\Models\Room;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

class PatientTransferAction
{
    public function __construct(
        private CheckRoomAvailabilityAction $checkRoomAvailabilityAction
    ) {}

    /**
     * Transfer a patient to a different room/department.
     *
     * @param int $visitId
     * @param int $destinationRoomId
     * @param array $transferData
     * @return array
     * @throws ModelNotFoundException
     * @throws ValidationException
     */
    public function execute(int $visitId, int $destinationRoomId, array $transferData = []): array
    {
        // Verify visit exists and is active
        $visit = Visit::with(['patient', 'encounters' => function ($query) {
            $query->whereNull('ended_at')->latest();
        }])->findOrFail($visitId);

        if ($visit->discharged_at) {
            throw ValidationException::withMessages([
                'visit' => ['Cannot transfer discharged patient']
            ]);
        }

        // Get current active encounter
        $currentEncounter = $visit->encounters->first();
        if (!$currentEncounter) {
            throw ValidationException::withMessages([
                'encounter' => ['No active encounter found for patient']
            ]);
        }

        // Verify destination room exists and get details
        $destinationRoom = Room::with(['department.facility', 'roomType'])->findOrFail($destinationRoomId);

        // Check if transfer is within the same facility
        if ($visit->facility_id !== $destinationRoom->department->facility_id) {
            throw ValidationException::withMessages([
                'facility' => ['Inter-facility transfers not supported in this action']
            ]);
        }

        // Check destination room availability
        $availabilityCheck = $this->checkRoomAvailabilityAction->execute(
            $destinationRoomId,
            Carbon::now(),
            Carbon::now()->addHours(1)
        );

        if (!$availabilityCheck['available']) {
            throw ValidationException::withMessages([
                'room' => ['Destination room is not available: ' . $availabilityCheck['reason']]
            ]);
        }

        // End current encounter
        $currentEncounter->update([
            'ended_at' => Carbon::now()
        ]);

        // Create transfer encounter
        $transferEncounter = Encounter::create([
            'visit_id' => $visit->id,
            'encounter_type_id' => $this->getTransferEncounterTypeId(),
            'encounter_form_id' => $this->getTransferFormId(),
            'started_at' => Carbon::now(),
            'is_new' => false
        ]);

        return [
            'success' => true,
            'transfer_details' => [
                'visit_id' => $visit->id,
                'patient' => [
                    'id' => $visit->patient->id,
                    'code' => $visit->patient->code
                ],
                'from_encounter' => [
                    'id' => $currentEncounter->id,
                    'ended_at' => $currentEncounter->ended_at->toISOString()
                ],
                'to_encounter' => [
                    'id' => $transferEncounter->id,
                    'started_at' => $transferEncounter->started_at->toISOString()
                ],
                'destination_room' => [
                    'id' => $destinationRoom->id,
                    'code' => $destinationRoom->code,
                    'name' => $destinationRoom->name,
                    'department' => [
                        'id' => $destinationRoom->department->id,
                        'name' => $destinationRoom->department->name
                    ]
                ],
                'transfer_reason' => $transferData['reason'] ?? null,
                'transfer_notes' => $transferData['notes'] ?? null,
                'transferred_at' => Carbon::now()->toISOString()
            ],
            'room_availability' => $availabilityCheck
        ];
    }

    /**
     * Validate transfer request without executing it.
     *
     * @param int $visitId
     * @param int $destinationRoomId
     * @return array
     */
    public function validateTransfer(int $visitId, int $destinationRoomId): array
    {
        try {
            $visit = Visit::with(['patient', 'encounters' => function ($query) {
                $query->whereNull('ended_at')->latest();
            }])->findOrFail($visitId);

            $destinationRoom = Room::with(['department.facility'])->findOrFail($destinationRoomId);

            $validationResults = [
                'valid' => true,
                'errors' => [],
                'warnings' => []
            ];

            // Check if visit is active
            if ($visit->discharged_at) {
                $validationResults['valid'] = false;
                $validationResults['errors'][] = 'Patient has been discharged';
            }

            // Check if there's an active encounter
            if ($visit->encounters->isEmpty()) {
                $validationResults['valid'] = false;
                $validationResults['errors'][] = 'No active encounter found';
            }

            // Check facility compatibility
            if ($visit->facility_id !== $destinationRoom->department->facility_id) {
                $validationResults['valid'] = false;
                $validationResults['errors'][] = 'Inter-facility transfers not supported';
            }

            // Check room availability
            $availabilityCheck = $this->checkRoomAvailabilityAction->execute($destinationRoomId);
            if (!$availabilityCheck['available']) {
                $validationResults['valid'] = false;
                $validationResults['errors'][] = 'Destination room not available: ' . $availabilityCheck['reason'];
            }

            // Add warnings for high utilization
            if (isset($availabilityCheck['department_utilization']['utilization_percentage']) && 
                $availabilityCheck['department_utilization']['utilization_percentage'] > 80) {
                $validationResults['warnings'][] = 'Destination department has high utilization';
            }

            $validationResults['availability_check'] = $availabilityCheck;

            return $validationResults;

        } catch (ModelNotFoundException $e) {
            return [
                'valid' => false,
                'errors' => ['Visit or destination room not found'],
                'warnings' => []
            ];
        }
    }

    /**
     * Get the encounter type ID for transfers.
     * In a real implementation, this would be configured or looked up from the database.
     */
    private function getTransferEncounterTypeId(): int
    {
        // This is a placeholder - in reality you'd look this up from the terms table
        // where the terminology is for encounter types and code is 'TRANSFER'
        return 1; // Placeholder
    }

    /**
     * Get the form ID for transfer encounters.
     * In a real implementation, this would be configured or looked up from the database.
     */
    private function getTransferFormId(): int
    {
        // This is a placeholder - in reality you'd look this up from clinical_form_templates
        // or have a dedicated transfer form
        return 1; // Placeholder
    }
}