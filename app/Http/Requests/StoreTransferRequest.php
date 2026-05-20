<?php

namespace App\Http\Requests;

use App\Models\Wallet;
use Illuminate\Foundation\Http\FormRequest;

class StoreTransferRequest extends FormRequest
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
            'from_wallet_id' => ['required', 'integer', 'exists:wallets,id'],
            'to_wallet_id' => ['required', 'integer', 'exists:wallets,id', 'different:from_wallet_id'],
            'amount' => ['required', 'integer', 'min:1'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $from = Wallet::query()->find($this->integer('from_wallet_id'));
            $to = Wallet::query()->find($this->integer('to_wallet_id'));

            if ($from === null || $to === null) {
                return;
            }

            if ($from->currency !== $to->currency) {
                $validator->errors()->add('to_wallet_id', 'Wallets must share the same currency.');
            }

            if ($from->employee_id === null || $to->employee_id === null) {
                $validator->errors()->add('to_wallet_id', 'Both wallets must belong to an employee.');
            }

            if ($from->employee_id !== $to->employee_id) {
                $validator->errors()->add('to_wallet_id', 'Transfers are limited to wallets owned by the same employee.');
            }
        });
    }
}
