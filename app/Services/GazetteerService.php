<?php

namespace App\Services;

use App\Models\Gazetteer;
use Illuminate\Support\Collection;

/**
 * Service for handling Cambodia gazetteer operations and address validation
 * 
 * This service provides business logic for validating address hierarchies
 * and ensuring proper relationships between provinces, districts, communes, and villages.
 */
class GazetteerService
{
    /**
     * Validate that the address hierarchy is correct
     *
     * @param int $provinceId
     * @param int $districtId
     * @param int $communeId
     * @param int $villageId
     * @return array Validation result with success status and errors
     */
    public function validateAddressHierarchy(int $provinceId, int $districtId, int $communeId, int $villageId): array
    {
        $errors = [];

        // Validate province exists
        $province = Gazetteer::where('id', $provinceId)
            ->where('type', 'Province')
            ->first();
        
        if (!$province) {
            $errors['province_id'] = 'Invalid province selected.';
        }

        // Validate district belongs to province
        $district = Gazetteer::where('id', $districtId)
            ->where('type', 'District')
            ->where('parent_id', $provinceId)
            ->first();
        
        if (!$district) {
            $errors['district_id'] = 'Invalid district for the selected province.';
        }

        // Validate commune belongs to district
        $commune = Gazetteer::where('id', $communeId)
            ->where('type', 'Commune')
            ->where('parent_id', $districtId)
            ->first();
        
        if (!$commune) {
            $errors['commune_id'] = 'Invalid commune for the selected district.';
        }

        // Validate village belongs to commune
        $village = Gazetteer::where('id', $villageId)
            ->where('type', 'Village')
            ->where('parent_id', $communeId)
            ->first();
        
        if (!$village) {
            $errors['village_id'] = 'Invalid village for the selected commune.';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'data' => [
                'province' => $province,
                'district' => $district,
                'commune' => $commune,
                'village' => $village,
            ]
        ];
    }

    /**
     * Get the full address hierarchy for given IDs
     *
     * @param int $provinceId
     * @param int $districtId
     * @param int $communeId
     * @param int $villageId
     * @return Collection
     */
    public function getAddressHierarchy(int $provinceId, int $districtId, int $communeId, int $villageId): Collection
    {
        return Gazetteer::whereIn('id', [$provinceId, $districtId, $communeId, $villageId])
            ->orderByRaw("FIELD(type, 'Province', 'District', 'Commune', 'Village')")
            ->get(['id', 'name', 'code', 'type', 'parent_id']);
    }

    /**
     * Search gazetteers by name across all types
     *
     * @param string $searchTerm
     * @param string|null $type Optional type filter
     * @param int $limit Maximum results to return
     * @return Collection
     */
    public function searchByName(string $searchTerm, ?string $type = null, int $limit = 50): Collection
    {
        $query = Gazetteer::where('name', 'LIKE', "%{$searchTerm}%");
        
        if ($type) {
            $query->where('type', $type);
        }
        
        return $query->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'code', 'type', 'parent_id']);
    }

    /**
     * Get children of a gazetteer entry
     *
     * @param int $parentId
     * @param string|null $type Optional type filter for children
     * @return Collection
     */
    public function getChildren(int $parentId, ?string $type = null): Collection
    {
        $query = Gazetteer::where('parent_id', $parentId);
        
        if ($type) {
            $query->where('type', $type);
        }
        
        return $query->orderBy('name')
            ->get(['id', 'name', 'code', 'type', 'parent_id']);
    }

    /**
     * Get the complete path from province to village for a given gazetteer ID
     *
     * @param int $gazetteerIds
     * @return array
     */
    public function getCompletePath(int $gazetteerId): array
    {
        $gazetteer = Gazetteer::find($gazetteerId);
        
        if (!$gazetteer) {
            return [];
        }

        $path = [$gazetteer];
        $current = $gazetteer;

        // Traverse up the hierarchy
        while ($current->parent_id) {
            $parent = Gazetteer::find($current->parent_id);
            if ($parent) {
                array_unshift($path, $parent);
                $current = $parent;
            } else {
                break;
            }
        }

        return $path;
    }
}