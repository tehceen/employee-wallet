<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BankWithdrawalCallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'bank_reference' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', Rule::in(['completed', 'failed'])],
            'idempotency_key' => ['required', 'string', 'max:255'],
            'external_event_id' => ['nullable', 'string', 'max:255'],
            'failure_reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
