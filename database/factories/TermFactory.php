<?php

namespace Database\Factories;

use App\Models\Term;
use App\Models\Terminology;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Term>
 */
class TermFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Term::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'TERM' . $this->faker->unique()->numberBetween(1000, 9999),
            'terminology_id' => Terminology::factory(),
            'name' => $this->faker->randomElement([
                'Standard Room',
                'ICU Room',
                'Operating Theater',
                'Emergency Room',
                'Consultation Room',
                'Laboratory',
                'Radiology Room'
            ]),
            'sort_order' => $this->faker->numberBetween(1, 100),
            'is_active' => true,
            'description' => $this->faker->sentence(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}