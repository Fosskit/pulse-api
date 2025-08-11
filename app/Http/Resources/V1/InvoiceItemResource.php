<?php

namespace App\Http\Resources\V1;

use App\Models\Service;
use App\Models\MedicationRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'invoiceable_type' => $this->invoiceable_type,
            'invoiceable_id' => $this->invoiceable_id,
            'quantity' => $this->quantity,
            'price' => (float) $this->price,
            'paid' => (float) $this->paid,
            'discount' => (float) $this->discount,
            'line_total' => (float) $this->line_total,
            'line_total_after_discount' => (float) $this->line_total_after_discount,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Invoiceable item details
            'item_details' => $this->getInvoiceableDetails(),

            // Relationships
            'discount_type' => $this->whenLoaded('discountType', function () {
                return [
                    'id' => $this->discountType->id,
                    'name' => $this->discountType->name,
                    'description' => $this->discountType->description,
                ];
            }),

            'payment_type' => $this->whenLoaded('paymentType', function () {
                return [
                    'id' => $this->paymentType->id,
                    'name' => $this->paymentType->name,
                    'description' => $this->paymentType->description,
                ];
            }),
        ];
    }

    /**
     * Get details of the invoiceable item.
     */
    private function getInvoiceableDetails(): array
    {
        if (!$this->invoiceable) {
            return [];
        }

        if ($this->invoiceable instanceof Service) {
            return [
                'type' => 'service',
                'id' => $this->invoiceable->id,
                'code' => $this->invoiceable->code,
                'name' => $this->invoiceable->name,
                'department' => $this->invoiceable->department?->name,
            ];
        }

        if ($this->invoiceable instanceof MedicationRequest) {
            return [
                'type' => 'medication',
                'id' => $this->invoiceable->id,
                'ulid' => $this->invoiceable->ulid,
                'medication_name' => $this->invoiceable->medication?->name,
                'unit' => $this->invoiceable->unit?->name,
                'total_quantity' => $this->invoiceable->quantity,
            ];
        }

        return [
            'type' => 'unknown',
            'id' => $this->invoiceable->id,
        ];
    }
}