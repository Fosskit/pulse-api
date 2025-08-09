<?php

namespace App\Http\Controllers\V1;

use App\Actions\CreatePatientAction;
use App\Actions\UpdatePatientAction;
use App\Actions\SearchPatientsAction;
use App\Actions\GetPatientDetailsAction;
use App\Http\Requests\V1\CreatePatientRequest;
use App\Http\Requests\V1\UpdatePatientRequest;
use App\Http\Requests\V1\SearchPatientsRequest;
use App\Http\Resources\V1\PatientResource;
use App\Http\Resources\V1\PatientDetailResource;
use App\Models\Patient;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PatientController extends BaseController
{
    public function __construct(
        private CreatePatientAction $createPatientAction,
        private UpdatePatientAction $updatePatientAction,
        private SearchPatientsAction $searchPatientsAction,
        private GetPatientDetailsAction $getPatientDetailsAction
    ) {}

    /**
     * Display a listing of patients with search and filtering capabilities.
     */
    public function index(SearchPatientsRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $perPage = $request->input('per_page', 15);

        $patients = $this->searchPatientsAction->execute($filters, $perPage);

        return PatientResource::collection($patients);
    }

    /**
     * Store a newly created patient.
     */
    public function store(CreatePatientRequest $request): JsonResponse
    {
        try {
            $patient = $this->createPatientAction->execute($request->validated());

            return $this->successResponse(
                new PatientDetailResource($patient),
                'Patient created successfully',
                201
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to create patient: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Display the specified patient with comprehensive details.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $patient = $this->getPatientDetailsAction->execute($id);

            return $this->successResponse(
                new PatientDetailResource($patient),
                'Patient retrieved successfully'
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Patient not found', 404);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve patient: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Update the specified patient.
     */
    public function update(UpdatePatientRequest $request, Patient $patient): JsonResponse
    {
        try {
            $updatedPatient = $this->updatePatientAction->execute($patient, $request->validated());

            return $this->successResponse(
                new PatientDetailResource($updatedPatient),
                'Patient updated successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to update patient: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Remove the specified patient (soft delete).
     */
    public function destroy(Patient $patient): JsonResponse
    {
        try {
            $patient->delete();

            return $this->successResponse(
                null,
                'Patient deleted successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to delete patient: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get patient by code.
     */
    public function showByCode(string $code): JsonResponse
    {
        try {
            $patient = $this->getPatientDetailsAction->executeByCode($code);

            return $this->successResponse(
                new PatientDetailResource($patient),
                'Patient retrieved successfully'
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Patient not found', 404);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve patient: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get patient summary with key information.
     */
    public function summary(int $id): JsonResponse
    {
        try {
            $summary = $this->getPatientDetailsAction->getPatientSummary($id);

            return $this->successResponse(
                $summary,
                'Patient summary retrieved successfully'
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Patient not found', 404);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve patient summary: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get patient visits.
     */
    public function visits(Patient $patient): JsonResponse
    {
        try {
            $visits = $patient->visits()
                ->with(['encounters', 'medicationRequests', 'serviceRequests', 'invoices'])
                ->orderBy('admitted_at', 'desc')
                ->paginate(10);

            return $this->successResponse(
                $visits,
                'Patient visits retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve patient visits: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get patient insurance status and details.
     */
    public function insuranceStatus(Patient $patient): JsonResponse
    {
        try {
            $insuranceAction = app(\App\Actions\ManagePatientInsuranceAction::class);
            $status = $insuranceAction->getInsuranceStatus($patient);

            return $this->successResponse(
                $status,
                'Patient insurance status retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve insurance status: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Add insurance identity to patient.
     */
    public function addInsurance(Patient $patient, \Illuminate\Http\Request $request): JsonResponse
    {
        try {
            $request->validate([
                'code' => 'required|string|max:255',
                'card_id' => 'required|integer|exists:cards,id',
                'start_date' => 'required|date',
                'end_date' => 'nullable|date|after:start_date',
                'detail' => 'sometimes|array',
            ]);

            $insuranceAction = app(\App\Actions\ManagePatientInsuranceAction::class);
            $identity = $insuranceAction->addInsuranceIdentity($patient, $request->validated());

            return $this->successResponse(
                $identity,
                'Insurance identity added successfully',
                201
            );
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to add insurance identity: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get beneficiary status for invoice generation.
     */
    public function beneficiaryStatus(Patient $patient): JsonResponse
    {
        try {
            $status = $patient->getBeneficiaryStatus();

            return $this->successResponse(
                $status,
                'Beneficiary status retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve beneficiary status: ' . $e->getMessage(),
                500
            );
        }
    }
}