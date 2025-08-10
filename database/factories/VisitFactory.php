<?php

namespace Database\Factories;

use App\Models\Facility;
use App\Models\Patient;
use App\Models\Term;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Visit>
 */
class VisitFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Visit::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $admittedAt = $this->faker->dateTimeBetween('-1 week', 'now');
        
        return [
            'ulid' => \Illuminate\Support\Str::ulid(),
            'patient_id' => Patient::factory(),
            'facility_id' => Facility::factory(),
            'visit_type_id' => Term::factory(),
            'admission_type_id' => Term::factory(),
            'admitted_at' => $admittedAt,
            'discharged_at' => $this->faker->optional(0.3)->dateTimeBetween($admittedAt, 'now'),
            'discharge_type_id' => null,
            'visit_outcome_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Indicate that the visit is currently active (not discharged).
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'discharged_at' => null,
            'discharge_type_id' => null,
            'visit_outcome_id' => null,
        ]);
    }

    /**
     * Indicate that the visit has been discharged.
     */
    public function discharged(): static
    {
        return $this->state(function (array $attributes) {
            $dischargedAt = $this->faker->dateTimeBetween($attributes['admitted_at'], 'now');
            
            return [
                'discharged_at' => $dischargedAt,
                'discharge_type_id' => Term::factory(),
                'visit_outcome_id' => Term::factory(),
            ];
        });
    }
}