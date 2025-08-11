<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class GenerateInvoiceRequest extends FormRequest
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
            'invoice_category_id' => 'sometimes|integer|exists:terms,id',
            'date' => 'sometimes|date',
            'percentage_discount' => 'sometimes|numeric|min:0|max:100',
            'amount_discount' => 'sometimes|numeric|min:0',
            'remark' => 'sometimes|string|max:70',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'invoice_category_id.exists' => 'The selected invoice category is invalid.',
            'percentage_discount.max' => 'The percentage discount cannot exceed 100%.',
            'amount_discount.min' => 'The amount discount cannot be negative.',
            'remark.max' => 'The remark cannot exceed 70 characters.',
        ];
    }
}