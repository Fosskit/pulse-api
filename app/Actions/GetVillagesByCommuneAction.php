<?php

namespace App\Actions;

use App\Models\Gazetteer;
use Illuminate\Database\Eloquent\Collection;

/**
 * Action to retrieve villages by commune from the Cambodia gazetteer
 * 
 * This action handles the retrieval of villages within a specific commune
 * in the Cambodia administrative hierarchy.
 */
class GetVillagesByCommuneAction
{
    /**
     * Execute the action to get villages by commune
     *
     * @param int $communeId The commune ID to filter villages
     * @return Collection
     */
    public function execute(int $communeId): Collection
    {
        return Gazetteer::where('type', 'Village')
            ->where('parent_id', $communeId)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'parent_id']);
    }
}