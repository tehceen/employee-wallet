<?php

namespace App\Http\Controllers\Webhooks;

use App\Data\Payroll\PayrollRunInput;
use App\Enums\PayrollItemStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProcessPayrollRunRequest;
use App\Services\Payroll\PayrollService;
use Illuminate\Http\JsonResponse;

class PayrollWebhookController extends Controller
{
    public function __construct(
        private readonly PayrollService $payrollService,
    ) {}

    public function store(ProcessPayrollRunRequest $request): JsonResponse
    {
        $result = $this->payrollService->process(
            PayrollRunInput::fromArray($request->validated()),
        );

        $status = $result->wasDuplicate ? 200 : 201;

        return response()->json([
            'payroll_run_id' => $result->payrollRun->id,
            'status' => $result->payrollRun->status->value,
            'duplicate' => $result->wasDuplicate,
            'items_processed' => $result->payrollRun->items
                ->where('status', PayrollItemStatus::Completed)
                ->count(),
        ], $status);
    }
}
