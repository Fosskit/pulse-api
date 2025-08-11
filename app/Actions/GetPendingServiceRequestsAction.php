<?php

namespace App\Actions;

use App\Models\ServiceRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class GetPendingServiceRequestsAction
{
    public function execute(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = ServiceRequest::pending();

        // Apply filters
        $this->applyFilters($query, $filters);

        // Load relationships
        $query->with([
            'visit.patient',
            'encounter',
            'service',
            'status',
            'laboratoryRequest.testConcept',
            'laboratoryRequest.specimenTypeConcept',
            'imagingRequest.modalityConcept',
            'imagingRequest.bodySiteConcept',
        ]);

        // Order by priority (oldest first for pending requests)
        $query->orderBy('ordered_at', 'asc');

        return $query->paginate($perPage);
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if (isset($filters['visit_id'])) {
            $query->where('visit_id', $filters['visit_id']);
        }

        if (isset($filters['patient_id'])) {
            $query->whereHas('visit', function ($q) use ($filters) {
                $q->where('patient_id', $filters['patient_id']);
            });
        }

        if (isset($filters['request_type'])) {
            $query->where('request_type', $filters['request_type']);
        }

        if (isset($filters['encounter_id'])) {
            $query->where('encounter_id', $filters['encounter_id']);
        }

        if (isset($filters['facility_id'])) {
            $query->whereHas('visit', function ($q) use ($filters) {
                $q->where('facility_id', $filters['facility_id']);
            });
        }

        if (isset($filters['department_id'])) {
            $query->whereHas('encounter', function ($q) use ($filters) {
                $q->where('department_id', $filters['department_id']);
            });
        }

        if (isset($filters['ordered_after'])) {
            $query->where('ordered_at', '>=', $filters['ordered_after']);
        }

        if (isset($filters['ordered_before'])) {
            $query->where('ordered_at', '<=', $filters['ordered_before']);
        }

        if (isset($filters['scheduled_today'])) {
            $query->whereDate('scheduled_at', today());
        }

        if (isset($filters['overdue'])) {
            $query->where('scheduled_at', '<', now());
        }
    }
}