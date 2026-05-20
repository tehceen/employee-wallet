<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('status', 32);
            $table->string('idempotency_key')->unique();
            $table->string('bank_reference')->nullable()->unique();
            $table->string('failure_reason')->nullable();
            $table->foreignId('hold_ledger_entry_id')->nullable()->constrained('ledger_entries')->nullOnDelete();
            $table->foreignId('release_ledger_entry_id')->nullable()->constrained('ledger_entries')->nullOnDelete();
            $table->foreignId('settle_ledger_entry_id')->nullable()->constrained('ledger_entries')->nullOnDelete();
            $table->timestamp('requested_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['wallet_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_withdrawals');
    }
};
