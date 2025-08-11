<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConceptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'system_id' => $this->system_id,
            'concept_category_id' => $this->concept_category_id,
            'name' => $this->name,
            'parent_id' => $this->parent_id,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'description' => $this->description,
            
            // Relationships
            'category' => new ConceptCategoryResource($this->whenLoaded('category')),
            'parent' => new ConceptResource($this->whenLoaded('parent')),
            'children' => ConceptResource::collection($this->whenLoaded('children')),
            
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}