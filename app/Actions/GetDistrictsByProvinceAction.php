<?php

namespace App\Actions;

use App\Models\Gazetteer;
use Illuminate\Database\Eloquent\Collection;

/**
 * Action to retrieve districts by province from the Cambodia gazetteer
 * 
 * This action handles the retrieval of districts within a specific province
 * in the Cambodia administrative hierarchy.
 */
class GetDistrictsByProvinceAction
{
    /**
     * Execute the action to get districts by province
     *
     * @param int $provinceId The province ID to filter districts
     * @return Collection
     */
    public function execute(int $provinceId): Collection
    {
        return Gazetteer::where('type', 'District')
            ->where('parent_id', $provinceId)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'parent_id']);
    }
}