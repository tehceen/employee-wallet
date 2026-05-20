<?php

namespace App\Models;

use App\Enums\WalletType;
use Database\Factories\WalletFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['employee_id', 'name', 'type', 'balance', 'locked_balance', 'currency'])]
class Wallet extends Model
{
    /** @use HasFactory<WalletFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => WalletType::class,
            'balance' => 'integer',
            'locked_balance' => 'integer',
        ];
    }

    public function availableBalance(): int
    {
        return $this->balance;
    }

    public function totalBalance(): int
    {
        return $this->balance + $this->locked_balance;
    }

    public function bankWithdrawals(): HasMany
    {
        return $this->hasMany(BankWithdrawal::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }
}
