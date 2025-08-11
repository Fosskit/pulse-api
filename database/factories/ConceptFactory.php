<?php

namespace Database\Factories;

use App\Models\Concept;
use App\Models\ConceptCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConceptFactory extends Factory
{
    protected $model = Concept::class;

    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->regexify('[A-Z]{2}[0-9]{3}'),
            'system_id' => $this->faker->numberBetween(1, 10),
            'concept_category_id' => ConceptCategory::factory(),
            'name' => $this->faker->words(3, true),
            'parent_id' => null,
            'sort_order' => $this->faker->numberBetween(0, 100),
            'is_active' => true,
            'description' => $this->faker->optional(0.7)->sentence(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withParent(): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => Concept::factory(),
        ]);
    }
}