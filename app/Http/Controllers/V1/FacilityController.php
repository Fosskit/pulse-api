<?php

namespace App\Http\Controllers\V1;

use App\Actions\GetFacilitiesAction;
use App\Actions\GetFacilityDepartmentsAction;
use App\Actions\FacilityUtilizationAction;
use App\Http\Controllers\V1\BaseController;
use App\Http\Requests\V1\GetDepartmentsRequest;
use App\Http\Requests\V1\GetFacilitiesRequest;
use App\Http\Requests\V1\FacilityUtilizationRequest;
use App\Http\Resources\V1\DepartmentResource;
use App\Http\Resources\V1\FacilityResource;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;

class FacilityController extends BaseController
{
    /**
     * Display a listing of facilities.
     */
    public function index(GetFacilitiesRequest $request, GetFacilitiesAction $action): JsonResponse
    {
        try {
            $facilities = $action->execute($request->validated());

            return $this->successResponse(
                FacilityResource::collection($facilities),
                'Facilities retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to retrieve facilities');
        }
    }

    /**
     * Display the specified facility.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $facility = \App\Models\Facility::findOrFail($id);

            return $this->successResponse(
                new FacilityResource($facility),
                'Facility retrieved successfully'
            );
        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse('Facility not found');
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to retrieve facility');
        }
    }

    /**
     * Get departments for a specific facility.
     */
    public function departments(int $facilityId, GetDepartmentsRequest $request, GetFacilityDepartmentsAction $action): JsonResponse
    {
        try {
            $departments = $action->execute($facilityId, $request->validated());

            return $this->successResponse(
                DepartmentResource::collection($departments),
                'Facility departments retrieved successfully'
            );
        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse('Facility not found');
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to retrieve facility departments');
        }
    }

    /**
     * Get utilization report for a specific facility.
     */
    public function utilization(int $facilityId, FacilityUtilizationRequest $request, FacilityUtilizationAction $action): JsonResponse
    {
        try {
            $validated = $request->validated();
            
            $startDate = isset($validated['start_date']) ? \Carbon\Carbon::parse($validated['start_date']) : null;
            $endDate = isset($validated['end_date']) ? \Carbon\Carbon::parse($validated['end_date']) : null;

            $report = $action->execute($facilityId, $startDate, $endDate);

            return $this->successResponse(
                $report,
                'Facility utilization report generated successfully'
            );
        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse('Facility not found');
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to generate utilization report');
        }
    }
}