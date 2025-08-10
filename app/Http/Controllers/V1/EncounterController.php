<?php

namespace App\Http\Controllers\V1;

use App\Actions\CreateEncounterAction;
use App\Actions\TransferPatientEncounterAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\CreateEncounterRequest;
use App\Http\Requests\V1\TransferPatientEncounterRequest;
use App\Http\Resources\V1\EncounterResource;
use App\Models\Encounter;
use App\Models\Visit;
use App\StandardResponse;
use Illuminate\Http\JsonResponse;

class EncounterController extends Controller
{
    use StandardResponse;

    /**
     * Display a listing of encounters
     */
    public function index(): JsonResponse
    {
        $encounters = Encounter::with(['visit.patient', 'encounterType'])
            ->orderBy('started_at', 'desc')
            ->paginate(15);

        return $this->success(
            'Encounters retrieved successfully',
            EncounterResource::collection($encounters)
        );
    }

    /**
     * Store a newly created encounter
     */
    public function store(CreateEncounterRequest $request): JsonResponse
    {
        try {
            $createEncounterAction = new CreateEncounterAction();
            $encounter = $createEncounterAction->execute(
                $request->input('visit_id'),
                $request->validated()
            );
            
            return $this->success(
                'Encounter created successfully',
                new EncounterResource($encounter),
                201
            );
        } catch (\Exception $e) {
            return $this->failure(
                'Failed to create encounter: ' . $e->getMessage(),
                400
            );
        }
    }

    /**
     * Display the specified encounter
     */
    public function show(Encounter $encounter): JsonResponse
    {
        $encounter->load([
            'visit.patient.demographics',
            'encounterType',
            'observations.observationConcept'
        ]);

        return $this->success(
            'Encounter retrieved successfully',
            new EncounterResource($encounter)
        );
    }

    /**
     * Update the specified encounter
     */
    public function update(Encounter $encounter): JsonResponse
    {
        return $this->failure('Encounter updates not implemented', 501);
    }

    /**
     * Remove the specified encounter
     */
    public function destroy(Encounter $encounter): JsonResponse
    {
        return $this->failure('Encounter deletion not implemented', 501);
    }

    /**
     * Get observations for an encounter
     */
    public function observations(Encounter $encounter): JsonResponse
    {
        $encounter->load([
            'observations' => function ($query) {
                $query->orderBy('created_at', 'desc');
            },
            'observations.observationConcept'
        ]);

        return $this->success(
            'Encounter observations retrieved successfully',
            $encounter->observations
        );
    }

    /**
     * Submit form data for an encounter
     */
    public function submitForm(Encounter $encounter): JsonResponse
    {
        return $this->failure('Form submission not implemented', 501);
    }

    /**
     * Transfer patient (create transfer encounter)
     */
    public function transfer(TransferPatientEncounterRequest $request): JsonResponse
    {
        try {
            $transferAction = new TransferPatientEncounterAction();
            $transferEncounter = $transferAction->execute(
                $request->input('visit_id'),
                $request->validated()
            );
            
            return $this->success(
                'Patient transferred successfully',
                new EncounterResource($transferEncounter)
            );
        } catch (\Exception $e) {
            return $this->failure(
                'Failed to transfer patient: ' . $e->getMessage(),
                400
            );
        }
    }

    /**
     * Get chronological encounters for a visit
     */
    public function chronological(Visit $visit): JsonResponse
    {
        $encounters = $visit->encounters()
            ->with(['encounterType', 'observations'])
            ->orderBy('started_at')
            ->get();

        $timeline = $encounters->map(function ($encounter) {
            return [
                'id' => $encounter->id,
                'type' => $encounter->encounterType->name ?? 'Unknown',
                'type_code' => $encounter->encounterType->code ?? null,
                'started_at' => $encounter->started_at,
                'ended_at' => $encounter->ended_at,
                'duration_minutes' => $encounter->started_at && $encounter->ended_at 
                    ? $encounter->started_at->diffInMinutes($encounter->ended_at)
                    : null,
                'is_active' => is_null($encounter->ended_at),
                'observations_count' => $encounter->observations->count(),
                'is_new' => $encounter->is_new,
            ];
        });

        return $this->success(
            'Chronological encounters retrieved successfully',
            [
                'visit_id' => $visit->id,
                'patient_id' => $visit->patient_id,
                'encounters' => $timeline,
                'total_encounters' => $encounters->count(),
                'active_encounters' => $encounters->whereNull('ended_at')->count(),
            ]
        );
    }
}