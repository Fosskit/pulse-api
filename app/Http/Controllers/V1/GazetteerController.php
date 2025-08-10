<?php

namespace App\Http\Controllers\V1;

use App\Actions\GetProvincesAction;
use App\Actions\GetDistrictsByProvinceAction;
use App\Actions\GetCommunesByDistrictAction;
use App\Actions\GetVillagesByCommuneAction;
use App\Actions\SearchPatientAddressesAction;
use App\Actions\ValidatePatientAddressAction;
use App\Services\GazetteerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller for Cambodia Gazetteer API endpoints
 * 
 * Provides hierarchical address data for Cambodia's administrative divisions:
 * Province -> District -> Commune -> Village
 */
class GazetteerController extends BaseController
{
    public function __construct(
        private readonly GazetteerService $gazetteerService
    ) {}

    /**
     * Get all provinces
     *
     * @param GetProvincesAction $action
     * @return JsonResponse
     */
    public function provinces(GetProvincesAction $action): JsonResponse
    {
        try {
            $provinces = $action->execute();
            
            return $this->successResponse(
                $provinces,
                'Provinces retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to retrieve provinces');
        }
    }

    /**
     * Get districts by province
     *
     * @param int $provinceId
     * @param GetDistrictsByProvinceAction $action
     * @return JsonResponse
     */
    public function districts(int $provinceId, GetDistrictsByProvinceAction $action): JsonResponse
    {
        try {
            $districts = $action->execute($provinceId);
            
            return $this->successResponse(
                $districts,
                'Districts retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to retrieve districts');
        }
    }

    /**
     * Get communes by district
     *
     * @param int $districtId
     * @param GetCommunesByDistrictAction $action
     * @return JsonResponse
     */
    public function communes(int $districtId, GetCommunesByDistrictAction $action): JsonResponse
    {
        try {
            $communes = $action->execute($districtId);
            
            return $this->successResponse(
                $communes,
                'Communes retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to retrieve communes');
        }
    }

    /**
     * Get villages by commune
     *
     * @param int $communeId
     * @param GetVillagesByCommuneAction $action
     * @return JsonResponse
     */
    public function villages(int $communeId, GetVillagesByCommuneAction $action): JsonResponse
    {
        try {
            $villages = $action->execute($communeId);
            
            return $this->successResponse(
                $villages,
                'Villages retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to retrieve villages');
        }
    }

    /**
     * Validate address hierarchy
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function validateAddress(Request $request): JsonResponse
    {
        $request->validate([
            'province_id' => 'required|integer|exists:gazetteers,id',
            'district_id' => 'required|integer|exists:gazetteers,id',
            'commune_id' => 'required|integer|exists:gazetteers,id',
            'village_id' => 'required|integer|exists:gazetteers,id',
        ]);

        try {
            $validation = $this->gazetteerService->validateAddressHierarchy(
                $request->province_id,
                $request->district_id,
                $request->commune_id,
                $request->village_id
            );

            if (!$validation['valid']) {
                return $this->validationErrorResponse(
                    $validation['errors'],
                    'Invalid address hierarchy'
                );
            }

            return $this->successResponse(
                $validation['data'],
                'Address hierarchy is valid'
            );
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to validate address');
        }
    }

    /**
     * Search gazetteers by name
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2|max:100',
            'type' => 'nullable|string|in:Province,District,Commune,Village',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        try {
            $results = $this->gazetteerService->searchByName(
                $request->q,
                $request->type,
                $request->limit ?? 50
            );

            return $this->successResponse(
                $results,
                'Search results retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to search gazetteers');
        }
    }

    /**
     * Get complete path for a gazetteer entry
     *
     * @param int $id
     * @return JsonResponse
     */
    public function path(int $id): JsonResponse
    {
        try {
            $path = $this->gazetteerService->getCompletePath($id);

            if (empty($path)) {
                return $this->notFoundResponse('Gazetteer entry not found');
            }

            return $this->successResponse(
                $path,
                'Address path retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to retrieve address path');
        }
    }

    /**
     * Search patient addresses using gazetteer relationships
     *
     * @param Request $request
     * @param SearchPatientAddressesAction $action
     * @return JsonResponse
     */
    public function searchAddresses(Request $request, SearchPatientAddressesAction $action): JsonResponse
    {
        $request->validate([
            'search' => 'nullable|string|min:2|max:100',
            'province_id' => 'nullable|integer|exists:gazetteers,id',
            'district_id' => 'nullable|integer|exists:gazetteers,id',
            'commune_id' => 'nullable|integer|exists:gazetteers,id',
            'village_id' => 'nullable|integer|exists:gazetteers,id',
            'is_current' => 'nullable|boolean',
            'address_type_id' => 'nullable|integer|exists:terms,id',
            'patient_id' => 'nullable|integer|exists:patients,id',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        try {
            $filters = $request->only([
                'search', 'province_id', 'district_id', 'commune_id', 
                'village_id', 'is_current', 'address_type_id', 'patient_id'
            ]);

            $addresses = $action->execute($filters, $request->per_page ?? 15);

            return $this->paginatedResponse($addresses, 'Addresses retrieved successfully');
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to search addresses');
        }
    }

    /**
     * Get addresses within a specific administrative area
     *
     * @param Request $request
     * @param SearchPatientAddressesAction $action
     * @return JsonResponse
     */
    public function addressesInArea(Request $request, SearchPatientAddressesAction $action): JsonResponse
    {
        $request->validate([
            'gazetteer_id' => 'required|integer|exists:gazetteers,id',
            'level' => 'required|string|in:province,district,commune,village',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        try {
            $addresses = $action->searchInArea(
                $request->gazetteer_id,
                $request->level,
                $request->per_page ?? 15
            );

            return $this->paginatedResponse($addresses, 'Addresses in area retrieved successfully');
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to retrieve addresses in area');
        }
    }

    /**
     * Get address statistics by administrative level
     *
     * @param Request $request
     * @param SearchPatientAddressesAction $action
     * @return JsonResponse
     */
    public function addressStatistics(Request $request, SearchPatientAddressesAction $action): JsonResponse
    {
        $request->validate([
            'level' => 'nullable|string|in:province,district,commune,village',
        ]);

        try {
            $statistics = $action->getStatistics($request->level ?? 'province');

            return $this->successResponse(
                $statistics,
                'Address statistics retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to retrieve address statistics');
        }
    }

    /**
     * Validate patient address hierarchy
     *
     * @param Request $request
     * @param ValidatePatientAddressAction $action
     * @return JsonResponse
     */
    public function validatePatientAddress(Request $request, ValidatePatientAddressAction $action): JsonResponse
    {
        $request->validate([
            'province_id' => 'required|integer|exists:gazetteers,id',
            'district_id' => 'required|integer|exists:gazetteers,id',
            'commune_id' => 'required|integer|exists:gazetteers,id',
            'village_id' => 'required|integer|exists:gazetteers,id',
        ]);

        try {
            $validation = $action->execute($request->only([
                'province_id', 'district_id', 'commune_id', 'village_id'
            ]));

            if (!$validation['valid']) {
                return $this->validationErrorResponse(
                    $validation['errors'],
                    'Invalid address hierarchy'
                );
            }

            return $this->successResponse(
                $validation,
                'Address hierarchy is valid'
            );
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to validate address');
        }
    }
}