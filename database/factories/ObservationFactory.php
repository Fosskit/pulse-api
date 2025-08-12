<?php

namespace Database\Factories;

use App\Models\Encounter;
use App\Models\Observation;
use App\Models\Patient;
use App\Models\ServiceRequest;
use App\Models\Term;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Observation>
 */
class ObservationFactory extends Factory
{
    protected $model = Observation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ulid' => $this->faker->unique()->lexify('OBS??????????'),
            'parent_id' => null,
            'patient_id' => Patient::factory(),
            'encounter_id' => Encounter::factory(),
            'service_request_id' => null,
            'code' => $this->faker->unique()->numerify('OBS####'),
            'observation_status_id' => Term::factory(),
            'concept_id' => Term::factory(),
            'body_site_id' => null,
            'value_id' => null,
            'value_string' => $this->faker->sentence,
            'value_number' => null,
            'value_text' => null,
            'value_complex' => null,
            'value_datetime' => null,
            'value_boolean' => null,
            'reference_range_low' => null,
            'reference_range_high' => null,
            'reference_range_text' => null,
            'interpretation' => null,
            'comments' => $this->faker->sentence,
            'observed_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'observed_by' => $this->faker->name,
            'verified_at' => null,
            'verified_by' => null,
        ];
    }

    /**
     * Indicate that this observation has a numeric value.
     */
    public function numeric(): static
    {
        return $this->state(fn (array $attributes) => [
            'value_string' => null,
            'value_number' => $this->faker->randomFloat(2, 0, 200),
        ]);
    }

    /**
     * Indicate that this observation is verified.
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'verified_at' => $this->faker->dateTimeBetween($attributes['observed_at'] ?? 'now', 'now'),
            'verified_by' => $this->faker->name,
        ]);
    }

    /**
     * Indicate that this observation is linked to a service request.
     */
    public function forServiceRequest(): static
    {
        return $this->state(fn (array $attributes) => [
            'service_request_id' => ServiceRequest::factory(),
        ]);
    }
}