<?php

namespace Database\Factories;

use App\Models\Procedure;
use App\Models\Patient;
use App\Models\Encounter;
use App\Models\Concept;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProcedureFactory extends Factory
{
    protected $model = Procedure::class;

    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'encounter_id' => Encounter::factory(),
            'procedure_concept_id' => Concept::factory(),
            'outcome_id' => Concept::factory(),
            'body_site_id' => Concept::factory(),
            'performed_at' => $this->faker->optional(0.3)->dateTimeBetween('-1 week', 'now'),
            'performed_by' => $this->faker->optional(0.3)->numberBetween(1, 100),
        ];
    }

    public function performed(): static
    {
        return $this->state(fn (array $attributes) => [
            'performed_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'performed_by' => $this->faker->numberBetween(1, 100),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'performed_at' => null,
            'performed_by' => null,
        ]);
    }
}