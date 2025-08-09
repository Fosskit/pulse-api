<?php

namespace App\Http\Controllers\V1;

use App\Models\{ClinicalFormTemplate, Encounter, Observation, Patient, User, Visit};

class DashboardController extends BaseController
{
    public function stats()
    {
        $currentTime = now()->utc()->format('Y-m-d H:i:s');
        $currentUser = auth()->user()->name ?? 'kakadanhem';

        $stats = [
            'active_patients' => Visit::whereNull('discharged_at')->count(),
            'forms_today' => Encounter::whereDate('created_at', today())->count(),
            'avg_visit_time' => $this->getAverageVisitTime(),
            'critical_alerts' => $this->getCriticalAlerts(),
            'new_patients_today' => Patient::whereDate('created_at', today())->count(),
            'completed_discharges_today' => Visit::whereDate('discharged_at', today())->count(),
            'pending_forms' => $this->getPendingForms(),
            'system_status' => [
                'current_time' => $currentTime,
                'active_users' => $this->getActiveUsers(),
                'system_load' => 'Normal'
            ]
        ];

        return $this->successResponse($stats, 'Dashboard statistics retrieved successfully');
    }

    public function activePatients()
    {
        $activeVisits = Visit::with([
                'patient:id,surname,name,code,sex,birthdate',
                'visitType:id,name',
                'encounters.clinicalFormTemplate:id,title,category'
            ])
            ->whereNull('discharged_at')
            ->orderBy('admitted_at', 'desc')
            ->get()
            ->map(function ($visit) {
                $lastActivity = $visit->encounters->max('updated_at') ?? $visit->updated_at;

                return [
                    'id' => $visit->id,
                    'code' => $visit->code,
                    'patient' => [
                        'id' => $visit->patient->id,
                        'name' => $visit->patient->surname . ' ' . $visit->patient->name,
                        'code' => $visit->patient->code,
                        'sex' => $visit->patient->sex,
                        'age' => $visit->patient->birthdate ? now()->diffInYears($visit->patient->birthdate) : null,
                        'initials' => substr($visit->patient->surname, 0, 1) . substr($visit->patient->name, 0, 1),
                    ],
                    'visit_type' => $visit->visitType->name ?? 'Unknown',
                    'admitted_at' => $visit->admitted_at,
                    'duration' => $visit->admitted_at->diffForHumans(null, true),
                    'forms_completed' => $visit->encounters->count(),
                    'last_activity' => $lastActivity,
                    'last_activity_human' => $lastActivity->diffForHumans(),
                    'status_indicators' => $this->getStatusIndicators($visit),
                    'priority' => $this->getVisitPriority($visit),
                    'chief_complaint' => $this->getChiefComplaint($visit)
                ];
            });

        return $this->successResponse($activeVisits, 'Active patients retrieved successfully');
    }

