<?php

namespace App\Actions;

use App\Services\AddressSearchService;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Action to search patient addresses using gazetteer relationships
 * 
 * This action provides comprehensive search functionality for patient addresses
 * using the Cambodia gazetteer hierarchy and various search criteria.
 */
class SearchPatientAddressesAction
{
    public function __construct(
        private readonly AddressSearchService $addressSearchService
    ) {}

    /**
     * Execute address search with filters
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function execute(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        // Validate per page limit
        $perPage = min(max($perPage, 1), 100);

        if (isset($filters['search']) && !empty($filters['search'])) {
            return $this->addressSearchService->searchByText($filters['search'], $perPage);
        }

        return $this->addressSearchService->searchAddresses($filters, $perPage);
    }

    /**
     * Search addresses within a specific administrative area
     *
     * @param int $gazetteerId
     * @param string $level
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function searchInArea(int $gazetteerId, string $level, int $perPage = 15): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 100);
        
        return $this->addressSearchService->findInArea($gazetteerId, $level, $perPage);
    }

    /**
     * Get address statistics
     *
     * @param string $level
     * @return array
     */
    public function getStatistics(string $level = 'province'): array
    {
        return $this->addressSearchService->getAddressStatistics($level);
    }
}