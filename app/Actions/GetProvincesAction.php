<?php

namespace App\Actions;

use App\Models\Gazetteer;
use Illuminate\Database\Eloquent\Collection;

/**
 * Action to retrieve all provinces from the Cambodia gazetteer
 * 
 * This action handles the retrieval of all provinces in the Cambodia
 * administrative hierarchy for use in address selection forms.
 */
class GetProvincesAction
{
    /**
     * Execute the action to get all provinces
     *
     * @return Collection
     */
    public function execute(): Collection
    {
        return Gazetteer::where('type', 'Province')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'parent_id']);
    }
}