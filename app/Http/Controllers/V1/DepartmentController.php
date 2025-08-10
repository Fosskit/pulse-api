<?php

namespace App\Http\Controllers\V1;

use App\Actions\GetDepartmentRoomsAction;
use App\Http\Controllers\V1\BaseController;
use App\Http\Requests\V1\GetRoomsRequest;
use App\Http\Resources\V1\DepartmentResource;
use App\Http\Resources\V1\RoomResource;
use App\Models\Department;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;

class DepartmentController extends BaseController
{
    /**
     * Display the specified department.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $department = Department::with(['facility'])->findOrFail($id);

            return $this->successResponse(
                new DepartmentResource($department),
                'Department retrieved successfully'
            );
        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse('Department not found');
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to retrieve department');
        }
    }

    /**
     * Get rooms for a specific department.
     */
    public function rooms(int $departmentId, GetRoomsRequest $request, GetDepartmentRoomsAction $action): JsonResponse
    {
        try {
            $rooms = $action->execute($departmentId, $request->validated());

            return $this->successResponse(
                RoomResource::collection($rooms),
                'Department rooms retrieved successfully'
            );
        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse('Department not found');
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to retrieve department rooms');
        }
    }
}