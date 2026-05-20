<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bank simulation outcome
    |--------------------------------------------------------------------------
    |
    | When true, async withdrawal jobs simulate a successful bank callback.
    | Set to false in tests to exercise failure / release flows via the queue.
    |
    */
    'simulate_success' => env('BANK_SIMULATE_SUCCESS', true),

    /*
    |--------------------------------------------------------------------------
    | Auto-apply bank callback from queue job
    |--------------------------------------------------------------------------
    |
    | When true, SubmitBankWithdrawalJob simulates the bank webhook after submit.
    | Set to false to test POST /api/webhooks/bank/withdrawal-status manually
    | (withdrawal stays in processing with a real bank_reference).
    |
    */
    'auto_callback' => env('BANK_AUTO_CALLBACK', true),

];
