<?php

namespace Database\Factories;

use App\Models\MedicationInstruction;
use App\Models\Term;
use Illuminate\Database\Eloquent\Factories\Factory;

class MedicationInstructionFactory extends Factory
{
    protected $model = MedicationInstruction::class;

    public function definition(): array
    {
        return [
            'ulid' => $this->faker->unique()->regexify('[0-9A-HJKMNP-TV-Z]{26}'),
            'method_id' => Term::factory(),
            'unit_id' => Term::factory(),
            'morning' => $this->faker->randomFloat(2, 0, 5),
            'afternoon' => $this->faker->randomFloat(2, 0, 5),
            'evening' => $this->faker->randomFloat(2, 0, 5),
            'night' => $this->faker->randomFloat(2, 0, 2),
            'days' => $this->faker->numberBetween(1, 30),
            'quantity' => $this->faker->randomFloat(2, 1, 100),
            'note' => $this->faker->optional()->sentence(),
        ];
    }

    public function withDosage(float $morning = 1, float $afternoon = 1, float $evening = 1, float $night = 0): static
    {
        return $this->state(fn (array $attributes) => [
            'morning' => $morning,
            'afternoon' => $afternoon,
            'evening' => $evening,
            'night' => $night,
        ]);
    }

    public function forDays(int $days): static
    {
        return $this->state(fn (array $attributes) => [
            'days' => $days,
        ]);
    }

    public function withNote(string $note): static
    {
        return $this->state(fn (array $attributes) => [
            'note' => $note,
        ]);
    }
}