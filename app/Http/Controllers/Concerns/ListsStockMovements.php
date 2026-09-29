<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\StockMovementType;
use App\Enums\StockReferenceType;
use App\Models\StockMovement;
use App\Support\ReportFilters;
use App\Support\SpreadsheetDownload;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait ListsStockMovements
{
    protected function exportStockMovements(StockMovementType $type, ReportFilters $filters): StreamedResponse
    {
        $movements = $this->stockService->listMovements($type, $filters, forExport: true);
        $purchaseReferences = $this->stockService->purchaseReferencesFor($movements);

        if ($type === StockMovementType::In) {
            return SpreadsheetDownload::csv('stock-in.csv', [
                'Date',
                'Plant',
                'Product',
                'SKU',
                'Category',
                'Qty',
                'Unit',
                'Source',
                'PO Ref',
                'Supplier',
                'By',
                'Notes',
            ], $movements->map(fn (StockMovement $m) => [
                $m->moved_at?->format('d M Y H:i') ?? '',
                $m->plant?->name ?? '',
                $m->product?->name ?? '',
                $m->product?->sku ?? '',
                $m->product?->category?->name ?? '',
                $m->quantity,
                $m->product?->unit?->abbreviation ?? $m->product?->unit?->name ?? '',
                $this->stockMovementSourceLabel($m),
                $m->reference_type === StockReferenceType::Purchase
                    ? ($purchaseReferences[$m->reference_id] ?? '')
                    : '',
                $m->supplier?->name ?? '',
                $m->performer?->name ?? '',
                $m->notes ?? '',
            ]));
        }

        return SpreadsheetDownload::csv('stock-out.csv', [
            'Date',
            'Plant',
            'Product',
            'SKU',
            'Category',
            'Qty',
            'Unit',
            'Vehicle / Machine',
            'Reason',
            'By',
            'Notes',
        ], $movements->map(fn (StockMovement $m) => [
            $m->moved_at?->format('d M Y H:i') ?? '',
            $m->plant?->name ?? '',
            $m->product?->name ?? '',
            $m->product?->sku ?? '',
            $m->product?->category?->name ?? '',
            $m->quantity,
            $m->product?->unit?->abbreviation ?? $m->product?->unit?->name ?? '',
            $m->vehicle?->displayLabel() ?? '',
            $m->reason ?? '',
            $m->performer?->name ?? '',
            $m->notes ?? '',
        ]));
    }

    protected function stockMovementSourceLabel(StockMovement $movement): string
    {
        return $movement->reference_type?->label() ?? 'Stock In';
    }
}
