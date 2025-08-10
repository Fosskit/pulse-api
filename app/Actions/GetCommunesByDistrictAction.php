<?php

namespace App\Actions;

use App\Models\Gazetteer;
use Illuminate\Database\Eloquent\Collection;

/**
 * Action to retrieve communes by district from the Cambodia gazetteer
 * 
 * This action handles the retrieval of communes within a specific district
 * in the Cambodia administrative hierarchy.
 */
class GetCommunesByDistrictAction
{
    /**
     * Execute the action to get communes by district
     *
     * @param int $districtId The district ID to filter communes
     * @return Collection
     */
    public function execute(int $districtId): Collection
    {
        return Gazetteer::where('type', 'Commune')
            ->where('parent_id', $districtId)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'parent_id']);
    }
}