<?php

namespace Database\Factories;

use App\Models\Card;
use App\Models\Term;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Card>
 */
class CardFactory extends Factory
{
    protected $model = Card::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ulid' => $this->faker->unique()->lexify('CARD??????????'),
            'code' => $this->faker->unique()->numerify('CARD#########'),
            'card_type_id' => Term::factory(),
            'issue_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'expiry_date' => $this->faker->dateTimeBetween('now', '+2 years'),
        ];
    }

    /**
     * Indicate that the card is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'issue_date' => $this->faker->dateTimeBetween('-2 years', '-1 year'),
            'expiry_date' => $this->faker->dateTimeBetween('-1 year', '-1 month'),
        ]);
    }
}