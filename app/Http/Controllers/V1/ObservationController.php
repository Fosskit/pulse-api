<?php

namespace App\Http\Controllers\V1;

use App\Http\Requests\Observation\{StoreObservationRequest, UpdateObservationRequest};
use App\Http\Resources\ObservationResource;
use App\Models\{Observation, Patient};
use Illuminate\Http\Request;
use Spatie\QueryBuilder\{AllowedFilter, QueryBuilder};

class ObservationController extends BaseController
{
    public function index(Request $request)
    {
        $observations = QueryBuilder::for(Observation::class)
            ->allowedFilters([
                AllowedFilter::exact('patient_id'),
                AllowedFilter::exact('encounter_id'),
                AllowedFilter::exact('observation_concept_id'),
                AllowedFilter::exact('observation_status_id'),
                AllowedFilter::scope('vital_signs'),
                AllowedFilter::scope('critical_values'),
                AllowedFilter::scope('today'),
            ])
            ->allowedSorts(['observed_at', 'created_at', 'value_number'])
            ->allowedIncludes(['patient', 'encounter', 'concept', 'observedBy'])
            ->with(['patient', 'encounter.clinicalFormTemplate', 'observedBy'])
            ->defaultSort('-observed_at')
            ->paginate($request->get('per_page', 15));

        return $this->paginatedResponse($observations, 'Observations retrieved successfully');
    }

    public function store(StoreObservationRequest $request)
    {
        try {
            $observationData = $request->validated();
            $observationData['observed_at'] = now();
            $observationData['observed_by'] = auth()->id();
            $observationData['created_by'] = auth()->id();
            $observationData['updated_by'] = auth()->id();

            $observation = Observation::create($observationData);

            // Check for critical values and trigger alerts
            $this->checkCriticalValues($observation);

            $observation->load(['patient', 'encounter', 'observedBy']);

            return $this->successResponse(
                new ObservationResource($observation),
                'Observation created successfully',
                201
            );

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create observation: ' . $e->getMessage(), 500);
        }
    }

    public function show(Observation $observation)
    {
        $observation->load(['patient', 'encounter.clinicalFormTemplate', 'concept', 'observedBy']);

        return $this->successResponse(
            new ObservationResource($observation),
            'Observation retrieved successfully'
        );
    }

    public function update(UpdateObservationRequest $request, Observation $observation)
    {
        try {
            $observationData = $request->validated();
            $observationData['updated_by'] = auth()->id();

            $observation->update($observationData);

            // Check for critical values after update
            $this->checkCriticalValues($observation);

            $observation->load(['patient', 'encounter', 'observedBy']);

            return $this->successResponse(
                new ObservationResource($observation),
                'Observation updated successfully'
            );

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update observation: ' . $e->getMessage(), 500);
        }
    }

    public function destroy(Observation $observation)
    {
        try {
            $observation->delete();

            return $this->successResponse(null, 'Observation deleted successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete observation: ' . $e->getMessage(), 500);
        }
    }

    public function patientVitals(Patient $patient)
    {
        $vitals = $patient->observations()
            ->whereIn('observation_concept_id', [1, 2, 3, 4, 5, 6]) // Vital signs concepts
            ->with(['encounter.clinicalFormTemplate', 'observedBy'])
            ->orderBy('observed_at', 'desc')
            ->take(50)
            ->get()
            ->groupBy('observation_concept_id')
            ->map(function ($observations, $conceptId) {
                $conceptNames = [
                    1 => 'Temperature (°C)',
                    2 => 'Systolic BP (mmHg)',
                    3 => 'Diastolic BP (mmHg)',
                    4 => 'Heart Rate (bpm)',
                    5 => 'Respiratory Rate (/min)',
                    6 => 'Oxygen Saturation (%)',
                ];

                return [
                    'concept_id' => $conceptId,
                    'concept_name' => $conceptNames[$conceptId] ?? 'Unknown',
                    'latest_value' => $observations->first()->value_number,
                    'latest_observed_at' => $observations->first()->observed_at,
                    'trend_data' => $observations->take(10)->map(function ($obs) {
                        return [
                            'value' => $obs->value_number,
                            'observed_at' => $obs->observed_at,
                            'encounter_type' => $obs->encounter->clinicalFormTemplate->title ?? 'Unknown'
                        ];
                    })->reverse()->values(),
                    'is_critical' => $this->isCriticalValue($conceptId, $observations->first()->value_number)
                ];
            })
            ->values();

        return $this->successResponse([
            'patient' => [
                'id' => $patient->id,
                'name' => $patient->surname . ' ' . $patient->name,
                'code' => $patient->code
            ],
            'vitals' => $vitals,
            'summary' => [
                'total_observations' => $patient->observations()->count(),
                'last_updated' => $patient->observations()->latest('observed_at')->first()?->observed_at,
                'critical_count' => $vitals->where('is_critical', true)->count()
            ]
        ], 'Patient vitals retrieved successfully');
    }

