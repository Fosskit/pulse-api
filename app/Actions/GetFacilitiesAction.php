<?php

namespace App\Actions;

use App\Models\Facility;
use Illuminate\Database\Eloquent\Collection;

class GetFacilitiesAction
{
    /**
     * Get all facilities with optional filtering.
     *
     * @param array $filters
     * @return Collection
     */
    public function execute(array $filters = []): Collection
    {
        $query = Facility::query();

        // Apply filters if provided
        if (isset($filters['code'])) {
            $query->where('code', 'like', '%' . $filters['code'] . '%');
        }

        if (isset($filters['search'])) {
            $query->where('code', 'like', '%' . $filters['search'] . '%');
        }

        // Order by code for consistent results
        $query->orderBy('code');

        return $query->get();
    }
}