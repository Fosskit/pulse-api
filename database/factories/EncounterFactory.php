<?php

namespace Database\Factories;

use App\Models\Encounter;
use App\Models\Term;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Encounter>
 */
class EncounterFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Encounter::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = $this->faker->dateTimeBetween('-1 week', 'now');
        
        return [
            'ulid' => \Illuminate\Support\Str::ulid(),
            'visit_id' => Visit::factory(),
            'encounter_type_id' => Term::factory(),
            'encounter_form_id' => $this->faker->numberBetween(1, 10),
            'is_new' => $this->faker->boolean(),
            'started_at' => $startedAt,
            'ended_at' => $this->faker->optional(0.7)->dateTimeBetween($startedAt, 'now'),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Indicate that the encounter is currently active (not ended).
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'ended_at' => null,
        ]);
    }

    /**
     * Indicate that the encounter has ended.
     */
    public function ended(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'ended_at' => $this->faker->dateTimeBetween($attributes['started_at'], 'now'),
            ];
        });
    }
}