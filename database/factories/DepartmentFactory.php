<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Facility;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Department>
 */
class DepartmentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Department::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'facility_id' => Facility::factory(),
            'code' => 'DEPT' . $this->faker->unique()->numberBetween(1000, 9999),
            'name' => $this->faker->randomElement([
                'Emergency Department',
                'Internal Medicine',
                'Surgery',
                'Pediatrics',
                'Obstetrics & Gynecology',
                'Intensive Care Unit',
                'Radiology',
                'Laboratory'
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}