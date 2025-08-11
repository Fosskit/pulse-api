<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class ProcessRefundRequest extends FormRequest
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
            'payment_method_id' => 'sometimes|integer|exists:terms,id',
            'refund_date' => 'sometimes|date',
            'reference_number' => 'sometimes|string|max:100',
            'notes' => 'sometimes|string|max:500',
            'processed_by' => 'sometimes|integer|exists:users,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'Refund amount is required.',
            'amount.min' => 'Refund amount must be greater than zero.',
            'payment_method_id.exists' => 'The selected payment method is invalid.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $payment = $this->route('payment');
            
            if ($payment && $this->amount > $payment->amount) {
                $validator->errors()->add('amount', 'Refund amount cannot exceed original payment amount.');
            }
        });
    }
}