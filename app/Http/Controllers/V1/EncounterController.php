<?php

namespace App\Http\Controllers\V1;

use App\Actions\CreateEncounterAction;
use App\Actions\ProcessFormSubmissionAction;
use App\Actions\TransferPatientAction;
use App\Actions\TransferPatientEncounterAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\CreateEncounterRequest;
use App\Http\Requests\V1\SubmitFormRequest;
use App\Http\Requests\V1\TransferPatientRequest;
use App\Http\Requests\V1\TransferPatientEncounterRequest;
use App\Http\Resources\V1\EncounterResource;
use App\Http\Resources\V1\ObservationResource;
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
    public function submitForm(Encounter $encounter, SubmitFormRequest $request): JsonResponse
    {
        try {
            $processFormAction = new ProcessFormSubmissionAction();
            $result = $processFormAction->execute(
                $encounter->id,
                $request->input('form_data', [])
            );
            
            return $this->success(
                'Form submitted successfully',
                [
                    'encounter' => new EncounterResource($result['encounter']),
                    'observations' => ObservationResource::collection($result['observations']),
                    'observations_created' => $result['observations_created'],
                    'form_data' => $result['form_data'],
                    'validated_data' => $result['validated_data'],
                    'validation_summary' => $result['validation_summary'],
                    'observation_summary' => $result['observation_summary'],
                    'form_completion_tracked' => $result['form_completion_tracked'],
                ]
            );
        } catch (\Exception $e) {
            return $this->failure(
                'Failed to submit form: ' . $e->getMessage(),
                400
            );
        }
    }

    /**
     * Transfer patient (create transfer encounter) - Legacy method
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
     * Transfer patient between departments with comprehensive tracking
     */
    public function transferPatient(TransferPatientRequest $request): JsonResponse
    {
        try {
            $transferAction = new TransferPatientAction();
            $result = $transferAction->execute(
                $request->input('visit_id'),
                $request->validated()
            );
            
            $response = [
                'transfer_encounter' => new EncounterResource($result['transfer_encounter']),
                'destination_encounter' => $result['destination_encounter'] 
                    ? new EncounterResource($result['destination_encounter']) 
                    : null,
                'transfer_details' => [
                    'destination_department' => $result['destination_department'] ? [
                        'id' => $result['destination_department']->id,
                        'name' => $result['destination_department']->name,
                        'code' => $result['destination_department']->code,
                    ] : null,
                    'destination_room' => $result['destination_room'] ? [
                        'id' => $result['destination_room']->id,
                        'name' => $result['destination_room']->name,
                        'code' => $result['destination_room']->code,
                    ] : null,
                    'transfer_at' => $result['transfer_at']->toISOString(),
                    'reason' => $result['reason'],
                    'active_encounters_ended' => $result['active_encounters_ended'],
                ]
            ];
            
            return $this->success(
                'Patient transferred successfully',
                $response
            );
        } catch (\Exception $e) {
            return $this->failure(
                'Failed to transfer patient: ' . $e->getMessage(),
                400
            );
        }
    }

    /**
     * Get chronological encounters for a visit with enhanced details
     */
    public function chronological(Visit $visit): JsonResponse
    {
        $encounters = $visit->encounters()
            ->with([
                'encounterType', 
                'clinicalFormTemplate',
                'observations' => function ($query) {
                    $query->with(['observationConcept', 'observationStatus'])
                          ->orderBy('observed_at');
                }
            ])
            ->orderBy('started_at')
            ->get();

        $timeline = $encounters->map(function ($encounter) {
            $vitalSigns = $encounter->observations->filter(function ($observation) {
                return $observation->observationConcept && 
                       str_contains(strtolower($observation->observationConcept->name ?? ''), 'vital');
            });

            return [
                'id' => $encounter->id,
                'ulid' => $encounter->ulid,
                'type' => $encounter->encounterType->name ?? 'Unknown',
                'type_code' => $encounter->encounterType->code ?? null,
                'started_at' => $encounter->started_at?->toISOString(),
                'ended_at' => $encounter->ended_at?->toISOString(),
                'duration_minutes' => $encounter->duration_minutes,
                'is_active' => $encounter->is_active,
                'status' => $encounter->status,
                'is_new' => $encounter->is_new,
                'clinical_form' => $encounter->clinicalFormTemplate ? [
                    'id' => $encounter->clinicalFormTemplate->id,
                    'name' => $encounter->clinicalFormTemplate->name,
                    'title' => $encounter->clinicalFormTemplate->title,
                    'category' => $encounter->clinicalFormTemplate->category,
                ] : null,
                'observations_summary' => [
                    'total_count' => $encounter->observations->count(),
                    'vital_signs_count' => $vitalSigns->count(),
                    'latest_observation_at' => $encounter->observations->max('observed_at')?->toISOString(),
                    'has_form_data' => $encounter->observations->isNotEmpty(),
                ],
                'key_observations' => $vitalSigns->take(5)->map(function ($obs) {
                    return [
                        'concept' => $obs->observationConcept->name ?? 'Unknown',
                        'value' => $obs->formatted_value,
                        'observed_at' => $obs->observed_at?->toISOString(),
                    ];
                })->values(),
            ];
        });

        // Calculate visit statistics
        $totalDuration = $encounters->sum('duration_minutes');
        $activeEncounters = $encounters->where('ended_at', null);
        $completedEncounters = $encounters->where('ended_at', '!=', null);

        return $this->success(
            'Chronological encounters retrieved successfully',
            [
                'visit_id' => $visit->id,
                'patient_id' => $visit->patient_id,
                'visit_status' => $visit->is_active ? 'active' : 'completed',
                'encounters' => $timeline,
                'summary' => [
                    'total_encounters' => $encounters->count(),
                    'active_encounters' => $activeEncounters->count(),
                    'completed_encounters' => $completedEncounters->count(),
                    'total_duration_minutes' => $totalDuration,
                    'total_observations' => $encounters->sum(fn($e) => $e->observations->count()),
                    'first_encounter_at' => $encounters->min('started_at')?->toISOString(),
                    'latest_encounter_at' => $encounters->max('started_at')?->toISOString(),
                ],
            ]
        );
    }
}