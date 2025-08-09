<?php

namespace App\Http\Controllers\V1;

use App\Http\Requests\Visit\{DischargeVisitRequest, StoreVisitRequest, UpdateVisitRequest};
use App\Http\Resources\VisitResource;
use App\Models\{Visit, VisitCaretaker, VisitSubject};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\{AllowedFilter, AllowedSort, QueryBuilder};

class VisitController extends BaseController
{
    public function index(Request $request)
    {
        $visits = QueryBuilder::for(Visit::class)
            ->allowedFilters([
                'code',
                AllowedFilter::exact('patient_id'),
                AllowedFilter::exact('health_facility_id'),
                AllowedFilter::exact('visit_type_id'),
                AllowedFilter::exact('admission_type_id'),
                AllowedFilter::scope('active'),
                AllowedFilter::scope('discharged'),
                AllowedFilter::scope('today'),
                AllowedFilter::scope('this_week'),
                AllowedFilter::scope('emergency'),
            ])
            ->allowedSorts([
                'admitted_at', 'discharged_at', 'created_at',
                AllowedSort::field('duration'),
                AllowedSort::field('patient_name')
            ])
            ->allowedIncludes([
                'patient', 'visitType', 'admissionType', 'dischargeType',
                'facility', 'encounters', 'caretakers'
            ])
            ->with(['patient', 'visitType', 'facility'])
            ->defaultSort('-admitted_at')
            ->paginate($request->get('per_page', 15));

        return $this->paginatedResponse($visits, 'Visits retrieved successfully');
    }

    public function store(StoreVisitRequest $request)
    {
        try {
            DB::beginTransaction();

            $visitData = $request->validated();
            $visitData['code'] = $this->generateVisitCode();
            $visitData['created_by'] = auth()->id();
            $visitData['updated_by'] = auth()->id();

            $visit = Visit::create($visitData);

            // Create caretaker if provided
            if ($request->has('caretaker')) {
                $this->createCaretaker($visit, $request->caretaker);
            }

            // Create visit subject if provided
            if ($request->has('subject')) {
                $this->createVisitSubject($visit, $request->subject);
            }

            DB::commit();

            $visit->load(['patient', 'visitType', 'facility', 'caretakers']);

            return $this->successResponse(
                new VisitResource($visit),
                'Visit created successfully',
                201
            );

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to create visit: ' . $e->getMessage(), 500);
        }
    }

    public function show(Visit $visit)
    {
        $visit->load([
            'patient.nationality', 'patient.facility',
            'visitType', 'admissionType', 'dischargeType',
            'facility', 'encounters.clinicalFormTemplate',
            'caretakers', 'subject'
        ]);

        return $this->successResponse(
            new VisitResource($visit),
            'Visit retrieved successfully'
        );
    }

    public function update(UpdateVisitRequest $request, Visit $visit)
    {
        try {
            DB::beginTransaction();

            $visitData = $request->validated();
            $visitData['updated_by'] = auth()->id();

            $visit->update($visitData);

            // Update caretaker if provided
            if ($request->has('caretaker')) {
                $visit->caretakers()->delete();
                $this->createCaretaker($visit, $request->caretaker);
            }

            DB::commit();

            $visit->load(['patient', 'visitType', 'facility', 'caretakers']);

            return $this->successResponse(
                new VisitResource($visit),
                'Visit updated successfully'
            );

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to update visit: ' . $e->getMessage(), 500);
        }
    }

    public function destroy(Visit $visit)
    {
        try {
            // Check if visit has encounters
            if ($visit->encounters()->exists()) {
                return $this->errorResponse(
                    'Cannot delete visit with clinical data. Please remove encounters first.',
                    422
                );
            }

            $visit->delete();

            return $this->successResponse(null, 'Visit deleted successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete visit: ' . $e->getMessage(), 500);
        }
    }

    public function discharge(DischargeVisitRequest $request, Visit $visit)
    {
        try {
            if ($visit->discharged_at) {
                return $this->errorResponse('Patient is already discharged', 422);
            }

            $dischargeData = $request->validated();
            $dischargeData['updated_by'] = auth()->id();

            $visit->update($dischargeData);

            // Log discharge activity
            activity()
                ->performedOn($visit)
                ->causedBy(auth()->user())
                ->withProperties([
                    'discharge_type' => $visit->dischargeType->name ?? 'Unknown',
                    'duration' => $visit->admitted_at->diffForHumans($visit->discharged_at, true)
                ])
                ->log('Patient discharged');

            $visit->load(['patient', 'visitType', 'dischargeType']);

            return $this->successResponse(
                new VisitResource($visit),
                'Patient discharged successfully'
            );

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to discharge patient: ' . $e->getMessage(), 500);
        }
    }

