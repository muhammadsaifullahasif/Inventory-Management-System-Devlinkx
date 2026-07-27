<?php

namespace App\Http\Requests;

use App\Models\Bill;
use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
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
            'bill_id' => 'required|exists:bills,id',
            'payment_date' => 'required|date',
            'payment_account_id' => 'required|exists:chart_of_accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:bank,cash',
            'reference' => 'nullable|string|max:100',
            'status' => 'required|in:draft,posted',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->bill_id) {
                $bill = Bill::find($this->bill_id);
                if ($bill) {
                    $remaining = $bill->total_amount - $bill->paid_amount;
                    if ($this->amount > $remaining) {
                        $validator->errors()->add(
                            'amount',
                            "Payment amount cannot exceed remaining balance of " . number_format($remaining, 2)
                        );
                    }

                    if (!$bill->isPayable()) {
                        $validator->errors()->add(
                            'bill_id',
                            'This bill is not payable. It may be a draft or already fully paid.'
                        );
                    }
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'bill_id.required' => 'Please select a bill.',
            'payment_account_id.required' => 'Please select a payment account.',
            'amount.required' => 'Please enter the payment amount.',
            'amount.min' => 'Payment amount must be greater than zero.',
            'payment_method.required' => 'Please select a payment method.',
        ];
    }
}
