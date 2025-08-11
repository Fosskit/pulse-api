<?php

namespace Database\Factories;

use App\Models\ServiceRequest;
use App\Models\Visit;
use App\Models\Encounter;
use App\Models\Service;
use App\Models\Term;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceRequestFactory extends Factory
{
    protected $model = ServiceRequest::class;

    public function definition(): array
    {
        return [
            'visit_id' => Visit::factory(),
            'encounter_id' => Encounter::factory(),
            'service_id' => Service::factory(),
            'request_type' => $this->faker->randomElement(['Laboratory', 'Imaging', 'Procedure']),
            'status_id' => Term::factory(),
            'ordered_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'completed_at' => $this->faker->optional(0.3)->dateTimeBetween('now', '+1 week'),
            'scheduled_at' => $this->faker->optional(0.5)->dateTimeBetween('now', '+1 week'),
            'scheduled_for' => $this->faker->optional(0.3)->numberBetween(1, 100),
        ];
    }

    public function laboratory(): static
    {
        return $this->state(fn (array $attributes) => [
            'request_type' => 'Laboratory',
        ]);
    }

    public function imaging(): static
    {
        return $this->state(fn (array $attributes) => [
            'request_type' => 'Imaging',
        ]);
    }

    public function procedure(): static
    {
        return $this->state(fn (array $attributes) => [
            'request_type' => 'Procedure',
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'completed_at' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'completed_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
        ]);
    }
}