<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FundWalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:100'],
            'payment_gateway' => ['required', 'string', 'in:paystack,flutterwave'],
            'callback_url' => ['nullable', 'url'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.min' => 'Minimum funding amount is 100.',
            'payment_gateway.in' => 'Supported payment gateways are paystack and flutterwave.',
        ];
    }
}

