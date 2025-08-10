<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'department_id' => $this->department_id,
            'room_type_id' => $this->room_type_id,
            'code' => $this->code,
            'name' => $this->name,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'department' => new DepartmentResource($this->whenLoaded('department')),
            'room_type' => new TermResource($this->whenLoaded('roomType')),
        ];
    }
}