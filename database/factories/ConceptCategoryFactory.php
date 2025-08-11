<?php

namespace Database\Factories;

use App\Models\ConceptCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConceptCategoryFactory extends Factory
{
    protected $model = ConceptCategory::class;

    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->regexify('[A-Z]{3}[0-9]{2}'),
            'name' => $this->faker->words(2, true),
            'parent_id' => null,
            'description' => $this->faker->optional(0.7)->sentence(),
            'is_active' => true,
            'sort_order' => $this->faker->numberBetween(0, 100),
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
            'parent_id' => ConceptCategory::factory(),
        ]);
    }
}