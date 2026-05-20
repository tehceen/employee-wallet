<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained();
            $table->string('external_item_id')->nullable();
            $table->unsignedBigInteger('amount');
            $table->string('status', 32);
            $table->foreignId('ledger_entry_id')->nullable()->constrained('ledger_entries')->nullOnDelete();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id']);
            $table->unique(['payroll_run_id', 'external_item_id']);
            $table->index(['payroll_run_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_items');
    }
};
