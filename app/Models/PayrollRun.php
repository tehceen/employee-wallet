<?php

namespace App\Models;

use App\Enums\PayrollRunStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    protected $fillable = [
        'idempotency_key',
        'external_event_id',
        'status',
        'payload',
        'error_message',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PayrollRunStatus::class,
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === PayrollRunStatus::Completed;
    }

    public function isProcessing(): bool
    {
        return $this->status === PayrollRunStatus::Processing;
    }
}
