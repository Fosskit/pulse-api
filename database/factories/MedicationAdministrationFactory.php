<?php

namespace Database\Factories;

use App\Models\MedicationAdministration;
use App\Models\MedicationRequest;
use App\Models\Term;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

class MedicationAdministrationFactory extends Factory
{
    protected $model = MedicationAdministration::class;

    public function definition(): array
    {
        return [
            'ulid' => $this->faker->unique()->regexify('[0-9A-HJKMNP-TV-Z]{26}'),
            'visit_id' => Visit::factory(),
            'medication_request_id' => MedicationRequest::factory(),
            'status_id' => Term::factory(),
            'administrator_id' => User::factory(),
            'dose_given' => $this->faker->randomFloat(2, 0.5, 10),
            'dose_unit_id' => Term::factory(),
            'administered_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'notes' => $this->faker->optional()->sentence(),
            'vital_signs_before' => $this->faker->optional()->randomElement([
                [
                    'temperature' => $this->faker->randomFloat(1, 36.0, 38.5),
                    'blood_pressure_systolic' => $this->faker->numberBetween(90, 140),
                    'blood_pressure_diastolic' => $this->faker->numberBetween(60, 90),
                    'heart_rate' => $this->faker->numberBetween(60, 100),
                    'respiratory_rate' => $this->faker->numberBetween(12, 20),
                    'oxygen_saturation' => $this->faker->numberBetween(95, 100),
                ],
            ]),
            'vital_signs_after' => $this->faker->optional()->randomElement([
                [
                    'temperature' => $this->faker->randomFloat(1, 36.0, 38.5),
                    'blood_pressure_systolic' => $this->faker->numberBetween(90, 140),
                    'blood_pressure_diastolic' => $this->faker->numberBetween(60, 90),
                    'heart_rate' => $this->faker->numberBetween(60, 100),
                    'respiratory_rate' => $this->faker->numberBetween(12, 20),
                    'oxygen_saturation' => $this->faker->numberBetween(95, 100),
                ],
            ]),
            'adverse_reactions' => $this->faker->optional(0.1)->sentence(),
        ];
    }

    public function forVisit(Visit $visit): static
    {
        return $this->state(fn (array $attributes) => [
            'visit_id' => $visit->id,
        ]);
    }

    public function forMedicationRequest(MedicationRequest $medicationRequest): static
    {
        return $this->state(fn (array $attributes) => [
            'medication_request_id' => $medicationRequest->id,
            'visit_id' => $medicationRequest->visit_id,
        ]);
    }

    public function withDose(float $dose): static
    {
        return $this->state(fn (array $attributes) => [
            'dose_given' => $dose,
        ]);
    }

    public function withAdministrator(User $administrator): static
    {
        return $this->state(fn (array $attributes) => [
            'administrator_id' => $administrator->id,
        ]);
    }

    public function withAdverseReactions(string $reactions = null): static
    {
        return $this->state(fn (array $attributes) => [
            'adverse_reactions' => $reactions ?? $this->faker->sentence(),
        ]);
    }

    public function withVitalSigns(array $before = null, array $after = null): static
    {
        return $this->state(fn (array $attributes) => [
            'vital_signs_before' => $before ?? [
                'temperature' => $this->faker->randomFloat(1, 36.0, 38.5),
                'blood_pressure_systolic' => $this->faker->numberBetween(90, 140),
                'blood_pressure_diastolic' => $this->faker->numberBetween(60, 90),
                'heart_rate' => $this->faker->numberBetween(60, 100),
            ],
            'vital_signs_after' => $after ?? [
                'temperature' => $this->faker->randomFloat(1, 36.0, 38.5),
                'blood_pressure_systolic' => $this->faker->numberBetween(90, 140),
                'blood_pressure_diastolic' => $this->faker->numberBetween(60, 90),
                'heart_rate' => $this->faker->numberBetween(60, 100),
            ],
        ]);
    }

    public function administered(): static
    {
        return $this->state(function (array $attributes) {
            $administeredStatus = Term::factory()->create(['name' => 'administered']);
            return [
                'status_id' => $administeredStatus->id,
                'dose_given' => $this->faker->randomFloat(2, 0.5, 10),
                'administered_at' => $this->faker->dateTimeBetween('-1 day', 'now'),
            ];
        });
    }
}