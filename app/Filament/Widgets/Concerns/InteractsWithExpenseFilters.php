<?php

namespace App\Filament\Widgets\Concerns;

use App\Models\Expense;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

trait InteractsWithExpenseFilters
{
    protected function getNormalizedPageFilters(): array
    {
        return $this->pageFilters ?? [];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function resolveDateRangeFromFilters(): array
    {
        $filters = $this->getNormalizedPageFilters();

        $start = filled($filters['date_from'] ?? null)
            ? Carbon::parse($filters['date_from'])->startOfDay()
            : now()->startOfMonth();

        $end = filled($filters['date_to'] ?? null)
            ? Carbon::parse($filters['date_to'])->endOfDay()
            : now()->endOfMonth();

        if ($start->gt($end)) {
            [$start, $end] = [$end, $start];
        }

        return [$start, $end];
    }

    protected function applyExpenseFilters(Builder $query, bool $includeDateRange = true): Builder
    {
        $filters = $this->getNormalizedPageFilters();

        if ($includeDateRange) {
            [$start, $end] = $this->resolveDateRangeFromFilters();

            $query->whereBetween('expenses.date', [$start->toDateString(), $end->toDateString()]);
        }

        $userIds = $this->normalizeIdFilter($filters['user_ids'] ?? null);
        $categoryIds = $this->normalizeIdFilter($filters['category_ids'] ?? null);
        $supplierIds = $this->normalizeIdFilter($filters['supplier_ids'] ?? null);

        if ($userIds !== []) {
            $query->whereIn('expenses.user_id', $userIds);
        }

        if ($categoryIds !== []) {
            $query->whereIn('expenses.category_id', $categoryIds);
        }

        if ($supplierIds !== []) {
            $query->whereIn('expenses.supplier_id', $supplierIds);
        }

        if (filled($filters['sum_min'] ?? null)) {
            $query->where('expenses.sum', '>=', (float) $filters['sum_min']);
        }

        if (filled($filters['sum_max'] ?? null)) {
            $query->where('expenses.sum', '<=', (float) $filters['sum_max']);
        }

        return $query;
    }

    protected function expenseQuery(bool $includeDateRange = true): Builder
    {
        return $this->applyExpenseFilters(Expense::query(), $includeDateRange);
    }

    protected function formatMoney(float $amount): string
    {
        return number_format($amount, 2, ',', ' ') . ' MDL';
    }

    /**
     * @return array<int, int>
     */
    private function normalizeIdFilter(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $id): ?int => is_numeric($id) ? (int) $id : null,
            $value,
        )));
    }
}
