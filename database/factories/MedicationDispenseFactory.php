<?php

namespace Database\Factories;

use App\Models\MedicationDispense;
use App\Models\MedicationRequest;
use App\Models\Term;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

class MedicationDispenseFactory extends Factory
{
    protected $model = MedicationDispense::class;

    public function definition(): array
    {
        return [
            'ulid' => $this->faker->unique()->regexify('[0-9A-HJKMNP-TV-Z]{26}'),
            'visit_id' => Visit::factory(),
            'status_id' => Term::factory(),
            'medication_request_id' => MedicationRequest::factory(),
            'dispenser_id' => User::factory(),
            'quantity' => $this->faker->numberBetween(1, 50),
            'unit_id' => Term::factory(),
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

    public function withQuantity(int $quantity): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => $quantity,
        ]);
    }

    public function withDispenser(User $dispenser): static
    {
        return $this->state(fn (array $attributes) => [
            'dispenser_id' => $dispenser->id,
        ]);
    }

    public function dispensed(): static
    {
        return $this->state(function (array $attributes) {
            $dispensedStatus = Term::factory()->create(['name' => 'dispensed']);
            return [
                'status_id' => $dispensedStatus->id,
                'quantity' => $this->faker->numberBetween(1, 50),
            ];
        });
    }

    public function pending(): static
    {
        return $this->state(function (array $attributes) {
            $pendingStatus = Term::factory()->create(['name' => 'pending']);
            return [
                'status_id' => $pendingStatus->id,
                'quantity' => 0,
            ];
        });
    }
}