    public function recentActivity()
    {
        // Get recent encounters
        $encounters = Encounter::with(['visit.patient', 'clinicalFormTemplate', 'createdBy'])
            ->latest()
            ->take(5)
            ->get();

        // Get recent admissions
        $admissions = Visit::with(['patient', 'visitType', 'createdBy'])
            ->latest('admitted_at')
            ->take(3)
            ->get();

        // Get recent discharges
        $discharges = Visit::with(['patient', 'dischargeType', 'updatedBy'])
            ->whereNotNull('discharged_at')
            ->latest('discharged_at')
            ->take(3)
            ->get();

        $activities = collect();

        // Add encounter activities
        $encounters->each(function ($encounter) use ($activities) {
            $activities->push([
                'id' => 'encounter-' . $encounter->id,
                'type' => 'form_completed',
                'title' => 'Clinical Form Completed',
                'message' => "{$encounter->visit->patient->surname} {$encounter->visit->patient->name} completed {$encounter->clinicalFormTemplate->title}",
                'timestamp' => $encounter->created_at,
                'user' => $encounter->createdBy->name ?? 'System',
                'patient' => [
                    'id' => $encounter->visit->patient->id,
                    'code' => $encounter->visit->patient->code,
                    'name' => $encounter->visit->patient->surname . ' ' . $encounter->visit->patient->name
                ],
                'icon' => 'document-text',
                'color' => 'green'
            ]);
        });

        // Add admission activities
        $admissions->each(function ($visit) use ($activities) {
            $treatment = $visit->visitType->name ?? 'treatment';
            $activities->push([
                'id' => 'admission-' . $visit->id,
                'type' => 'patient_admitted',
                'title' => 'Patient Admitted',
                'message' => "{$visit->patient->surname} {$visit->patient->name} was admitted for {$treatment}",
                'timestamp' => $visit->admitted_at,
                'user' => $visit->createdBy->name ?? 'System',
                'patient' => [
                    'id' => $visit->patient->id,
                    'code' => $visit->patient->code,
                    'name' => $visit->patient->surname . ' ' . $visit->patient->name
                ],
                'icon' => 'user-plus',
                'color' => 'blue'
            ]);
        });

        // Add discharge activities
        $discharges->each(function ($visit) use ($activities) {
            $activities->push([
                'id' => 'discharge-' . $visit->id,
                'type' => 'patient_discharged',
                'title' => 'Patient Discharged',
                'message' => "{$visit->patient->surname} {$visit->patient->name} was discharged after " .
                           $visit->admitted_at->diffForHumans($visit->discharged_at, true),
                'timestamp' => $visit->discharged_at,
                'user' => $visit->updatedBy->name ?? 'System',
                'patient' => [
                    'id' => $visit->patient->id,
                    'code' => $visit->patient->code,
                    'name' => $visit->patient->surname . ' ' . $visit->patient->name
                ],
                'icon' => 'user-minus',
                'color' => 'gray'
            ]);
        });

        // Sort by timestamp and take latest 10
        $sortedActivities = $activities->sortByDesc('timestamp')->take(10)->values();

        return $this->successResponse($sortedActivities, 'Recent activity retrieved successfully');
    }

    public function quickStats()
    {
        $today = today();
        $thisWeek = [now()->startOfWeek(), now()->endOfWeek()];
        $thisMonth = [now()->startOfMonth(), now()->endOfMonth()];

        $stats = [
            'patients' => [
                'total' => Patient::count(),
                'today' => Patient::whereDate('created_at', $today)->count(),
                'this_week' => Patient::whereBetween('created_at', $thisWeek)->count(),
                'this_month' => Patient::whereBetween('created_at', $thisMonth)->count(),
                'active' => Patient::whereHas('visits', fn($q) => $q->whereNull('discharged_at'))->count()
            ],
            'visits' => [
                'total' => Visit::count(),
                'active' => Visit::whereNull('discharged_at')->count(),
                'today_admissions' => Visit::whereDate('admitted_at', $today)->count(),
                'today_discharges' => Visit::whereDate('discharged_at', $today)->count(),
                'emergency' => Visit::whereHas('admissionType', fn($q) => $q->where('name', 'like', '%emergency%'))->whereNull('discharged_at')->count()
            ],
            'encounters' => [
                'total' => Encounter::count(),
                'today' => Encounter::whereDate('created_at', $today)->count(),
                'this_week' => Encounter::whereBetween('created_at', $thisWeek)->count(),
                'avg_completion_time' => $this->getAverageEncounterTime()
            ],
            'forms' => [
                'total_templates' => ClinicalFormTemplate::where('active', true)->count(),
                'most_used' => $this->getMostUsedForm(),
                'completion_rate' => $this->getFormCompletionRate()
            ],
            'alerts' => [
                'critical_values' => $this->getCriticalAlerts(),
                'pending_discharges' => $this->getPendingDischarges(),
                'overdue_forms' => $this->getOverdueForms()
            ]
        ];

        return $this->successResponse($stats, 'Quick statistics retrieved successfully');
    }

