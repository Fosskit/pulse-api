<?php

namespace App\Http\Controllers\V1;

use App\Actions\CreatePrescriptionAction;
use App\Actions\DispenseMedicationAction;
use App\Actions\GetMedicationHistoryAction;
use App\Actions\RecordMedicationAdministrationAction;
use App\Actions\ValidateMedicationSafetyAction;
use App\Http\Requests\V1\CreatePrescriptionRequest;
use App\Http\Requests\V1\DispenseMedicationRequest;
use App\Http\Requests\V1\RecordMedicationAdministrationRequest;
use App\Http\Resources\V1\MedicationAdministrationResource;
use App\Http\Resources\V1\MedicationDispenseResource;
use App\Http\Resources\V1\MedicationRequestResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MedicationController extends BaseController
{
    public function __construct() {}

    /**
     * Create a new prescription for a visit
     */
    public function createPrescription(CreatePrescriptionRequest $request): JsonResponse
    {
        try {
            $createPrescriptionAction = new CreatePrescriptionAction();
            $medicationRequest = $createPrescriptionAction->execute($request->validated());

            return $this->createdResponse(
                new MedicationRequestResource($medicationRequest),
                'Prescription created successfully'
            );
        } catch (\Exception $e) {
            return $this->serverErrorResponse(
                'Failed to create prescription: ' . $e->getMessage()
            );
        }
    }

    /**
     * Get medication history for a patient
     */
    public function getPatientMedicationHistory(Request $request, int $patientId): JsonResponse
    {
        try {
            $filters = $request->only([
                'visit_id', 'status', 'medication_name', 
                'date_from', 'date_to', 'per_page'
            ]);

            $getMedicationHistoryAction = new GetMedicationHistoryAction();
            $medications = $getMedicationHistoryAction->forPatient($patientId, $filters);

            return $this->successResponse(
                MedicationRequestResource::collection($medications),
                'Patient medication history retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->serverErrorResponse(
                'Failed to retrieve medication history: ' . $e->getMessage()
            );
        }
    }

    /**
     * Get medication history for a specific visit
     */
    public function getVisitMedications(Request $request, int $visitId): JsonResponse
    {
        try {
            $filters = $request->only(['status', 'pending_only']);
            $getMedicationHistoryAction = new GetMedicationHistoryAction();
            $medications = $getMedicationHistoryAction->forVisit($visitId, $filters);

            return $this->successResponse(
                MedicationRequestResource::collection($medications),
                'Visit medications retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->serverErrorResponse(
                'Failed to retrieve visit medications: ' . $e->getMessage()
            );
        }
    }

    /**
     * Get active prescriptions for a patient
     */
    public function getActivePrescriptions(int $patientId): JsonResponse
    {
        try {
            $getMedicationHistoryAction = new GetMedicationHistoryAction();
            $prescriptions = $getMedicationHistoryAction->getActivePrescriptions($patientId);

            return $this->successResponse(
                MedicationRequestResource::collection($prescriptions),
                'Active prescriptions retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->serverErrorResponse(
                'Failed to retrieve active prescriptions: ' . $e->getMessage()
            );
        }
    }

    /**
     * Get pending dispenses for a visit
     */
    public function getPendingDispenses(int $visitId): JsonResponse
    {
        try {
            $getMedicationHistoryAction = new GetMedicationHistoryAction();
            $pendingDispenses = $getMedicationHistoryAction->getPendingDispenses($visitId);

            return $this->successResponse(
                MedicationRequestResource::collection($pendingDispenses),
                'Pending dispenses retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->serverErrorResponse(
                'Failed to retrieve pending dispenses: ' . $e->getMessage()
            );
        }
    }

    /**
     * Get medication summary for a patient
     */
    public function getMedicationSummary(int $patientId): JsonResponse
    {
        try {
            $getMedicationHistoryAction = new GetMedicationHistoryAction();
            $summary = $getMedicationHistoryAction->getMedicationSummary($patientId);

            return $this->successResponse(
                $summary,
                'Medication summary retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->serverErrorResponse(
                'Failed to retrieve medication summary: ' . $e->getMessage()
            );
        }
    }

    /**
     * Dispense medication from pharmacy
     */
    public function dispenseMedication(DispenseMedicationRequest $request): JsonResponse
    {
        try {
            $dispenseMedicationAction = new DispenseMedicationAction();
            $dispense = $dispenseMedicationAction->execute($request->validated());

            return $this->createdResponse(
                new MedicationDispenseResource($dispense),
                'Medication dispensed successfully'
            );
        } catch (\Exception $e) {
            return $this->serverErrorResponse(
                'Failed to dispense medication: ' . $e->getMessage()
            );
        }
    }

    /**
     * Record medication administration
     */
    public function recordAdministration(RecordMedicationAdministrationRequest $request): JsonResponse
    {
        try {
            $recordAdministrationAction = new RecordMedicationAdministrationAction();
            $administration = $recordAdministrationAction->execute($request->validated());

            return $this->createdResponse(
                new MedicationAdministrationResource($administration),
                'Medication administration recorded successfully'
            );
        } catch (\Exception $e) {
            return $this->serverErrorResponse(
                'Failed to record medication administration: ' . $e->getMessage()
            );
        }
    }

    /**
     * Validate medication safety for a patient
     */
    public function validateMedicationSafety(Request $request, int $patientId, int $medicationId): JsonResponse
    {
        try {
            $validateSafetyAction = new ValidateMedicationSafetyAction();
            $safetyCheck = $validateSafetyAction->execute($patientId, $medicationId);

            return $this->successResponse(
                $safetyCheck,
                'Medication safety validation completed'
            );
        } catch (\Exception $e) {
            return $this->serverErrorResponse(
                'Failed to validate medication safety: ' . $e->getMessage()
            );
        }
    }

    /**
     * Get medication administrations for a visit
     */
    public function getVisitAdministrations(int $visitId): JsonResponse
    {
        try {
            $administrations = \App\Models\MedicationAdministration::forVisit($visitId)
                ->with([
                    'medicationRequest.medication',
                    'status',
                    'administrator',
                    'doseUnit'
                ])
                ->orderBy('administered_at', 'desc')
                ->get();

            return $this->successResponse(
                MedicationAdministrationResource::collection($administrations),
                'Visit medication administrations retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->serverErrorResponse(
                'Failed to retrieve medication administrations: ' . $e->getMessage()
            );
        }
    }

    /**
     * Get medication administrations for a patient
     */
    public function getPatientAdministrations(Request $request, int $patientId): JsonResponse
    {
        try {
            $query = \App\Models\MedicationAdministration::forPatient($patientId)
                ->with([
                    'visit',
                    'medicationRequest.medication',
                    'status',
                    'administrator',
                    'doseUnit'
                ])
                ->orderBy('administered_at', 'desc');

            // Apply filters
            if ($request->has('date_from')) {
                $query->whereDate('administered_at', '>=', $request->input('date_from'));
            }

            if ($request->has('date_to')) {
                $query->whereDate('administered_at', '<=', $request->input('date_to'));
            }

            if ($request->has('with_adverse_reactions') && $request->boolean('with_adverse_reactions')) {
                $query->withAdverseReactions();
            }

            $administrations = $query->get();

            return $this->successResponse(
                MedicationAdministrationResource::collection($administrations),
                'Patient medication administrations retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->serverErrorResponse(
                'Failed to retrieve patient medication administrations: ' . $e->getMessage()
            );
        }
    }
}