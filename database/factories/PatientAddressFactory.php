<?php

namespace Database\Factories;

use App\Models\Gazetteer;
use App\Models\Patient;
use App\Models\PatientAddress;
use App\Models\Term;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PatientAddress>
 */
class PatientAddressFactory extends Factory
{
    protected $model = PatientAddress::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'province_id' => Gazetteer::factory()->state(['type' => 'Province']),
            'district_id' => Gazetteer::factory()->state(['type' => 'District']),
            'commune_id' => Gazetteer::factory()->state(['type' => 'Commune']),
            'village_id' => Gazetteer::factory()->state(['type' => 'Village']),
            'street_address' => $this->faker->streetAddress,
            'is_current' => true,
            'address_type_id' => Term::factory(),
        ];
    }

    /**
     * Indicate that this is not the current address.
     */
    public function notCurrent(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_current' => false,
        ]);
    }
}