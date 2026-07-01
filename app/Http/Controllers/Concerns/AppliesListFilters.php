<?php

namespace App\Http\Controllers\Concerns;

use App\Support\FinancialYearResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait AppliesListFilters
{
    protected function applyPlantProductFilters(
        Builder $query,
        Request $request,
        string $itemsTable = 'items',
        string $dateColumn = 'created_at'
    ): void {
        if ($request->filled('plant_id')) {
            $query->where('plant_id', $request->integer('plant_id'));
        }

        if ($request->filled('product_id')) {
            $query->whereHas($itemsTable, fn (Builder $q) => $q->where('product_id', $request->integer('product_id')));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        app(FinancialYearResolver::class)->applyToQuery($query, $dateColumn, $request);
    }
}
