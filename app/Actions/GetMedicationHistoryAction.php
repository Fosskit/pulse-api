<?php

namespace App\Actions;

use App\Models\MedicationRequest;
use App\Models\Patient;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class GetMedicationHistoryAction
{
    public function forPatient(int $patientId, array $filters = []): Collection|LengthAwarePaginator
    {
        // Validate patient exists
        Patient::findOrFail($patientId);

        $query = MedicationRequest::forPatient($patientId)
            ->with([
                'visit',
                'status',
                'intent',
                'medication',
                'requester',
                'instruction.method',
                'instruction.unit',
                'unit',
                'dispenses.status',
                'dispenses.dispenser'
            ])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if (!empty($filters['visit_id'])) {
            $query->where('visit_id', $filters['visit_id']);
        }

        if (!empty($filters['status'])) {
            $query->whereHas('status', function ($q) use ($filters) {
                $q->where('name', $filters['status']);
            });
        }

        if (!empty($filters['medication_name'])) {
            $query->whereHas('medication', function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['medication_name'] . '%');
            });
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        // Return paginated or all results
        if (!empty($filters['per_page'])) {
            return $query->paginate($filters['per_page']);
        }

        return $query->get();
    }

    public function forVisit(int $visitId, array $filters = []): Collection
    {
        // Validate visit exists
        Visit::findOrFail($visitId);

        $query = MedicationRequest::forVisit($visitId)
            ->with([
                'status',
                'intent',
                'medication',
                'requester',
                'instruction.method',
                'instruction.unit',
                'unit',
                'dispenses.status',
                'dispenses.dispenser'
            ])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if (!empty($filters['status'])) {
            $query->whereHas('status', function ($q) use ($filters) {
                $q->where('name', $filters['status']);
            });
        }

        if (!empty($filters['pending_only']) && $filters['pending_only']) {
            $query->pending();
        }

        return $query->get();
    }

    public function getActivePrescriptions(int $patientId): Collection
    {
        return MedicationRequest::forPatient($patientId)
            ->active()
            ->with([
                'visit',
                'medication',
                'instruction.method',
                'instruction.unit',
                'unit',
                'dispenses'
            ])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getPendingDispenses(int $visitId): Collection
    {
        return MedicationRequest::forVisit($visitId)
            ->pending()
            ->with([
                'medication',
                'instruction.method',
                'instruction.unit',
                'unit'
            ])
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function getMedicationSummary(int $patientId): array
    {
        $requests = MedicationRequest::forPatient($patientId)
            ->with(['medication', 'dispenses'])
            ->get();

        $totalRequests = $requests->count();
        $totalDispensed = $requests->sum('total_dispensed');
        $pendingRequests = $requests->where('is_fully_dispensed', false)->count();
        
        $medicationsByName = $requests->groupBy('medication.name')
            ->map(function ($group) {
                return [
                    'medication_name' => $group->first()->medication->name,
                    'total_requests' => $group->count(),
                    'total_quantity_requested' => $group->sum('quantity'),
                    'total_quantity_dispensed' => $group->sum('total_dispensed'),
                    'last_prescribed' => $group->max('created_at'),
                ];
            })
            ->values();

        return [
            'total_requests' => $totalRequests,
            'total_dispensed' => $totalDispensed,
            'pending_requests' => $pendingRequests,
            'medications_by_name' => $medicationsByName,
        ];
    }
}