    public function timeline(Visit $visit)
    {
        $timeline = collect();

        // Add admission event
        $timeline->push([
            'id' => 'admission',
            'type' => 'admission',
            'title' => 'Patient Admitted',
            'description' => 'Admitted to ' . ($visit->facility->name ?? 'Unknown Facility'),
            'timestamp' => $visit->admitted_at,
            'user' => $visit->createdBy->name ?? 'System',
            'icon' => 'sign-in-alt',
            'color' => 'blue'
        ]);

        // Add encounter events
        $visit->encounters()
            ->with(['clinicalFormTemplate', 'createdBy'])
            ->orderBy('started_at')
            ->get()
            ->each(function ($encounter) use ($timeline) {
                $timeline->push([
                    'id' => 'encounter-' . $encounter->id,
                    'type' => 'encounter',
                    'title' => $encounter->clinicalFormTemplate->title . ' Completed',
                    'description' => 'Clinical form filled by ' . ($encounter->createdBy->name ?? 'Unknown'),
                    'timestamp' => $encounter->started_at,
                    'user' => $encounter->createdBy->name ?? 'System',
                    'icon' => $this->getFormIcon($encounter->clinicalFormTemplate->category),
                    'color' => $this->getFormColor($encounter->clinicalFormTemplate->category),
                    'data' => [
                        'encounter_id' => $encounter->id,
                        'observations_count' => $encounter->observations()->count()
                    ]
                ]);
            });

        // Add discharge event if discharged
        if ($visit->discharged_at) {
            $timeline->push([
                'id' => 'discharge',
                'type' => 'discharge',
                'title' => 'Patient Discharged',
                'description' => 'Discharged ' . ($visit->dischargeType->name ?? 'home'),
                'timestamp' => $visit->discharged_at,
                'user' => $visit->updatedBy->name ?? 'System',
                'icon' => 'sign-out-alt',
                'color' => 'green'
            ]);
        }

        // Sort by timestamp
        $timeline = $timeline->sortBy('timestamp')->values();

        return $this->successResponse([
            'timeline' => $timeline,
            'visit_summary' => [
                'duration' => $visit->discharged_at
                    ? $visit->admitted_at->diffForHumans($visit->discharged_at, true)
                    : $visit->admitted_at->diffForHumans(null, true),
                'total_events' => $timeline->count(),
                'forms_completed' => $visit->encounters()->count(),
                'status' => $visit->discharged_at ? 'Discharged' : 'Active'
            ]
        ], 'Visit timeline retrieved');
    }

    public function statistics()
    {
        $stats = [
            'total_visits' => Visit::count(),
            'active_visits' => Visit::whereNull('discharged_at')->count(),
            'today_admissions' => Visit::whereDate('admitted_at', today())->count(),
            'today_discharges' => Visit::whereDate('discharged_at', today())->count(),
            'avg_stay_duration' => $this->getAverageStayDuration(),
            'by_visit_type' => Visit::with('visitType')
                ->selectRaw('visit_type_id, COUNT(*) as count')
                ->groupBy('visit_type_id')
                ->get(),
            'by_facility' => Visit::with('facility')
                ->selectRaw('health_facility_id, COUNT(*) as count')
                ->groupBy('health_facility_id')
                ->get(),
            'monthly_trends' => $this->getMonthlyTrends(),
        ];

        return $this->successResponse($stats, 'Visit statistics retrieved');
    }

    // Private helper methods
    private function generateVisitCode(): string
    {
        return 'V' . now()->format('Ymd') . str_pad(Visit::count() + 1, 4, '0', STR_PAD_LEFT);
    }

    private function createCaretaker(Visit $visit, array $caretakerData): void
    {
        $caretakerData['visit_id'] = $visit->id;
        $caretakerData['created_by'] = auth()->id();
        $caretakerData['updated_by'] = auth()->id();

        VisitCaretaker::create($caretakerData);
    }

    private function createVisitSubject(Visit $visit, array $subjectData): void
    {
        $subjectData['visit_id'] = $visit->id;
        $subjectData['created_by'] = auth()->id();
        $subjectData['updated_by'] = auth()->id();

        VisitSubject::create($subjectData);
    }

    private function getFormIcon(string $category): string
    {
        return match($category) {
            'vital-signs' => 'heartbeat',
            'physical-exam' => 'stethoscope',
            'laboratory' => 'vial',
            'medical-history' => 'history',
            'assessment' => 'clipboard-check',
            default => 'file-medical'
        };
    }

    private function getFormColor(string $category): string
    {
        return match($category) {
            'vital-signs' => 'green',
            'physical-exam' => 'blue',
            'laboratory' => 'purple',
            'medical-history' => 'yellow',
            'assessment' => 'red',
            default => 'gray'
        };
    }

    private function getAverageStayDuration(): string
    {
        $avgMinutes = Visit::whereNotNull('discharged_at')
            ->whereBetween('admitted_at', [now()->subDays(30), now()])
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, admitted_at, discharged_at)) as avg_minutes')
            ->value('avg_minutes');

        if (!$avgMinutes) return '0h';

        $hours = intval($avgMinutes / 60);
        $minutes = $avgMinutes % 60;

        return $hours . 'h ' . intval($minutes) . 'm';
    }

    private function getMonthlyTrends(): array
    {
        $trends = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $trends[] = [
                'month' => $date->format('Y-m'),
                'admissions' => Visit::whereYear('admitted_at', $date->year)
                    ->whereMonth('admitted_at', $date->month)
                    ->count(),
                'discharges' => Visit::whereYear('discharged_at', $date->year)
                    ->whereMonth('discharged_at', $date->month)
                    ->count()
            ];
        }
        return $trends;
    }
}
