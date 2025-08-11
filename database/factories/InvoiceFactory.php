<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Visit;
use App\Models\Term;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = $this->faker->dateTimeBetween('-30 days', 'now');
        $total = $this->faker->randomFloat(2, 50, 1000);
        $percentageDiscount = $this->faker->randomFloat(2, 0, 20);
        $amountDiscount = $this->faker->randomFloat(2, 0, 50);
        $discount = ($total * $percentageDiscount / 100) + $amountDiscount;
        $received = $this->faker->randomFloat(2, 0, $total - $discount);

        return [
            'ulid' => $this->faker->unique()->regexify('[0-9A-HJKMNP-TV-Z]{26}'),
            'code' => 'INV-' . $date->format('Ymd') . '-' . str_pad($this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'visit_id' => Visit::factory(),
            'invoice_category_id' => function () {
                return Term::firstOrCreate([
                    'name' => 'General Invoice',
                    'category' => 'invoice_category'
                ], [
                    'description' => 'General medical services invoice'
                ])->id;
            },
            'payment_type_id' => function () {
                return Term::firstOrCreate([
                    'name' => 'Self Pay',
                    'category' => 'payment_type'
                ], [
                    'description' => 'Patient pays out of pocket'
                ])->id;
            },
            'date' => $date,
            'total' => $total,
            'percentage_discount' => $percentageDiscount,
            'amount_discount' => $amountDiscount,
            'discount' => $discount,
            'received' => $received,
            'remark' => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the invoice is fully paid.
     */
    public function fullyPaid(): static
    {
        return $this->state(function (array $attributes) {
            $finalAmount = $attributes['total'] - $attributes['discount'];
            return [
                'received' => $finalAmount,
            ];
        });
    }

    /**
     * Indicate that the invoice is unpaid.
     */
    public function unpaid(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'received' => 0,
            ];
        });
    }

    /**
     * Indicate that the invoice has insurance coverage.
     */
    public function withInsurance(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'payment_type_id' => function () {
                    return Term::firstOrCreate([
                        'name' => 'Insurance',
                        'category' => 'payment_type'
                    ], [
                        'description' => 'Covered by insurance'
                    ])->id;
                },
                'percentage_discount' => $this->faker->randomFloat(2, 50, 90), // Higher discount for insurance
            ];
        });
    }
}