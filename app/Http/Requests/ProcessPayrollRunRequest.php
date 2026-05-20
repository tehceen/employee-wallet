<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProcessPayrollRunRequest extends FormRequest
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
            'idempotency_key' => ['required', 'string', 'max:255'],
            'external_event_id' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.employee_external_ref' => ['required', 'string', 'max:255'],
            'items.*.amount' => ['required', 'integer', 'min:1'],
            'items.*.external_item_id' => ['nullable', 'string', 'max:255'],
        ];
    }
}
