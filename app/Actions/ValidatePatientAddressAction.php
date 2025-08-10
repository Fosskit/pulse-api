<?php

namespace App\Actions;

use App\Services\AddressSearchService;
use App\Services\GazetteerService;

/**
 * Action to validate patient address using gazetteer hierarchy
 * 
 * This action validates that the provided address components follow
 * the correct Cambodia gazetteer hierarchy relationships.
 */
class ValidatePatientAddressAction
{
    public function __construct(
        private readonly GazetteerService $gazetteerService,
        private readonly AddressSearchService $addressSearchService
    ) {}

    /**
     * Execute address validation
     *
     * @param array $addressData
     * @return array
     */
    public function execute(array $addressData): array
    {
        // Validate required fields
        $requiredFields = ['province_id', 'district_id', 'commune_id', 'village_id'];
        $missingFields = [];

        foreach ($requiredFields as $field) {
            if (!isset($addressData[$field]) || empty($addressData[$field])) {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            return [
                'valid' => false,
                'errors' => [
                    'missing_fields' => 'Required fields missing: ' . implode(', ', $missingFields)
                ],
                'suggestions' => []
            ];
        }

        // Validate hierarchy using gazetteer service
        $hierarchyValidation = $this->gazetteerService->validateAddressHierarchy(
            $addressData['province_id'],
            $addressData['district_id'],
            $addressData['commune_id'],
            $addressData['village_id']
        );

        if (!$hierarchyValidation['valid']) {
            // Get suggestions for corrections
            $suggestions = $this->addressSearchService->validateAndSuggest($addressData);
            
            return [
                'valid' => false,
                'errors' => $hierarchyValidation['errors'],
                'suggestions' => $suggestions['suggestions'] ?? []
            ];
        }

        return [
            'valid' => true,
            'errors' => [],
            'data' => $hierarchyValidation['data'],
            'address_path' => $this->buildAddressPath($hierarchyValidation['data'])
        ];
    }

    /**
     * Build formatted address path from validated data
     *
     * @param array $data
     * @return array
     */
    private function buildAddressPath(array $data): array
    {
        return [
            'province' => $data['province']?->name,
            'district' => $data['district']?->name,
            'commune' => $data['commune']?->name,
            'village' => $data['village']?->name,
            'full_path' => implode(' > ', array_filter([
                $data['province']?->name,
                $data['district']?->name,
                $data['commune']?->name,
                $data['village']?->name,
            ]))
        ];
    }
}