<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class RecordPaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:0.01',
            'payment_method_id' => 'required|integer|exists:terms,id',
            'payment_date' => 'sometimes|date',
            'reference_number' => 'sometimes|string|max:100',
            'notes' => 'sometimes|string|max:500',
            'processed_by' => 'sometimes|integer|exists:users,id',
            'item_allocations' => 'sometimes|array',
            'item_allocations.*.item_id' => 'required_with:item_allocations|integer|exists:invoice_items,id',
            'item_allocations.*.amount' => 'required_with:item_allocations|numeric|min:0.01',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'Payment amount is required.',
            'amount.min' => 'Payment amount must be greater than zero.',
            'payment_method_id.required' => 'Payment method is required.',
            'payment_method_id.exists' => 'The selected payment method is invalid.',
            'item_allocations.*.item_id.exists' => 'One or more invoice items are invalid.',
            'item_allocations.*.amount.min' => 'Item allocation amounts must be greater than zero.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->has('item_allocations')) {
                $totalAllocated = collect($this->item_allocations)->sum('amount');
                
                if ($totalAllocated > $this->amount) {
                    $validator->errors()->add('item_allocations', 'Total item allocations cannot exceed payment amount.');
                }
            }
        });
    }
}