<?php

namespace Tests\Feature\Api;

use App\Enums\LedgerEntryType;
use App\Models\Wallet;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletLedgerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_ledger_returns_paginated_history_newest_first(): void
    {
        $wallet = Wallet::factory()->create(['balance' => 0]);
        $service = app(WalletService::class);

        $service->credit($wallet, 100, 'First');
        $service->credit($wallet, 50, 'Second');

        $response = $this->getJson("/api/wallets/{$wallet->id}/ledger?page=1");

        $response->assertOk()
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonCount(2, 'data');

        $this->assertSame('Second', $response->json('data.0.reason'));
        $this->assertSame('First', $response->json('data.1.reason'));
    }

    public function test_ledger_can_filter_by_type(): void
    {
        $wallet = Wallet::factory()->create(['balance' => 1_000_00]);
        $service = app(WalletService::class);

        $service->credit($wallet, 100, 'General credit');
        $service->debit($wallet, 40, 'General debit');

        $this->getJson("/api/wallets/{$wallet->id}/ledger?type=".LedgerEntryType::Debit->value)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', LedgerEntryType::Debit->value);
    }
}
