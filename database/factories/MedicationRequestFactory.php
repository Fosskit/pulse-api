<?php

namespace Database\Factories;

use App\Models\MedicationInstruction;
use App\Models\MedicationRequest;
use App\Models\Term;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

class MedicationRequestFactory extends Factory
{
    protected $model = MedicationRequest::class;

    public function definition(): array
    {
        return [
            'ulid' => $this->faker->unique()->regexify('[0-9A-HJKMNP-TV-Z]{26}'),
            'visit_id' => Visit::factory(),
            'status_id' => Term::factory(),
            'intent_id' => Term::factory(),
            'medication_id' => Term::factory(),
            'requester_id' => User::factory(),
            'instruction_id' => MedicationInstruction::factory(),
            'quantity' => $this->faker->numberBetween(1, 100),
            'unit_id' => Term::factory(),
        ];
    }

    public function forVisit(Visit $visit): static
    {
        return $this->state(fn (array $attributes) => [
            'visit_id' => $visit->id,
        ]);
    }

    public function withStatus(Term $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status_id' => $status->id,
        ]);
    }

    public function withMedication(Term $medication): static
    {
        return $this->state(fn (array $attributes) => [
            'medication_id' => $medication->id,
        ]);
    }

    public function withQuantity(int $quantity): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => $quantity,
        ]);
    }

    public function withRequester(User $requester): static
    {
        return $this->state(fn (array $attributes) => [
            'requester_id' => $requester->id,
        ]);
    }

    public function active(): static
    {
        return $this->state(function (array $attributes) {
            $activeStatus = Term::factory()->create(['name' => 'active']);
            return [
                'status_id' => $activeStatus->id,
            ];
        });
    }

    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $completedStatus = Term::factory()->create(['name' => 'completed']);
            return [
                'status_id' => $completedStatus->id,
            ];
        });
    }
}