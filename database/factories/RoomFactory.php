<?php

namespace Database\Factories;

use App\Models\Room;
use App\Models\Department;
use App\Models\Term;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Room>
 */
class RoomFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Room::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'room_type_id' => Term::factory(),
            'code' => 'ROOM' . $this->faker->unique()->numberBetween(100, 999),
            'name' => $this->faker->randomElement([
                'Standard Room',
                'ICU Room',
                'Operating Theater',
                'Emergency Room',
                'Consultation Room',
                'Laboratory',
                'Radiology Room'
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}