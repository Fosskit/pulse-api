<?php

namespace App\Actions;

use App\Models\Department;
use App\Models\Encounter;
use App\Models\Facility;
use App\Models\Room;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class FacilityUtilizationAction
{
    /**
     * Get comprehensive utilization report for a facility.
     *
     * @param int $facilityId
     * @param Carbon|null $startDate
     * @param Carbon|null $endDate
     * @return array
     * @throws ModelNotFoundException
     */
    public function execute(int $facilityId, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        // Default to current day if no dates provided
        if (!$startDate) {
            $startDate = Carbon::today();
        }
        if (!$endDate) {
            $endDate = Carbon::tomorrow();
        }

        $facility = Facility::with(['departments.rooms'])->findOrFail($facilityId);

        // Simplified implementation for now
        $activeVisits = Visit::where('facility_id', $facilityId)
            ->whereNull('discharged_at')
            ->count();

        $activeEncounters = Encounter::whereHas('visit', function ($query) use ($facilityId) {
            $query->where('facility_id', $facilityId);
        })
        ->whereNull('ended_at')
        ->count();

        // Calculate basic statistics
        $totalRooms = $facility->departments->sum(function ($dept) {
            return $dept->rooms ? $dept->rooms->count() : 0;
        });
        
        $totalCapacity = $totalRooms * 2; // Assume 2 patients per room

        $utilizationStats = [
            'total_capacity' => $totalCapacity,
            'active_patients' => $activeVisits,
            'active_encounters' => $activeEncounters,
            'overall_utilization_percentage' => $totalCapacity > 0 ? min(100, ($activeVisits / $totalCapacity) * 100) : 0,
            'period_admissions' => 0,
            'period_encounters' => 0,
            'average_length_of_stay' => 0,
            'peak_utilization_hour' => 0,
        ];

        // Simple department utilization
        $departmentUtilization = $facility->departments->map(function ($department) {
            $roomCount = $department->rooms ? $department->rooms->count() : 0;
            $capacity = $roomCount * 2;

            return [
                'department_id' => $department->id,
                'department_name' => $department->name,
                'room_count' => $roomCount,
                'estimated_capacity' => $capacity,
                'active_encounters' => 0,
                'utilization_percentage' => 0,
                'status' => 'minimal'
            ];
        });

        return [
            'facility' => [
                'id' => $facility->id,
                'code' => $facility->code,
                'name' => $facility->name ?? 'Facility ' . $facility->code
            ],
            'report_period' => [
                'start' => $startDate->toISOString(),
                'end' => $endDate->toISOString(),
                'generated_at' => Carbon::now()->toISOString()
            ],
            'utilization_stats' => $utilizationStats,
            'department_utilization' => $departmentUtilization,
            'room_availability' => [],
            'active_visits' => [],
            'recommendations' => []
        ];
    }

    /**
     * Get utilization status based on percentage.
     */
    private function getUtilizationStatus(float $percentage): string
    {
        if ($percentage >= 90) return 'critical';
        if ($percentage >= 75) return 'high';
        if ($percentage >= 50) return 'moderate';
        if ($percentage >= 25) return 'low';
        return 'minimal';
    }

    /**
     * Calculate average length of stay for visits.
     */
    private function calculateAverageLengthOfStay(Collection $visits): float
    {
        $completedVisits = $visits->filter(function ($visit) {
            return $visit->discharged_at !== null;
        });

        if ($completedVisits->isEmpty()) {
            return 0;
        }

        $totalHours = $completedVisits->sum(function ($visit) {
            return $visit->admitted_at->diffInHours($visit->discharged_at);
        });

        return round($totalHours / $completedVisits->count(), 2);
    }

    /**
     * Calculate peak utilization hour based on encounter start times.
     */
    private function calculatePeakUtilizationHour(Collection $encounters): int
    {
        $hourCounts = [];
        
        foreach ($encounters as $encounter) {
            $hour = $encounter->started_at->hour;
            $hourCounts[$hour] = ($hourCounts[$hour] ?? 0) + 1;
        }

        if (empty($hourCounts)) {
            return 0;
        }

        return array_keys($hourCounts, max($hourCounts))[0];
    }

    /**
     * Generate recommendations based on utilization data.
     */
    private function generateRecommendations(array $stats, Collection $departmentUtilization): array
    {
        $recommendations = [];

        // Overall utilization recommendations
        if ($stats['overall_utilization_percentage'] > 90) {
            $recommendations[] = [
                'type' => 'capacity',
                'priority' => 'high',
                'message' => 'Facility is at critical capacity. Consider expanding or optimizing patient flow.'
            ];
        } elseif ($stats['overall_utilization_percentage'] > 75) {
            $recommendations[] = [
                'type' => 'capacity',
                'priority' => 'medium',
                'message' => 'Facility utilization is high. Monitor closely and prepare for capacity management.'
            ];
        }

        // Department-specific recommendations
        $criticalDepartments = $departmentUtilization->filter(function ($dept) {
            return $dept['utilization_percentage'] > 90;
        });

        if ($criticalDepartments->isNotEmpty()) {
            $recommendations[] = [
                'type' => 'department',
                'priority' => 'high',
                'message' => 'Critical utilization in departments: ' . $criticalDepartments->pluck('department_name')->join(', ')
            ];
        }

        // Length of stay recommendations
        if ($stats['average_length_of_stay'] > 72) { // More than 3 days
            $recommendations[] = [
                'type' => 'efficiency',
                'priority' => 'medium',
                'message' => 'Average length of stay is high. Review discharge planning processes.'
            ];
        }

        return $recommendations;
    }
}