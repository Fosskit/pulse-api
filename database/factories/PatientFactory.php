<?php

namespace Database\Factories;

use App\Models\Facility;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Patient>
 */
class PatientFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Patient::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ulid' => \Illuminate\Support\Str::ulid(),
            'code' => 'PAT' . $this->faker->unique()->numberBetween(100000, 999999),
            'facility_id' => Facility::factory(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}