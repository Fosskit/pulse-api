<?php

namespace App\Actions;

use App\Models\Room;
use App\Models\Encounter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CheckRoomAvailabilityAction
{
    /**
     * Check if a room is available for a given time period.
     *
     * @param int $roomId
     * @param Carbon|null $startTime
     * @param Carbon|null $endTime
     * @return array
     * @throws ModelNotFoundException
     */
    public function execute(int $roomId, ?Carbon $startTime = null, ?Carbon $endTime = null): array
    {
        // Verify room exists
        $room = Room::with(['department', 'roomType'])->findOrFail($roomId);

        // If no time period specified, check current availability
        if (!$startTime) {
            $startTime = Carbon::now();
        }
        
        if (!$endTime) {
            $endTime = Carbon::now()->addHours(1); // Default 1-hour check
        }

        // Check if room is soft deleted
        if ($room->deleted_at) {
            return [
                'available' => false,
                'reason' => 'Room is not active',
                'room' => $room,
                'conflicts' => []
            ];
        }

        // Check for conflicting encounters/appointments
        // Since encounters don't have room_id in current schema, we'll implement
        // availability checking based on visits and encounters that might be
        // associated with the room through department relationships
        
        $conflicts = collect();
        
        // Simplified availability check for now
        // In a real implementation, you would check:
        // 1. Room maintenance schedules
        // 2. Existing bookings/appointments
        // 3. Current patient assignments
        
        $conflicts = collect(); // Empty for now
        $activeDepartmentEncounters = 0; // Simplified
        $isOverCapacity = false;

        $isAvailable = $conflicts->isEmpty() && !$isOverCapacity;

        return [
            'available' => $isAvailable,
            'reason' => $isAvailable ? null : ($isOverCapacity ? 'Department over capacity' : 'Room has conflicting bookings'),
            'room' => $room,
            'conflicts' => $conflicts,
            'department_utilization' => [
                'active_encounters' => $activeDepartmentEncounters,
                'capacity_threshold' => 5,
                'utilization_percentage' => 0
            ],
            'checked_period' => [
                'start' => $startTime->toISOString(),
                'end' => $endTime->toISOString()
            ]
        ];
    }

    /**
     * Check room availability for multiple rooms.
     *
     * @param array $roomIds
     * @param Carbon|null $startTime
     * @param Carbon|null $endTime
     * @return array
     */
    public function checkMultiple(array $roomIds, ?Carbon $startTime = null, ?Carbon $endTime = null): array
    {
        $results = [];
        
        foreach ($roomIds as $roomId) {
            try {
                $results[$roomId] = $this->execute($roomId, $startTime, $endTime);
            } catch (ModelNotFoundException $e) {
                $results[$roomId] = [
                    'available' => false,
                    'reason' => 'Room not found',
                    'room' => null,
                    'conflicts' => []
                ];
            }
        }

        return $results;
    }
}