<?php

namespace Database\Factories;

use App\Models\InvoiceItem;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\MedicationRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    protected $model = InvoiceItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(1, 5);
        $price = $this->faker->randomFloat(2, 10, 200);
        $discount = $this->faker->randomFloat(2, 0, $quantity * $price * 0.2);

        return [
            'ulid' => $this->faker->unique()->regexify('[0-9A-HJKMNP-TV-Z]{26}'),
            'invoice_id' => Invoice::factory(),
            'invoiceable_id' => Service::factory(),
            'invoiceable_type' => Service::class,
            'quantity' => $quantity,
            'price' => $price,
            'paid' => 0,
            'discount' => $discount,
        ];
    }

    /**
     * Indicate that the invoice item is for a service.
     */
    public function forService(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'invoiceable_id' => Service::factory(),
                'invoiceable_type' => Service::class,
            ];
        });
    }

    /**
     * Indicate that the invoice item is for a medication.
     */
    public function forMedication(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'invoiceable_id' => MedicationRequest::factory(),
                'invoiceable_type' => MedicationRequest::class,
            ];
        });
    }

    /**
     * Indicate that the invoice item is paid.
     */
    public function paid(): static
    {
        return $this->state(function (array $attributes) {
            $lineTotal = $attributes['quantity'] * $attributes['price'] - $attributes['discount'];
            return [
                'paid' => $lineTotal,
            ];
        });
    }
}