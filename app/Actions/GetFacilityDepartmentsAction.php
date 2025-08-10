<?php

namespace App\Actions;

use App\Models\Department;
use App\Models\Facility;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class GetFacilityDepartmentsAction
{
    /**
     * Get all departments for a specific facility.
     *
     * @param int $facilityId
     * @param array $filters
     * @return Collection
     * @throws ModelNotFoundException
     */
    public function execute(int $facilityId, array $filters = []): Collection
    {
        // Verify facility exists
        $facility = Facility::findOrFail($facilityId);

        $query = Department::forFacility($facilityId);

        // Apply filters if provided
        if (isset($filters['code'])) {
            $query->where('code', 'like', '%' . $filters['code'] . '%');
        }

        if (isset($filters['name'])) {
            $query->where('name', 'like', '%' . $filters['name'] . '%');
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('code', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('name', 'like', '%' . $filters['search'] . '%');
            });
        }

        // Order by name for consistent results
        $query->orderBy('name');

        return $query->get();
    }
}