    public function facilityOverview()
    {
        $currentUser = auth()->user();
        $facilityId = $currentUser->facility_id;

        $overview = [
            'facility' => [
                'id' => $facilityId,
                'name' => $currentUser->facility->name ?? 'Unknown Facility',
                'current_time' => now()->utc()->format('Y-m-d H:i:s'),
                'timezone' => 'UTC'
            ],
            'capacity' => [
                'current_patients' => Visit::where('health_facility_id', $facilityId)->whereNull('discharged_at')->count(),
                'max_capacity' => 100, // This should come from facility settings
                'occupancy_rate' => $this->getOccupancyRate($facilityId)
            ],
            'staff' => [
                'active_users' => User::where('facility_id', $facilityId)->whereDate('last_login_at', '>=', now()->subDays(7))->count(),
                'current_shift' => $this->getCurrentShiftStaff($facilityId)
            ],
            'performance' => [
                'avg_visit_duration' => $this->getAverageVisitDuration($facilityId),
                'patient_satisfaction' => 4.2, // Placeholder
                'form_completion_rate' => $this->getFacilityFormCompletionRate($facilityId)
            ]
        ];

        return $this->successResponse($overview, 'Facility overview retrieved successfully');
    }

    // Private helper methods
    private function getAverageVisitTime(): string
    {
        $avgMinutes = Visit::whereNotNull('discharged_at')
            ->whereBetween('admitted_at', [now()->subDays(30), now()])
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, admitted_at, discharged_at)) as avg_minutes')
            ->value('avg_minutes');

        if (!$avgMinutes) return '0h';

        $hours = intval($avgMinutes / 60);
        $minutes = $avgMinutes % 60;

        return $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m";
    }

    private function getCriticalAlerts(): int
    {
        return Observation::where('created_at', '>=', now()->subHours(24))
            ->where(function ($query) {
                $query->where(function ($q) {
                    // High systolic BP
                    $q->where('observation_concept_id', 2)->where('value_number', '>', 140);
                })->orWhere(function ($q) {
                    // Low SpO2
                    $q->where('observation_concept_id', 6)->where('value_number', '<', 95);
                })->orWhere(function ($q) {
                    // High temperature
                    $q->where('observation_concept_id', 1)->where('value_number', '>', 38.5);
                })->orWhere(function ($q) {
                    // Abnormal heart rate
                    $q->where('observation_concept_id', 4)->where(function ($subQ) {
                        $subQ->where('value_number', '>', 120)->orWhere('value_number', '<', 50);
                    });
                });
            })
            ->count();
    }

    private function getPendingForms(): int
    {
        // Count active visits without recent vital signs
        return Visit::whereNull('discharged_at')
            ->whereDoesntHave('encounters', function ($query) {
                $query->whereHas('clinicalFormTemplate', function ($q) {
                    $q->where('category', 'vital-signs');
                })->where('created_at', '>=', now()->subHours(4));
            })
            ->count();
    }

    private function getActiveUsers(): int
    {
        return User::where('last_login_at', '>=', now()->subMinutes(30))->count();
    }

    private function getStatusIndicators($visit): array
    {
        $indicators = [];

        // Check for recent vital signs
        $hasRecentVitals = $visit->encounters()
            ->whereHas('clinicalFormTemplate', fn($q) => $q->where('category', 'vital-signs'))
            ->where('created_at', '>=', now()->subHours(4))
            ->exists();

        if (!$hasRecentVitals) {
            $indicators[] = ['type' => 'warning', 'message' => 'Vital signs pending', 'icon' => 'exclamation-triangle'];
        }

        // Check for critical values
        $hasCriticalValues = $visit->encounters()
            ->whereHas('observations', function ($q) {
                $q->where(function ($query) {
                    $query->where('observation_concept_id', 2)->where('value_number', '>', 140)
                          ->orWhere('observation_concept_id', 6)->where('value_number', '<', 95);
                });
            })
            ->exists();

        if ($hasCriticalValues) {
            $indicators[] = ['type' => 'danger', 'message' => 'Critical values', 'icon' => 'exclamation-circle'];
        }

        // Check visit duration
        $hoursAdmitted = $visit->admitted_at->diffInHours(now());
        if ($hoursAdmitted > 24) {
            $indicators[] = ['type' => 'info', 'message' => 'Long stay', 'icon' => 'clock'];
        }

        return $indicators;
    }

    private function getVisitPriority($visit): string
    {
        // Determine priority based on various factors
        $criticalValues = $visit->encounters()
            ->whereHas('observations', function ($q) {
                $q->where('observation_concept_id', 2)->where('value_number', '>', 180)
                  ->orWhere('observation_concept_id', 6)->where('value_number', '<', 85);
            })
            ->exists();

        if ($criticalValues) return 'critical';

        $isEmergency = $visit->admissionType &&
                      str_contains(strtolower($visit->admissionType->name), 'emergency');

        if ($isEmergency) return 'high';

        $hoursWithoutUpdate = $visit->encounters->max('updated_at')?->diffInHours(now()) ?? 24;

        if ($hoursWithoutUpdate > 8) return 'medium';

        return 'normal';
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

    private function getAverageEncounterTime(): string
    {
        $avgMinutes = Encounter::whereNotNull('ended_at')
            ->whereBetween('created_at', [now()->subDays(7), now()])
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, started_at, ended_at)) as avg_minutes')
            ->value('avg_minutes');

        return $avgMinutes ? round($avgMinutes, 1) . ' min' : '0 min';
    }

    private function getMostUsedForm(): ?string
    {
        return ClinicalFormTemplate::withCount('encounters')
            ->orderBy('encounters_count', 'desc')
            ->first()
            ?->title ?? 'None';
    }

    private function getFormCompletionRate(): string
    {
        $totalVisits = Visit::whereDate('created_at', '>=', now()->subDays(7))->count();
        $visitsWithForms = Visit::whereDate('created_at', '>=', now()->subDays(7))
            ->whereHas('encounters')
            ->count();

        if ($totalVisits === 0) return '0%';

        return round(($visitsWithForms / $totalVisits) * 100, 1) . '%';
    }

    private function getPendingDischarges(): int
    {
        // Visits admitted more than 3 days ago and still active
        return Visit::whereNull('discharged_at')
            ->where('admitted_at', '<=', now()->subDays(3))
            ->count();
    }

    private function getOverdueForms(): int
    {
        // Active visits without any forms in the last 6 hours
        return Visit::whereNull('discharged_at')
            ->whereDoesntHave('encounters', function ($query) {
                $query->where('created_at', '>=', now()->subHours(6));
            })
            ->where('admitted_at', '<=', now()->subHours(6))
            ->count();
    }

    private function getOccupancyRate($facilityId): string
    {
        $currentPatients = Visit::where('health_facility_id', $facilityId)
            ->whereNull('discharged_at')
            ->count();

        $maxCapacity = 100; // This should come from facility configuration

        if ($maxCapacity === 0) return '0%';

        return round(($currentPatients / $maxCapacity) * 100, 1) . '%';
    }

    private function getCurrentShiftStaff($facilityId): array
    {
        $currentHour = now()->hour;
        $shiftName = match(true) {
            $currentHour >= 6 && $currentHour < 14 => 'Morning Shift',
            $currentHour >= 14 && $currentHour < 22 => 'Evening Shift',
            default => 'Night Shift'
        };

        $activeStaff = User::where('facility_id', $facilityId)
            ->where('last_login_at', '>=', now()->subHours(1))
            ->count();

        return [
            'shift_name' => $shiftName,
            'active_staff' => $activeStaff,
            'shift_hours' => $this->getShiftHours($currentHour)
        ];
    }

    private function getShiftHours($currentHour): string
    {
        return match(true) {
            $currentHour >= 6 && $currentHour < 14 => '06:00 - 14:00',
            $currentHour >= 14 && $currentHour < 22 => '14:00 - 22:00',
            default => '22:00 - 06:00'
        };
    }

    private function getAverageVisitDuration($facilityId): string
    {
        $avgMinutes = Visit::where('health_facility_id', $facilityId)
            ->whereNotNull('discharged_at')
            ->whereBetween('admitted_at', [now()->subDays(30), now()])
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, admitted_at, discharged_at)) as avg_minutes')
            ->value('avg_minutes');

        if (!$avgMinutes) return '0h';

        $hours = intval($avgMinutes / 60);
        $minutes = $avgMinutes % 60;

        return $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m";
    }

    private function getFacilityFormCompletionRate($facilityId): string
    {
        $totalVisits = Visit::where('health_facility_id', $facilityId)
            ->whereDate('created_at', '>=', now()->subDays(7))
            ->count();

        $visitsWithForms = Visit::where('health_facility_id', $facilityId)
            ->whereDate('created_at', '>=', now()->subDays(7))
            ->whereHas('encounters')
            ->count();

        if ($totalVisits === 0) return '0%';

        return round(($visitsWithForms / $totalVisits) * 100, 1) . '%';
    }
}
