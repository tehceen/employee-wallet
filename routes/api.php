<?php

use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\TransferController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\WalletLedgerController;
use App\Http\Controllers\Api\WithdrawalController;
use App\Http\Controllers\Webhooks\BankWebhookController;
use App\Http\Controllers\Webhooks\PayrollWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::get('/employees', [EmployeeController::class, 'index']);
Route::get('/employees/{employee}', [EmployeeController::class, 'show']);
Route::get('/wallets', [WalletController::class, 'index']);

Route::post('/wallets/transfers', [TransferController::class, 'store']);
Route::get('/wallets/{wallet}/ledger', [WalletLedgerController::class, 'index']);
Route::post('/wallets/{wallet}/withdrawals', [WithdrawalController::class, 'store']);
Route::get('/wallets/{wallet}/withdrawals/{withdrawalId}', [WithdrawalController::class, 'show']);

Route::post('/webhooks/payroll/runs', [PayrollWebhookController::class, 'store']);
Route::post('/webhooks/bank/withdrawal-status', [BankWebhookController::class, 'withdrawalStatus'])
    ->name('webhooks.bank.withdrawal-status');
Route::post('/webhooks/bank/callback', [BankWebhookController::class, 'withdrawalStatus'])
    ->name('webhooks.bank.callback');
