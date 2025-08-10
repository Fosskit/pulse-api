<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Room;
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
            'code' => 'ROOM' . $this->faker->unique()->numberBetween(1000, 9999),
            'name' => $this->faker->randomElement([
                'Emergency Room',
                'Operating Theater',
                'Patient Room',
                'Consultation Room',
                'ICU Room',
                'Recovery Room',
                'Examination Room'
            ]) . ' ' . $this->faker->numberBetween(1, 20),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}