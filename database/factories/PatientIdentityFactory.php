<?php

namespace Database\Factories;

use App\Models\Card;
use App\Models\Patient;
use App\Models\PatientIdentity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PatientIdentity>
 */
class PatientIdentityFactory extends Factory
{
    protected $model = PatientIdentity::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ulid' => $this->faker->unique()->lexify('PID??????????'),
            'code' => $this->faker->unique()->numerify('ID#########'),
            'patient_id' => Patient::factory(),
            'card_id' => Card::factory(),
            'start_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'end_date' => $this->faker->dateTimeBetween('now', '+1 year'),
            'detail' => [
                'issuer' => $this->faker->company,
                'notes' => $this->faker->sentence
            ],
        ];
    }

    /**
     * Indicate that the identity is currently active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'start_date' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'end_date' => $this->faker->dateTimeBetween('now', '+6 months'),
        ]);
    }

    /**
     * Indicate that the identity is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'start_date' => $this->faker->dateTimeBetween('-2 years', '-1 year'),
            'end_date' => $this->faker->dateTimeBetween('-1 year', '-1 month'),
        ]);
    }
}