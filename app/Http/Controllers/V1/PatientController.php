<?php

namespace App\Http\Controllers\V1;

use App\Http\Requests\Patient\{StorePatientRequest, UpdatePatientRequest};
use App\Http\Resources\PatientResource;
use App\Models\{Patient, PatientAddress, PatientIdentity};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\{AllowedFilter, AllowedSort, QueryBuilder};

class PatientController extends BaseController
{
    public function index(Request $request)
    {
        $patients = QueryBuilder::for(Patient::class)
            ->allowedFilters([
                'name', 'surname', 'code', 'phone',
                AllowedFilter::exact('sex'),
                AllowedFilter::exact('nationality_id'),
                AllowedFilter::exact('facility_id'),
                AllowedFilter::scope('age_between'),
                AllowedFilter::scope('has_active_visit'),
                AllowedFilter::scope('created_today'),
                AllowedFilter::scope('has_condition')
            ])
            ->allowedSorts([
                'created_at', 'updated_at', 'name', 'surname',
                'birthdate', AllowedSort::field('age')
            ])
            ->allowedIncludes([
                'nationality', 'facility', 'addresses', 'identities',
                'visits', 'disabilities'
            ])
            ->with(['nationality', 'facility'])
            ->defaultSort('-created_at')
            ->paginate($request->get('per_page', 15));

        return $this->paginatedResponse($patients, 'Patients retrieved successfully');
    }

    public function store(StorePatientRequest $request)
    {
        try {
            DB::beginTransaction();

            $patientData = $request->validated();
            $patientData['code'] = $this->generatePatientCode();
            $patientData['created_by'] = auth()->id();
            $patientData['updated_by'] = auth()->id();

            $patient = Patient::create($patientData);

            // Create address if provided
            if ($request->has('address')) {
                $this->createPatientAddress($patient, $request->address);
            }

            // Create identity documents if provided
            if ($request->has('identities')) {
                $this->createPatientIdentities($patient, $request->identities);
            }

//            // Attach disabilities if provided
//            if ($request->has('disabilities')) {
//                $patient->disabilities()->attach($request->disabilities);
//            }

            DB::commit();

            $patient->load(['nationality', 'facility', 'addresses', 'identities']);

            return $this->successResponse(
                new PatientResource($patient),
                'Patient created successfully',
                201
            );

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to create patient: ' . $e->getMessage(), 500);
        }
    }

    public function show(Patient $patient)
    {
        $patient->load([
            'nationality', 'facility', 'addresses.province', 'addresses.district',
            'addresses.commune', 'addresses.village', 'identities.cardType',
            'visits.visitType', 'visits.admissionType', 'visits.encounters.clinicalFormTemplate',
            'disabilities'
        ]);

        return $this->successResponse(
            new PatientResource($patient),
            'Patient retrieved successfully'
        );
    }

    public function update(UpdatePatientRequest $request, Patient $patient)
    {
        try {
            DB::beginTransaction();

            $patientData = $request->validated();
            $patientData['updated_by'] = auth()->id();

            $patient->update($patientData);

            // Update address if provided
            if ($request->has('address')) {
                $this->updatePatientAddress($patient, $request->address);
            }

            // Update identities if provided
            if ($request->has('identities')) {
                $this->updatePatientIdentities($patient, $request->identities);
            }

            // Update disabilities if provided
            if ($request->has('disabilities')) {
                $patient->disabilities()->sync($request->disabilities);
            }

            DB::commit();

            $patient->load(['nationality', 'facility', 'addresses', 'identities']);

            return $this->successResponse(
                new PatientResource($patient),
                'Patient updated successfully'
            );

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to update patient: ' . $e->getMessage(), 500);
        }
    }

    public function destroy(Patient $patient)
    {
        try {
            // Check if patient has active visits
            if ($patient->visits()->whereNull('discharged_at')->exists()) {
                return $this->errorResponse(
                    'Cannot delete patient with active visits. Please discharge first.',
                    422
                );
            }

            $patient->delete();

            return $this->successResponse(null, 'Patient deleted successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete patient: ' . $e->getMessage(), 500);
        }
    }

    public function search(Request $request, string $query)
    {
        $patients = Patient::where('name', 'like', "%{$query}%")
            ->orWhere('surname', 'like', "%{$query}%")
            ->orWhere('code', 'like', "%{$query}%")
            ->orWhere('phone', 'like', "%{$query}%")
            ->with([
                'nationality', 'facility',
                'visits' => fn($q) => $q->latest()->whereNull('discharged_at')->take(1)
            ])
            ->limit(10)
            ->get()
            ->map(function ($patient) {
                return [
                    'id' => $patient->id,
                    'name' => $patient->surname . ' ' . $patient->name,
                    'code' => $patient->code,
                    'sex' => $patient->sex,
                    'age' => $patient->birthdate ? now()->diffInYears($patient->birthdate) : null,
                    'phone' => $patient->phone,
                    'nationality' => $patient->nationality?->name,
                    'facility' => $patient->facility?->name,
                    'initials' => substr($patient->surname, 0, 1) . substr($patient->name, 0, 1),
                    'has_active_visit' => $patient->visits->isNotEmpty(),
                    'last_visit' => $patient->visits->first()?->admitted_at?->diffForHumans(),
                ];
            });

        return $this->successResponse($patients, 'Search results retrieved');
    }

