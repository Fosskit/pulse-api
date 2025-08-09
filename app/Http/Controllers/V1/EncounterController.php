<?php

namespace App\Http\Controllers\V1;

use App\Http\Requests\Encounter\{StoreEncounterRequest, UpdateEncounterRequest};
use App\Http\Resources\EncounterResource;
use App\Models\{Encounter, Observation, Visit};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\{AllowedFilter, QueryBuilder};

class EncounterController extends BaseController
{
    public function index(Request $request)
    {
        $encounters = QueryBuilder::for(Encounter::class)
            ->allowedFilters([
                'code',
                AllowedFilter::exact('visit_id'),
                AllowedFilter::exact('encounter_type_id'),
                AllowedFilter::exact('case_type_id'),
                AllowedFilter::exact('clinical_form_template_id'),
                AllowedFilter::scope('today'),
                AllowedFilter::scope('this_week'),
            ])
            ->allowedSorts(['started_at', 'ended_at', 'created_at'])
            ->allowedIncludes([
                'visit.patient', 'clinicalFormTemplate', 'encounterType',
                'caseType', 'observations'
            ])
            ->with(['visit.patient', 'clinicalFormTemplate'])
            ->withCount('observations')
            ->defaultSort('-started_at')
            ->paginate($request->get('per_page', 15));

        return $this->paginatedResponse($encounters, 'Encounters retrieved successfully');
    }

    public function store(StoreEncounterRequest $request)
    {
        try {
            DB::beginTransaction();

            $encounterData = $request->validated();
            $encounterData['code'] = $this->generateEncounterCode();
            $encounterData['started_at'] = now();
            $encounterData['created_by'] = auth()->id();
            $encounterData['updated_by'] = auth()->id();

            $encounter = Encounter::create($encounterData);

            // Process form data and create observations
            if ($request->has('form_data')) {
                $this->processFormData($encounter, $request->form_data);
            }

            // Complete encounter
            $encounter->update(['ended_at' => now()]);

            DB::commit();

            $encounter->load(['visit.patient', 'clinicalFormTemplate', 'observations']);

            return $this->successResponse(
                new EncounterResource($encounter),
                'Encounter created successfully',
                201
            );

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to create encounter: ' . $e->getMessage(), 500);
        }
    }

    public function show(Encounter $encounter)
    {
        $encounter->load([
            'visit.patient.nationality',
            'clinicalFormTemplate',
            'encounterType',
            'caseType',
            'observations.concept',
            'createdBy'
        ]);

        return $this->successResponse(
            new EncounterResource($encounter),
            'Encounter retrieved successfully'
        );
    }

    public function update(UpdateEncounterRequest $request, Encounter $encounter)
    {
        try {
            DB::beginTransaction();

            $encounterData = $request->validated();
            $encounterData['updated_by'] = auth()->id();

            $encounter->update($encounterData);

            // Update observations if form data provided
            if ($request->has('form_data')) {
                // Delete existing observations
                $encounter->observations()->delete();
                // Create new observations
                $this->processFormData($encounter, $request->form_data);
            }

            DB::commit();

            $encounter->load(['visit.patient', 'clinicalFormTemplate', 'observations']);

            return $this->successResponse(
                new EncounterResource($encounter),
                'Encounter updated successfully'
            );

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to update encounter: ' . $e->getMessage(), 500);
        }
    }

    public function destroy(Encounter $encounter)
    {
        try {
            DB::beginTransaction();

            // Delete all observations first
            $encounter->observations()->delete();

            // Delete the encounter
            $encounter->delete();

            DB::commit();

            return $this->successResponse(null, 'Encounter deleted successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to delete encounter: ' . $e->getMessage(), 500);
        }
    }

    public function observations(Encounter $encounter)
    {
        $observations = $encounter->observations()
            ->with(['concept', 'createdBy'])
            ->orderBy('created_at')
            ->get()
            ->groupBy('observation_concept_id')
            ->map(function ($obsGroup) {
                $first = $obsGroup->first();
                return [
                    'concept_id' => $first->observation_concept_id,
                    'concept_name' => $first->concept->name ?? 'Unknown',
                    'values' => $obsGroup->map(function ($obs) {
                        return [
                            'id' => $obs->id,
                            'value_string' => $obs->value_string,
                            'value_number' => $obs->value_number,
                            'value_text' => $obs->value_text,
                            'value_datetime' => $obs->value_datetime,
                            'observed_at' => $obs->observed_at,
                            'created_by' => $obs->createdBy->name ?? 'System'
                        ];
                    })
                ];
            })
            ->values();

        return $this->successResponse([
            'encounter' => new EncounterResource($encounter),
            'observations' => $observations,
            'summary' => [
                'total_observations' => $encounter->observations()->count(),
                'concepts_recorded' => $observations->count(),
                'completion_time' => $encounter->started_at->diffInMinutes($encounter->ended_at ?? now()) . ' minutes'
            ]
        ], 'Encounter observations retrieved');
    }

