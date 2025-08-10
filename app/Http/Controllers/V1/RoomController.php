<?php

namespace App\Http\Controllers\V1;

use App\Actions\CheckRoomAvailabilityAction;
use App\Http\Controllers\V1\BaseController;
use App\Http\Requests\V1\CheckRoomAvailabilityRequest;
use App\Http\Resources\V1\RoomResource;
use App\Models\Room;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;

class RoomController extends BaseController
{
    /**
     * Display the specified room.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $room = Room::with(['department.facility', 'roomType'])->findOrFail($id);

            return $this->successResponse(
                new RoomResource($room),
                'Room retrieved successfully'
            );
        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse('Room not found');
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to retrieve room');
        }
    }

    /**
     * Check room availability.
     */
    public function availability(int $roomId, CheckRoomAvailabilityRequest $request, CheckRoomAvailabilityAction $action): JsonResponse
    {
        try {
            $validated = $request->validated();
            
            $startTime = isset($validated['start_time']) ? Carbon::parse($validated['start_time']) : null;
            $endTime = isset($validated['end_time']) ? Carbon::parse($validated['end_time']) : null;

            // Check if this is a bulk check for multiple rooms
            if (isset($validated['room_ids'])) {
                $results = $action->checkMultiple($validated['room_ids'], $startTime, $endTime);
                
                return $this->successResponse(
                    $results,
                    'Room availability checked successfully'
                );
            }

            // Single room check
            $result = $action->execute($roomId, $startTime, $endTime);

            return $this->successResponse(
                $result,
                'Room availability checked successfully'
            );
        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse('Room not found');
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to check room availability');
        }
    }
}