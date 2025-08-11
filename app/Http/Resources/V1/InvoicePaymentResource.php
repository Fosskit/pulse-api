<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoicePaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'invoice_id' => $this->invoice_id,
            'amount' => (float) $this->amount,
            'absolute_amount' => (float) $this->absolute_amount,
            'payment_type' => $this->payment_type,
            'is_refund' => $this->is_refund,
            'payment_date' => $this->payment_date?->toISOString(),
            'reference_number' => $this->reference_number,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships
            'payment_method' => $this->whenLoaded('paymentMethod', function () {
                return [
                    'id' => $this->paymentMethod->id,
                    'name' => $this->paymentMethod->name,
                    'description' => $this->paymentMethod->description,
                ];
            }),

            'processed_by' => $this->whenLoaded('processedBy', function () {
                return [
                    'id' => $this->processedBy->id,
                    'name' => $this->processedBy->name,
                    'email' => $this->processedBy->email,
                ];
            }),

            'original_payment' => $this->whenLoaded('originalPayment', function () {
                return [
                    'id' => $this->originalPayment->id,
                    'ulid' => $this->originalPayment->ulid,
                    'amount' => (float) $this->originalPayment->amount,
                    'payment_date' => $this->originalPayment->payment_date?->toISOString(),
                ];
            }),

            'invoice' => $this->whenLoaded('invoice', function () {
                return [
                    'id' => $this->invoice->id,
                    'ulid' => $this->invoice->ulid,
                    'code' => $this->invoice->code,
                    'total' => (float) $this->invoice->total,
                    'remaining_balance' => (float) $this->invoice->remaining_balance,
                ];
            }),
        ];
    }
}