<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBillRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'bill_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:bill_date',
            'supplier_id' => 'required|exists:suppliers,id',
            'notes' => 'nullable|string|max:1000',
            'status' => 'required|in:draft,unpaid',

            // Items validation
            'items' => 'required|array|min:1',
            'items.*.expense_group_id' => 'required|exists:chart_of_accounts,id',
            'items.*.expense_account_id' => 'nullable|exists:chart_of_accounts,id',
            'items.*.expense_account_name' => 'required|string|max:100',
            'items.*.description' => 'required|string|max:255',
            'items.*.amount' => 'required|numeric|min:0.01',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'At least one expense item is required.',
            'items.min' => 'At least one expense item is required.',
            'item.*.expense_group_id.required' => 'Please select an expense group for each item.',
            'items.*.expense_account_name.required' => 'Please select or enter an account head for each item.',
            'items.*.description.required' => 'Please enter a description for each item.',
            'items.*.amount.required' => 'Please enter an amount for each item.',
            'items.*.amount.min' => 'Amount must be greater than zero.',
        ];
    }
}
