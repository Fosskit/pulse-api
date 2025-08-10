<?php

namespace Database\Factories;

use App\Models\Terminology;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Terminology>
 */
class TerminologyFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Terminology::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'TERM' . $this->faker->unique()->numberBetween(1000, 9999),
            'name' => $this->faker->words(2, true),
            'description' => $this->faker->sentence(),
            'is_active' => true,
            'sort_order' => $this->faker->numberBetween(1, 100),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}