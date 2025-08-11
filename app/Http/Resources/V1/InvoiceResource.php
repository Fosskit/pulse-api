<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'code' => $this->code,
            'visit_id' => $this->visit_id,
            'date' => $this->date?->toISOString(),
            'total' => (float) $this->total,
            'percentage_discount' => (float) $this->percentage_discount,
            'amount_discount' => (float) $this->amount_discount,
            'discount' => (float) $this->discount,
            'received' => (float) $this->received,
            'remaining_balance' => (float) $this->remaining_balance,
            'is_fully_paid' => $this->is_fully_paid,
            'remark' => $this->remark,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships
            'visit' => $this->whenLoaded('visit', function () {
                return [
                    'id' => $this->visit->id,
                    'ulid' => $this->visit->ulid,
                    'patient' => $this->whenLoaded('visit.patient', function () {
                        return [
                            'id' => $this->visit->patient->id,
                            'code' => $this->visit->patient->code,
                            'full_name' => $this->visit->patient->full_name,
                        ];
                    }),
                ];
            }),

            'invoice_category' => $this->whenLoaded('invoiceCategory', function () {
                return [
                    'id' => $this->invoiceCategory->id,
                    'name' => $this->invoiceCategory->name,
                    'description' => $this->invoiceCategory->description,
                ];
            }),

            'payment_type' => $this->whenLoaded('paymentType', function () {
                return [
                    'id' => $this->paymentType->id,
                    'name' => $this->paymentType->name,
                    'description' => $this->paymentType->description,
                ];
            }),

            'invoice_items' => $this->whenLoaded('invoiceItems', function () {
                return InvoiceItemResource::collection($this->invoiceItems);
            }),

            // Summary calculations
            'summary' => [
                'subtotal' => (float) $this->calculateTotal(),
                'total_discount' => (float) $this->calculateDiscount(),
                'final_amount' => (float) $this->calculateFinalAmount(),
                'amount_paid' => (float) $this->received,
                'balance_due' => (float) $this->remaining_balance,
                'payment_status' => $this->is_fully_paid ? 'paid' : 'pending',
            ],
        ];
    }
}