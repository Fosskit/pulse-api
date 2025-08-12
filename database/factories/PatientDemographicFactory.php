<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\PatientDemographic;
use App\Models\Term;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PatientDemographic>
 */
class PatientDemographicFactory extends Factory
{
    protected $model = PatientDemographic::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'name' => [
                'family' => $this->faker->lastName,
                'given' => [$this->faker->firstName]
            ],
            'birthdate' => $this->faker->date(),
            'telecom' => [
                'phone' => $this->faker->phoneNumber,
                'email' => $this->faker->email
            ],
            'address' => [
                'occupation' => $this->faker->jobTitle,
                'marital_status' => $this->faker->randomElement(['Single', 'Married', 'Divorced', 'Widowed']),
                'disabilities' => [],
                'photos' => []
            ],
            'sex' => $this->faker->randomElement(['Male', 'Female']),
            'nationality_id' => Term::factory(),
            'telephone' => $this->faker->phoneNumber,
            'died_at' => $this->faker->dateTimeBetween('now', '+50 years'),
        ];
    }

    /**
     * Indicate that the patient is deceased.
     */
    public function deceased(): static
    {
        return $this->state(fn (array $attributes) => [
            'died_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ]);
    }

    /**
     * Indicate that the patient is alive.
     */
    public function alive(): static
    {
        return $this->state(fn (array $attributes) => [
            'died_at' => $this->faker->dateTimeBetween('+1 year', '+50 years'),
        ]);
    }
}