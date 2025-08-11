<?php

namespace Database\Factories;

use App\Models\InvoicePayment;
use App\Models\Invoice;
use App\Models\Term;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InvoicePayment>
 */
class InvoicePaymentFactory extends Factory
{
    protected $model = InvoicePayment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ulid' => $this->faker->unique()->regexify('[0-9A-HJKMNP-TV-Z]{26}'),
            'invoice_id' => Invoice::factory(),
            'amount' => $this->faker->randomFloat(2, 10, 500),
            'payment_method_id' => function () {
                return Term::firstOrCreate([
                    'name' => 'Cash',
                    'category' => 'payment_method'
                ], [
                    'description' => 'Cash payment'
                ])->id;
            },
            'payment_date' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'reference_number' => $this->faker->optional()->regexify('[A-Z0-9]{10}'),
            'notes' => $this->faker->optional()->sentence(),
            'processed_by' => User::factory(),
        ];
    }

    /**
     * Indicate that the payment is a refund.
     */
    public function refund(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'amount' => -abs($attributes['amount']),
                'original_payment_id' => InvoicePayment::factory(),
                'notes' => 'Refund for payment',
            ];
        });
    }

    /**
     * Indicate that the payment is by credit card.
     */
    public function creditCard(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'payment_method_id' => function () {
                    return Term::firstOrCreate([
                        'name' => 'Credit Card',
                        'category' => 'payment_method'
                    ], [
                        'description' => 'Credit card payment'
                    ])->id;
                },
                'reference_number' => $this->faker->creditCardNumber(),
            ];
        });
    }

    /**
     * Indicate that the payment is by bank transfer.
     */
    public function bankTransfer(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'payment_method_id' => function () {
                    return Term::firstOrCreate([
                        'name' => 'Bank Transfer',
                        'category' => 'payment_method'
                    ], [
                        'description' => 'Bank transfer payment'
                    ])->id;
                },
                'reference_number' => $this->faker->regexify('TXN[0-9]{10}'),
            ];
        });
    }
}