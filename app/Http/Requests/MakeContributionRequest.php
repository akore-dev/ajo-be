<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MakeContributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'circle_id' => ['required', 'integer', 'exists:circles,id'],
            'cycle' => ['required', 'integer', 'min:1'],
            'payment_method' => ['required', 'string', 'in:wallet,card,bank_transfer'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'circle_id.exists' => 'The selected ROSCA circle does not exist.',
            'payment_method.in' => 'Payment method must be wallet, card, or bank_transfer.',
        ];
    }
}

