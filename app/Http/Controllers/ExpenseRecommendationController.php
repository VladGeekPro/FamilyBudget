<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Supplier;
use App\Services\ExpensePredictionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class ExpenseRecommendationController extends Controller
{
    public function predict(Request $request, ExpensePredictionService $service): JsonResponse
    {
        try {
            $result = $service->predictForUser((int) $request->user()->id);

            return response()->json($result['payload'], $result['status']);
        } catch (RuntimeException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], 503);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => 'error',
                'message' => 'Unexpected error while predicting expenses.',
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'sum' => ['required', 'numeric', 'gt:0'],
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $supplier = Supplier::query()->findOrFail($validated['supplier_id']);

        $expense = Expense::query()->create([
            'date' => $validated['date'],
            'sum' => $validated['sum'],
            'supplier_id' => $validated['supplier_id'],
            'user_id' => $validated['user_id'],
            'category_id' => $supplier->category_id,
        ]);

        return response()->json([
            'status' => 'ok',
            'expense_id' => $expense->id,
        ], 201);
    }
}
