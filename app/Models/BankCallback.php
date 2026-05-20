<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankCallback extends Model
{
    protected $fillable = [
        'bank_withdrawal_id',
        'idempotency_key',
        'external_event_id',
        'bank_reference',
        'status',
        'payload',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function bankWithdrawal(): BelongsTo
    {
        return $this->belongsTo(BankWithdrawal::class);
    }
}