    public function summary(Patient $patient)
    {
        $currentVisit = $patient->visits()->whereNull('discharged_at')->first();

        return $this->successResponse([
            'patient' => new PatientResource($patient->load(['nationality', 'facility'])),
            'current_visit' => $currentVisit ? [
                'id' => $currentVisit->id,
                'code' => $currentVisit->code,
                'admitted_at' => $currentVisit->admitted_at,
                'visit_type' => $currentVisit->visitType->name ?? 'Unknown',
                'chief_complaint' => $this->getChiefComplaint($currentVisit),
                'duration' => $currentVisit->admitted_at->diffForHumans(null, true),
                'forms_completed' => $currentVisit->encounters->count(),
            ] : null,
            'latest_vitals' => $this->getLatestVitals($patient),
            'allergies' => $this->getAllergies($patient),
            'medications' => $this->getCurrentMedications($patient),
            'medical_history' => $this->getMedicalHistory($patient),
            'recent_visits' => $this->getRecentVisits($patient),
        ], 'Patient summary retrieved');
    }

    public function statistics()
    {
        $stats = [
            'total_patients' => Patient::count(),
            'new_today' => Patient::whereDate('created_at', today())->count(),
            'new_this_week' => Patient::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'new_this_month' => Patient::whereMonth('created_at', now()->month)->count(),
            'active_patients' => Patient::whereHas('visits', fn($q) => $q->whereNull('discharged_at'))->count(),
            'by_sex' => Patient::selectRaw('sex, COUNT(*) as count')->groupBy('sex')->get(),
            'by_age_group' => $this->getPatientsByAgeGroup(),
            'by_nationality' => Patient::with('nationality')
                ->selectRaw('nationality_id, COUNT(*) as count')
                ->groupBy('nationality_id')
                ->get(),
        ];

        return $this->successResponse($stats, 'Patient statistics retrieved');
    }

    // Private helper methods
    private function generatePatientCode(): string
    {
        return 'PT' . now()->format('Ymd') . str_pad(Patient::count() + 1, 3, '0', STR_PAD_LEFT);
    }

    private function createPatientAddress(Patient $patient, array $addressData): void
    {
        $addressData['patient_id'] = $patient->id;
        $addressData['is_current'] = true;
        $addressData['created_by'] = auth()->id();
        $addressData['updated_by'] = auth()->id();

        PatientAddress::create($addressData);
    }

    private function createPatientIdentities(Patient $patient, array $identitiesData): void
    {
        foreach ($identitiesData as $identityData) {
            $identityData['patient_id'] = $patient->id;
            PatientIdentity::create($identityData);
        }
    }

    private function updatePatientAddress(Patient $patient, array $addressData): void
    {
        $patient->addresses()->where('is_current', true)->update(['is_current' => false]);
        $this->createPatientAddress($patient, $addressData);
    }

    private function updatePatientIdentities(Patient $patient, array $identitiesData): void
    {
        $patient->identities()->delete();
        $this->createPatientIdentities($patient, $identitiesData);
    }

    private function getChiefComplaint($visit): string
    {
        return $visit->encounters()
            ->whereHas('observations', fn($q) => $q->where('observation_concept_id', 16))
            ->with('observations')
            ->first()
            ?->observations
            ->where('observation_concept_id', 16)
            ->first()
            ?->value_text ?? 'Not recorded';
    }

    private function getLatestVitals($patient): ?array
    {
        $latestEncounter = $patient->visits()
            ->with(['encounters' => fn($q) => $q->whereHas('clinicalFormTemplate',
                fn($q2) => $q2->where('category', 'vital-signs'))->latest()])
            ->first()
            ?->encounters
            ->first();

        if (!$latestEncounter) return null;

        $vitals = $latestEncounter->observations()
            ->whereIn('observation_concept_id', [1, 2, 3, 4, 5, 6])
            ->get()
            ->keyBy('observation_concept_id');

        return [
            'recorded_at' => $latestEncounter->created_at,
            'temperature' => $vitals->get(1)?->value_number,
            'systolic_bp' => $vitals->get(2)?->value_number,
            'diastolic_bp' => $vitals->get(3)?->value_number,
            'heart_rate' => $vitals->get(4)?->value_number,
            'respiratory_rate' => $vitals->get(5)?->value_number,
            'oxygen_saturation' => $vitals->get(6)?->value_number,
        ];
    }

    private function getAllergies($patient): array
    {
        // Implementation based on your allergy storage method
        return ['Penicillin', 'Shellfish']; // Placeholder
    }

    private function getCurrentMedications($patient): array
    {
        // Implementation based on your medication storage method
        return [
            'Metformin 500mg BID',
            'Lisinopril 10mg daily'
        ]; // Placeholder
    }

    private function getMedicalHistory($patient): array
    {
        // Implementation based on your medical history storage
        return [
            'Diabetes Type 2 (2018)',
            'Hypertension (2020)'
        ]; // Placeholder
    }

    private function getRecentVisits($patient): array
    {
        return $patient->visits()
            ->with(['visitType', 'encounters'])
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($visit) {
                return [
                    'id' => $visit->id,
                    'code' => $visit->code,
                    'visit_type' => $visit->visitType->name ?? 'Unknown',
                    'admitted_at' => $visit->admitted_at,
                    'discharged_at' => $visit->discharged_at,
                    'duration' => $visit->discharged_at
                        ? $visit->admitted_at->diffInHours($visit->discharged_at) . 'h'
                        : $visit->admitted_at->diffForHumans(null, true),
                    'forms_completed' => $visit->encounters->count(),
                    'status' => $visit->discharged_at ? 'Completed' : 'Active'
                ];
            })
            ->toArray();
    }

    private function getPatientsByAgeGroup(): array
    {
        return [
            '0-17' => Patient::whereRaw('TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 0 AND 17')->count(),
            '18-29' => Patient::whereRaw('TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 18 AND 29')->count(),
            '30-49' => Patient::whereRaw('TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 30 AND 49')->count(),
            '50-69' => Patient::whereRaw('TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 50 AND 69')->count(),
            '70+' => Patient::whereRaw('TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) >= 70')->count(),
        ];
    }
}
