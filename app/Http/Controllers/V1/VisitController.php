<?php

namespace App\Http\Controllers\V1;

use App\Actions\AdmitPatientAction;
use App\Actions\DischargePatientAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\AdmitPatientRequest;
use App\Http\Requests\V1\DischargePatientRequest;
use App\Http\Resources\V1\VisitResource;
use App\Models\Visit;
use App\StandardResponse;
use Illuminate\Http\JsonResponse;

class VisitController extends Controller
{
    use StandardResponse;
    /**
     * Admit a patient (create a new visit)
     */
    public function store(AdmitPatientRequest $request): JsonResponse
    {
        try {
            $admitPatientAction = new AdmitPatientAction();
            $visit = $admitPatientAction->execute($request->validated());
            
            return $this->success(
                'Patient admitted successfully',
                new VisitResource($visit),
                201
            );
        } catch (\Exception $e) {
            return $this->failure(
                'Failed to admit patient: ' . $e->getMessage(),
                400
            );
        }
    }

    /**
     * Get visit details
     */
    public function show(Visit $visit): JsonResponse
    {
        $visit->load([
            'patient.demographics',
            'facility',
            'visitType',
            'admissionType',
            'dischargeType',
            'visitOutcome',
            'encounters.encounterType'
        ]);

        return $this->success(
            'Visit retrieved successfully',
            new VisitResource($visit)
        );
    }

    /**
     * Discharge a patient
     */
    public function discharge(Visit $visit, DischargePatientRequest $request): JsonResponse
    {
        try {
            $dischargePatientAction = new DischargePatientAction();
            $dischargedVisit = $dischargePatientAction->execute(
                $visit->id,
                $request->validated()
            );
            
            return $this->success(
                'Patient discharged successfully',
                new VisitResource($dischargedVisit)
            );
        } catch (\Exception $e) {
            return $this->failure(
                'Failed to discharge patient: ' . $e->getMessage(),
                400
            );
        }
    }

    /**
     * Get visit timeline and status
     */
    public function timeline(Visit $visit): JsonResponse
    {
        $visit->load([
            'encounters' => function ($query) {
                $query->orderBy('started_at');
            },
            'encounters.encounterType'
        ]);

        $timeline = [
            'visit_id' => $visit->id,
            'patient_id' => $visit->patient_id,
            'admitted_at' => $visit->admitted_at,
            'discharged_at' => $visit->discharged_at,
            'is_active' => $visit->is_active,
            'duration_days' => $visit->duration,
            'encounters' => $visit->encounters->map(function ($encounter) {
                return [
                    'id' => $encounter->id,
                    'type' => $encounter->encounterType->name ?? 'Unknown',
                    'started_at' => $encounter->started_at,
                    'ended_at' => $encounter->ended_at,
                    'duration_minutes' => $encounter->started_at && $encounter->ended_at 
                        ? $encounter->started_at->diffInMinutes($encounter->ended_at)
                        : null,
                ];
            })
        ];

        return $this->success(
            'Visit timeline retrieved successfully',
            $timeline
        );
    }

    /**
     * Display a listing of visits
     */
    public function index(): JsonResponse
    {
        $visits = Visit::with(['patient', 'facility', 'visitType', 'admissionType'])
            ->orderBy('admitted_at', 'desc')
            ->paginate(15);

        return $this->success(
            'Visits retrieved successfully',
            VisitResource::collection($visits)
        );
    }

    /**
     * Update the specified visit
     */
    public function update(Visit $visit): JsonResponse
    {
        return $this->failure('Visit updates not implemented', 501);
    }

    /**
     * Remove the specified visit
     */
    public function destroy(Visit $visit): JsonResponse
    {
        return $this->failure('Visit deletion not implemented', 501);
    }
}