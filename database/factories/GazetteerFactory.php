<?php

namespace Database\Factories;

use App\Models\Gazetteer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Gazetteer>
 */
class GazetteerFactory extends Factory
{
    protected $model = Gazetteer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->numerify('GAZ####'),
            'type' => $this->faker->randomElement(['Province', 'District', 'Commune', 'Village']),
            'parent_id' => 1, // Default parent ID
            'name' => $this->faker->city,
        ];
    }

    /**
     * Indicate that this is a province.
     */
    public function province(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'Province',
            'parent_id' => 1, // Root level
        ]);
    }

    /**
     * Indicate that this is a district.
     */
    public function district(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'District',
        ]);
    }

    /**
     * Indicate that this is a commune.
     */
    public function commune(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'Commune',
        ]);
    }

    /**
     * Indicate that this is a village.
     */
    public function village(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'Village',
        ]);
    }
}