<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_callbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_withdrawal_id')->constrained()->cascadeOnDelete();
            $table->string('idempotency_key')->unique();
            $table->string('external_event_id')->nullable()->unique();
            $table->string('bank_reference');
            $table->string('status', 32);
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('bank_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_callbacks');
    }
};