    public function statistics()
    {
        $stats = [
            'total_encounters' => Encounter::count(),
            'today_encounters' => Encounter::whereDate('created_at', today())->count(),
            'this_week_encounters' => Encounter::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'avg_completion_time' => $this->getAverageCompletionTime(),
            'by_form_type' => Encounter::with('clinicalFormTemplate')
                ->selectRaw('clinical_form_template_id, COUNT(*) as count')
                ->groupBy('clinical_form_template_id')
                ->get(),
            'by_user' => Encounter::with('createdBy')
                ->selectRaw('created_by, COUNT(*) as count')
                ->groupBy('created_by')
                ->get(),
            'completion_trends' => $this->getCompletionTrends(),
        ];

        return $this->successResponse($stats, 'Encounter statistics retrieved');
    }

    // Private helper methods
    private function generateEncounterCode(): string
    {
        return 'ENC' . now()->format('Ymd') . str_pad(Encounter::count() + 1, 4, '0', STR_PAD_LEFT);
    }

    private function processFormData(Encounter $encounter, array $formData): void
    {
        $template = $encounter->clinicalFormTemplate;
        $conceptMapping = $this->getConceptMapping($template->category);

        foreach ($formData as $fieldId => $value) {
            if (empty($value) || in_array($fieldId, ['encounter_type_id', 'case_type_id', 'encounter_notes'])) {
                continue;
            }

            $conceptId = $conceptMapping[$fieldId] ?? $this->getGenericConceptId($fieldId);

            $observationData = [
                'encounter_id' => $encounter->id,
                'patient_id' => $encounter->visit->patient_id,
                'observation_concept_id' => $conceptId,
                'observation_status_id' => 1, // Final
                'observed_at' => now(),
                'observed_by' => auth()->id(),
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ];

            // Determine value type and set appropriate field
            if (is_numeric($value)) {
                $observationData['value_number'] = floatval($value);
            } elseif (strlen($value) <= 190) {
                $observationData['value_string'] = $value;
            } else {
                $observationData['value_text'] = $value;
            }

            Observation::create($observationData);
        }

        // Add encounter notes if provided
        if (!empty($formData['encounter_notes'])) {
            Observation::create([
                'encounter_id' => $encounter->id,
                'patient_id' => $encounter->visit->patient_id,
                'observation_concept_id' => 999, // Notes concept
                'observation_status_id' => 1,
                'value_text' => $formData['encounter_notes'],
                'observed_at' => now(),
                'observed_by' => auth()->id(),
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);
        }
    }

    private function getConceptMapping(string $category): array
    {
        return match($category) {
            'vital-signs' => [
                'temperature' => 1,
                'systolic_bp' => 2,
                'diastolic_bp' => 3,
                'heart_rate' => 4,
                'respiratory_rate' => 5,
                'oxygen_saturation' => 6,
                'height' => 7,
                'weight' => 8,
                'pain_scale' => 9,
            ],
            'physical-exam' => [
                'general_appearance' => 10,
                'head_neck' => 11,
                'chest_lungs' => 12,
                'cardiovascular' => 13,
                'abdomen' => 14,
                'extremities' => 15,
            ],
            'medical-history' => [
                'chief_complaint' => 16,
                'history_present_illness' => 17,
                'past_medical_history' => 18,
                'medications' => 19,
                'allergies' => 20,
                'social_history' => 21,
                'family_history' => 22,
            ],
            default => []
        };
    }

    private function getGenericConceptId(string $fieldId): int
    {
        // Generate a concept ID based on field name hash
        return 1000 + (crc32($fieldId) % 8999); // Range 1000-9999
    }

    private function getAverageCompletionTime(): string
    {
        $avgMinutes = Encounter::whereNotNull('ended_at')
            ->whereBetween('created_at', [now()->subDays(30), now()])
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, started_at, ended_at)) as avg_minutes')
            ->value('avg_minutes');

        return $avgMinutes ? round($avgMinutes, 1) . ' minutes' : '0 minutes';
    }

    private function getCompletionTrends(): array
    {
        $trends = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $trends[] = [
                'date' => $date->format('Y-m-d'),
                'encounters' => Encounter::whereDate('created_at', $date)->count(),
                'avg_time' => Encounter::whereDate('created_at', $date)
                    ->whereNotNull('ended_at')
                    ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, started_at, ended_at)) as avg_minutes')
                    ->value('avg_minutes') ?? 0
            ];
        }
        return $trends;
    }
}
