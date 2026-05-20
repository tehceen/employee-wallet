<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListEmployeesRequest;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;

class EmployeeController extends Controller
{
    public function index(ListEmployeesRequest $request): JsonResponse
    {
        $query = Employee::query()->orderBy('name');

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('external_ref', 'like', "%{$search}%");
            });
        }

        $employees = $query->paginate(
            perPage: $request->integer('per_page', 20),
            page: $request->integer('page', 1),
        );

        return response()->json([
            'data' => $employees->getCollection()->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'external_ref' => $employee->external_ref,
                'name' => $employee->name,
            ]),
            'meta' => [
                'current_page' => $employees->currentPage(),
                'per_page' => $employees->perPage(),
                'total' => $employees->total(),
                'last_page' => $employees->lastPage(),
            ],
        ]);
    }

    public function show(Employee $employee): JsonResponse
    {
        $employee->load('wallets');

        return response()->json([
            'id' => $employee->id,
            'external_ref' => $employee->external_ref,
            'name' => $employee->name,
            'wallets' => $employee->wallets->map(fn ($wallet): array => [
                'id' => $wallet->id,
                'type' => $wallet->type?->value,
                'name' => $wallet->name,
                'currency' => $wallet->currency,
                'available_balance' => $wallet->availableBalance(),
                'locked_balance' => $wallet->locked_balance,
                'total_balance' => $wallet->totalBalance(),
            ]),
        ]);
    }
}
