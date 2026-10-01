<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCircleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'contribution_amount' => ['required', 'numeric', 'min:100'],
            'max_members' => ['required', 'integer', 'min:2', 'max:100'],
            'frequency' => ['required', 'string', 'in:daily,weekly,monthly'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'draw_method' => ['required', 'string', 'in:random,bidding,fixed'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'contribution_amount' => $this->input('contribution_amount', $this->input('amount_per_cycle')),
            'draw_method' => $this->input('draw_method', $this->input('payout_type')),
        ]);
    }

    public function messages(): array
    {
        return [
            'contribution_amount.min' => 'The contribution amount per cycle must be at least 100.',
            'max_members.min' => 'A circle must allow at least 2 members.',
            'frequency.in' => 'Frequency must be one of: daily, weekly, or monthly.',
            'start_date.after_or_equal' => 'Start date cannot be in the past.',
        ];
    }
}

