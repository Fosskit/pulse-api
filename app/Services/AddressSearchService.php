<?php

namespace App\Services;

use App\Models\PatientAddress;
use App\Models\Gazetteer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Service for searching patient addresses using gazetteer relationships
 * 
 * This service provides advanced search capabilities for patient addresses
 * using the Cambodia gazetteer hierarchy and address components.
 */
class AddressSearchService
{
    /**
     * Search patient addresses with various filters
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function searchAddresses(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = PatientAddress::with([
            'patient',
            'province',
            'district', 
            'commune',
            'village',
            'addressType'
        ]);

        // Apply filters
        $this->applyFilters($query, $filters);

        return $query->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Search addresses by text across all address components
     *
     * @param string $searchTerm
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function searchByText(string $searchTerm, int $perPage = 15): LengthAwarePaginator
    {
        $query = PatientAddress::with([
            'patient',
            'province',
            'district',
            'commune', 
            'village',
            'addressType'
        ]);

        $query->where(function (Builder $q) use ($searchTerm) {
            // Search in street address
            $q->where('street_address', 'LIKE', "%{$searchTerm}%")
              // Search in gazetteer names
              ->orWhereHas('province', function (Builder $subQ) use ($searchTerm) {
                  $subQ->where('name', 'LIKE', "%{$searchTerm}%")
                       ->orWhere('name_kh', 'LIKE', "%{$searchTerm}%");
              })
              ->orWhereHas('district', function (Builder $subQ) use ($searchTerm) {
                  $subQ->where('name', 'LIKE', "%{$searchTerm}%")
                       ->orWhere('name_kh', 'LIKE', "%{$searchTerm}%");
              })
              ->orWhereHas('commune', function (Builder $subQ) use ($searchTerm) {
                  $subQ->where('name', 'LIKE', "%{$searchTerm}%")
                       ->orWhere('name_kh', 'LIKE', "%{$searchTerm}%");
              })
              ->orWhereHas('village', function (Builder $subQ) use ($searchTerm) {
                  $subQ->where('name', 'LIKE', "%{$searchTerm}%")
                       ->orWhere('name_kh', 'LIKE', "%{$searchTerm}%");
              });
        });

        return $query->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Find addresses within a specific administrative area
     *
     * @param int $gazetteerIds
     * @param string $level (province, district, commune, village)
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function findInArea(int $gazetteerId, string $level, int $perPage = 15): LengthAwarePaginator
    {
        $query = PatientAddress::with([
            'patient',
            'province',
            'district',
            'commune',
            'village',
            'addressType'
        ]);

        switch ($level) {
            case 'province':
                $query->where('province_id', $gazetteerId);
                break;
            case 'district':
                $query->where('district_id', $gazetteerId);
                break;
            case 'commune':
                $query->where('commune_id', $gazetteerId);
                break;
            case 'village':
                $query->where('village_id', $gazetteerId);
                break;
            default:
                throw new \InvalidArgumentException("Invalid level: {$level}");
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get address statistics by administrative level
     *
     * @param string $level
     * @return array
     */
    public function getAddressStatistics(string $level = 'province'): array
    {
        $columnMap = [
            'province' => 'province_id',
            'district' => 'district_id', 
            'commune' => 'commune_id',
            'village' => 'village_id'
        ];

        if (!isset($columnMap[$level])) {
            throw new \InvalidArgumentException("Invalid level: {$level}");
        }

        $column = $columnMap[$level];

        $stats = PatientAddress::selectRaw("
            {$column},
            COUNT(*) as address_count,
            COUNT(DISTINCT patient_id) as patient_count
        ")
        ->with($level)
        ->groupBy($column)
        ->orderByDesc('address_count')
        ->get();

        return $stats->map(function ($stat) use ($level) {
            return [
                'gazetteer' => $stat->{$level},
                'address_count' => $stat->address_count,
                'patient_count' => $stat->patient_count,
            ];
        })->toArray();
    }

    /**
     * Validate and suggest address corrections
     *
     * @param array $addressData
     * @return array
     */
    public function validateAndSuggest(array $addressData): array
    {
        $suggestions = [];
        $errors = [];

        // Validate hierarchy
        if (isset($addressData['province_id'], $addressData['district_id'])) {
            $district = Gazetteer::find($addressData['district_id']);
            if ($district && $district->parent_id != $addressData['province_id']) {
                $errors['district_id'] = 'District does not belong to selected province';
                $suggestions['districts'] = Gazetteer::where('parent_id', $addressData['province_id'])
                    ->where('type', 'District')
                    ->get(['id', 'name']);
            }
        }

        if (isset($addressData['district_id'], $addressData['commune_id'])) {
            $commune = Gazetteer::find($addressData['commune_id']);
            if ($commune && $commune->parent_id != $addressData['district_id']) {
                $errors['commune_id'] = 'Commune does not belong to selected district';
                $suggestions['communes'] = Gazetteer::where('parent_id', $addressData['district_id'])
                    ->where('type', 'Commune')
                    ->get(['id', 'name']);
            }
        }

        if (isset($addressData['commune_id'], $addressData['village_id'])) {
            $village = Gazetteer::find($addressData['village_id']);
            if ($village && $village->parent_id != $addressData['commune_id']) {
                $errors['village_id'] = 'Village does not belong to selected commune';
                $suggestions['villages'] = Gazetteer::where('parent_id', $addressData['commune_id'])
                    ->where('type', 'Village')
                    ->get(['id', 'name']);
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'suggestions' => $suggestions
        ];
    }

    /**
     * Apply filters to the query
     *
     * @param Builder $query
     * @param array $filters
     * @return void
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (isset($filters['search'])) {
            $query->searchByAddress($filters['search']);
        }

        if (isset($filters['province_id'])) {
            $query->inProvince($filters['province_id']);
        }

        if (isset($filters['district_id'])) {
            $query->inDistrict($filters['district_id']);
        }

        if (isset($filters['commune_id'])) {
            $query->inCommune($filters['commune_id']);
        }

        if (isset($filters['village_id'])) {
            $query->inVillage($filters['village_id']);
        }

        if (isset($filters['is_current'])) {
            $query->where('is_current', $filters['is_current']);
        }

        if (isset($filters['address_type_id'])) {
            $query->where('address_type_id', $filters['address_type_id']);
        }

        if (isset($filters['patient_id'])) {
            $query->where('patient_id', $filters['patient_id']);
        }
    }
}