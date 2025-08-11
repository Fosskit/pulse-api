<?php

namespace Database\Factories;

use App\Models\ImagingRequest;
use App\Models\ServiceRequest;
use App\Models\Concept;
use Illuminate\Database\Eloquent\Factories\Factory;

class ImagingRequestFactory extends Factory
{
    protected $model = ImagingRequest::class;

    public function definition(): array
    {
        return [
            'service_request_id' => ServiceRequest::factory()->imaging(),
            'modality_concept_id' => Concept::factory(),
            'body_site_concept_id' => Concept::factory(),
            'reason_for_study' => $this->faker->optional(0.7)->sentence(),
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