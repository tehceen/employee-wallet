<?php

namespace App\Models;

use App\Enums\BankWithdrawalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankWithdrawal extends Model
{
    protected $fillable = [
        'wallet_id',
        'employee_id',
        'amount',
        'status',
        'idempotency_key',
        'bank_reference',
        'failure_reason',
        'hold_ledger_entry_id',
        'release_ledger_entry_id',
        'settle_ledger_entry_id',
        'requested_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => BankWithdrawalStatus::class,
            'amount' => 'integer',
            'requested_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function holdLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(LedgerEntry::class, 'hold_ledger_entry_id');
    }

    public function callbacks(): HasMany
    {
        return $this->hasMany(BankCallback::class);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            BankWithdrawalStatus::Completed,
            BankWithdrawalStatus::Failed,
        ], true);
    }
}
