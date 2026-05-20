<?php

namespace App\Models;

use App\Enums\PayrollItemStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollItem extends Model
{
    protected $fillable = [
        'payroll_run_id',
        'employee_id',
        'external_item_id',
        'amount',
        'status',
        'ledger_entry_id',
        'error_message',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PayrollItemStatus::class,
            'amount' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(LedgerEntry::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === PayrollItemStatus::Completed;
    }
}
