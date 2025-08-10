<?php

namespace App\Actions;

use App\Models\Department;
use App\Models\Room;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class GetDepartmentRoomsAction
{
    /**
     * Get all rooms for a specific department.
     *
     * @param int $departmentId
     * @param array $filters
     * @return Collection
     * @throws ModelNotFoundException
     */
    public function execute(int $departmentId, array $filters = []): Collection
    {
        // Verify department exists
        $department = Department::findOrFail($departmentId);

        $query = Room::forDepartment($departmentId)->with(['roomType']);

        // Apply filters if provided
        if (isset($filters['code'])) {
            $query->where('code', 'like', '%' . $filters['code'] . '%');
        }

        if (isset($filters['name'])) {
            $query->where('name', 'like', '%' . $filters['name'] . '%');
        }

        if (isset($filters['room_type_id'])) {
            $query->where('room_type_id', $filters['room_type_id']);
        }

        if (isset($filters['available']) && $filters['available']) {
            $query->available();
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