    public function criticalValues()
    {
        $criticalObservations = Observation::where('created_at', '>=', now()->subHours(24))
            ->where(function ($query) {
                $query->where(function ($q) {
                    // High blood pressure
                    $q->where('observation_concept_id', 2)->where('value_number', '>', 140);
                })->orWhere(function ($q) {
                    // Low oxygen saturation
                    $q->where('observation_concept_id', 6)->where('value_number', '<', 95);
                })->orWhere(function ($q) {
                    // High temperature
                    $q->where('observation_concept_id', 1)->where('value_number', '>', 38.5);
                })->orWhere(function ($q) {
                    // High heart rate
                    $q->where('observation_concept_id', 4)->where('value_number', '>', 120);
                });
            })
            ->with(['patient', 'encounter', 'observedBy'])
            ->orderBy('observed_at', 'desc')
            ->get()
            ->map(function ($obs) {
                return [
                    'id' => $obs->id,
                    'patient' => [
                        'id' => $obs->patient->id,
                        'name' => $obs->patient->surname . ' ' . $obs->patient->name,
                        'code' => $obs->patient->code
                    ],
                    'concept_name' => $this->getConceptName($obs->observation_concept_id),
                    'value' => $obs->value_number,
                    'unit' => $this->getConceptUnit($obs->observation_concept_id),
                    'observed_at' => $obs->observed_at,
                    'observed_by' => $obs->observedBy->name ?? 'System',
                    'severity' => $this->getCriticalSeverity($obs->observation_concept_id, $obs->value_number),
                    'encounter_type' => $obs->encounter->clinicalFormTemplate->title ?? 'Unknown'
                ];
            });

        return $this->successResponse([
            'critical_observations' => $criticalObservations,
            'summary' => [
                'total_critical' => $criticalObservations->count(),
                'by_severity' => $criticalObservations->groupBy('severity')->map->count(),
                'unique_patients' => $criticalObservations->pluck('patient.id')->unique()->count()
            ]
        ], 'Critical values retrieved successfully');
    }

    // Private helper methods
    private function checkCriticalValues(Observation $observation): void
    {
        if ($this->isCriticalValue($observation->observation_concept_id, $observation->value_number)) {
            // Log critical value alert
            activity()
                ->performedOn($observation)
                ->causedBy(auth()->user())
                ->withProperties([
                    'concept' => $this->getConceptName($observation->observation_concept_id),
                    'value' => $observation->value_number,
                    'patient_id' => $observation->patient_id
                ])
                ->log('Critical value recorded');

            // Here you could trigger notifications, emails, etc.
        }
    }

    private function isCriticalValue(int $conceptId, ?float $value): bool
    {
        if (!$value) return false;

        return match($conceptId) {
            1 => $value > 38.5 || $value < 35.0, // Temperature
            2 => $value > 140 || $value < 90,   // Systolic BP
            3 => $value > 90 || $value < 60,    // Diastolic BP
            4 => $value > 120 || $value < 50,   // Heart Rate
            5 => $value > 30 || $value < 12,    // Respiratory Rate
            6 => $value < 95,                   // Oxygen Saturation
            default => false
        };
    }

    private function getCriticalSeverity(int $conceptId, ?float $value): string
    {
        if (!$value) return 'normal';

        return match($conceptId) {
            1 => $value > 40 || $value < 34 ? 'critical' : 'warning', // Temperature
            2 => $value > 180 || $value < 80 ? 'critical' : 'warning', // Systolic BP
            3 => $value > 110 || $value < 50 ? 'critical' : 'warning', // Diastolic BP
            4 => $value > 150 || $value < 40 ? 'critical' : 'warning', // Heart Rate
            5 => $value > 35 || $value < 10 ? 'critical' : 'warning', // Respiratory Rate
            6 => $value < 85 ? 'critical' : 'warning', // Oxygen Saturation
            default => 'normal'
        };
    }

    private function getConceptName(int $conceptId): string
    {
        return match($conceptId) {
            1 => 'Temperature',
            2 => 'Systolic Blood Pressure',
            3 => 'Diastolic Blood Pressure',
            4 => 'Heart Rate',
            5 => 'Respiratory Rate',
            6 => 'Oxygen Saturation',
            7 => 'Height',
            8 => 'Weight',
                        9 => 'Pain Scale',
            10 => 'General Appearance',
            11 => 'Head & Neck',
            12 => 'Chest & Lungs',
            13 => 'Cardiovascular',
            14 => 'Abdomen',
            15 => 'Extremities',
            16 => 'Chief Complaint',
            17 => 'History of Present Illness',
            18 => 'Past Medical History',
            19 => 'Current Medications',
            20 => 'Allergies',
            21 => 'Social History',
            22 => 'Family History',
            999 => 'Clinical Notes',
            default => 'Unknown Concept'
        };
    }

    private function getConceptUnit(int $conceptId): string
    {
        return match($conceptId) {
            1 => '°C',
            2, 3 => 'mmHg',
            4 => 'bpm',
            5 => '/min',
            6 => '%',
            7 => 'cm',
            8 => 'kg',
            9 => '/10',
            default => ''
        };
    }
}
