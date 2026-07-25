<?php

namespace App\Http\Controllers\Ho\Reports;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Plant;
use App\Models\Supplier;
use App\Models\Vehicle;
use App\Services\ReportService;
use App\Support\ReportFilters;
use App\Support\SpreadsheetDownload;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        protected ReportService $reports
    ) {
    }

    public function index(): View
    {
        return view('ho.reports.index');
    }

    public function productLife(Request $request): View|StreamedResponse
    {
        $filters = ReportFilters::fromRequest($request);

        if ($request->query('export') === 'excel') {
            return $this->exportProductLife($filters);
        }

        return view('ho.reports.product-life', [
            ...$this->filterOptions(),
            'filters' => $filters,
            'entries' => $this->reports->productLifeUsage($filters),
        ]);
    }

    public function categoryPurchase(Request $request): View|StreamedResponse
    {
        $filters = ReportFilters::fromRequest($request);

        if ($request->query('export') === 'excel') {
            return $this->exportCategoryPurchase($filters);
        }

        return view('ho.reports.category-purchase', [
            ...$this->filterOptions(),
            'filters' => $filters,
            'rows' => $this->reports->categoryWisePurchaseAmount($filters),
        ]);
    }

    public function multipleUsage(Request $request): View|StreamedResponse
    {
        $filters = ReportFilters::fromRequest($request);

        if ($request->query('export') === 'excel') {
            return $this->exportMultipleUsage($filters);
        }

        return view('ho.reports.multiple-usage', [
            ...$this->filterOptions(),
            'filters' => $filters,
            'rows' => $this->reports->multipleUsageHighlights($filters),
        ]);
    }

    public function usageByCategory(Request $request): View|StreamedResponse
    {
        $filters = ReportFilters::fromRequest($request);

        if ($request->query('export') === 'excel') {
            return $this->exportUsageByCategory($filters);
        }

        return view('ho.reports.usage-by-category', [
            ...$this->filterOptions(),
            'filters' => $filters,
            'rows' => $this->reports->productUsageByCategory($filters),
        ]);
    }

    public function partyPurchase(Request $request): View|StreamedResponse
    {
        $filters = ReportFilters::fromRequest($request);

        if ($request->query('export') === 'excel') {
            return $this->exportPartyPurchase($filters);
        }

        return view('ho.reports.party-purchase', [
            ...$this->filterOptions(),
            'filters' => $filters,
            'rows' => $this->reports->supplierWisePurchaseTotals($filters),
        ]);
    }

    public function partyLedger(Request $request, Supplier $supplier): View|StreamedResponse
    {
        $filters = ReportFilters::fromRequest($request);

        if ($request->query('export') === 'excel') {
            return $this->exportPartyLedger($supplier, $filters);
        }

        return view('ho.reports.party-ledger', [
            ...$this->filterOptions(),
            'filters' => $filters,
            'supplier' => $supplier,
            'rows' => $this->reports->supplierLedger($supplier->id, $filters),
        ]);
    }

    /** @return array{plants: \Illuminate\Database\Eloquent\Collection, categories: \Illuminate\Database\Eloquent\Collection, products: \Illuminate\Database\Eloquent\Collection, vehicles: \Illuminate\Database\Eloquent\Collection} */
    private function filterOptions(): array
    {
        return [
            'plants' => Plant::orderBy('name')->get(['id', 'name']),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'products' => Product::orderBy('name')->get(['id', 'name']),
            'vehicles' => Vehicle::where('is_active', true)->orderBy('name')->get(['id', 'name', 'registration_no']),
        ];
    }

    private function exportProductLife(ReportFilters $filters): StreamedResponse
    {
        $entries = $this->reports->productLifeUsage($filters, forExport: true);

        return SpreadsheetDownload::csv('product-life-report.csv', [
            'Date',
            'Plant',
            'Vehicle',
            'Current Item',
            'Replaced Item',
            'Category',
            'Item Type',
            'Meter',
            'Previous Reading',
            'Current Reading',
            'Life Used',
            'Qty',
            'Usage Count',
            'Recorded By',
            'Notes',
        ], $entries->map(function ($e) {
            $unit = $e->meterUnitLabel();

            return [
                $e->used_at->format('d M Y'),
                $e->plant->name,
                $e->vehicle?->displayLabel() ?? '',
                $e->product->name,
                $e->replacedProduct?->name ?? '',
                $e->product->category?->name ?? '',
                $e->subCategory?->name ?? '',
                $e->meter_type === 'km' ? 'KM' : 'Hours',
                $e->previous_reading !== null ? $e->previous_reading.' '.$unit : '',
                $e->current_reading !== null ? $e->current_reading.' '.$unit : '',
                $e->life_hours.' '.$unit,
                $e->quantity,
                $e->usage_count,
                $e->recorder->name ?? '',
                $e->notes ?? '',
            ];
        }));
    }

    private function exportCategoryPurchase(ReportFilters $filters): StreamedResponse
    {
        $rows = $this->reports->categoryWisePurchaseAmount($filters);

        return SpreadsheetDownload::csv('category-purchase-report.csv', [
            'Category',
            'Items Purchased',
            'Products',
            'Orders',
            'Total Qty',
            'Total Amount',
        ], $rows->map(fn($r) => [
                        $r->category_name,
                        $r->top_items ?? '',
                        $r->product_count,
                        $r->order_count,
                        $r->total_qty,
                        $r->total_amount,
                    ]));
    }

    private function exportMultipleUsage(ReportFilters $filters): StreamedResponse
    {
        $rows = $this->reports->multipleUsageHighlights($filters, forExport: true);

        return SpreadsheetDownload::csv('multiple-usage-report.csv', [
            'Product',
            'Category',
            'Use Count',
            'Total Qty',
            'Avg Qty/Use',
            'Total Life Used',
            'Avg Life Used',
            'Plants',
            'Vehicles',
            'Last Used',
        ], $rows->map(fn($r) => [
                        $r->product_name,
                        $r->category_name,
                        $r->use_count,
                        $r->total_qty,
                        $r->avg_qty_per_use,
                        $r->total_life_hours,
                        $r->avg_life_hours,
                        $r->plant_count,
                        $r->vehicle_count ?? 0,
                        $r->last_used_at ? \Carbon\Carbon::parse($r->last_used_at)->format('d M Y') : '',
                    ]));
    }

    private function exportUsageByCategory(ReportFilters $filters): StreamedResponse
    {
        $rows = $this->reports->productUsageByCategory($filters);

        return SpreadsheetDownload::csv('usage-by-category-report.csv', [
            'Category',
            'Total Qty Used',
            'Usage Count',
            'Products',
            'Vehicles',
            'Total Life Used',
            'Avg Life Used/Entry',
        ], $rows->map(fn($r) => [
                        $r->category_name,
                        $r->total_qty,
                        $r->usage_count,
                        $r->product_count,
                        $r->vehicle_count ?? 0,
                        $r->total_life_hours,
                        $r->avg_life_hours,
                    ]));
    }

    private function exportPartyPurchase(ReportFilters $filters): StreamedResponse
    {
        $rows = $this->reports->supplierWisePurchaseTotals($filters);

        return SpreadsheetDownload::csv('party-purchase-report.csv', [
            'Party (Supplier)',
            'Orders',
            'Amount',
        ], $rows->map(fn($r) => [
                        $r->supplier?->name ?? 'Unknown',
                        $r->order_count,
                        $r->total_amount,
                    ]));
    }

    private function exportPartyLedger(Supplier $supplier, ReportFilters $filters): StreamedResponse
    {
        $rows = $this->reports->supplierLedger($supplier->id, $filters);

        return SpreadsheetDownload::csv("party-ledger-{$supplier->id}.csv", [
            'Date',
            'PO Ref',
            'Plant',
            'Items',
            'Subtotal',
            'GST',
            'Grand Total',
            'Status',
        ], $rows->map(fn($p) => [
                        $p->ordered_at?->format('d M Y') ?? '',
                        $p->reference_no,
                        $p->plant->name,
                        $p->items->map(fn($item) => "{$item->product->name} (x{$item->quantity})")->implode(', '),
                        $p->subtotal,
                        $p->total_gst,
                        $p->grand_total,
                        $p->status->label(),
                    ]));
    }

    public function categoryStockInOut(Request $request): View|StreamedResponse
    {
        $filters = ReportFilters::fromRequest($request);
        $data = $this->reports->categoryStockInOut($filters);

        if ($request->query('export') === 'excel') {
            return SpreadsheetDownload::csv('category-stock-in-out.csv', [
                'Category',
                'Stock In Qty',
                'Stock In Amount (₹)',
                'Stock Out Qty',
            ], collect($data['rows'])->map(fn($r) => [
                            $r->category,
                            $r->in_qty,
                            number_format($r->in_amount, 2),
                            $r->out_qty,
                        ]));
        }

        return view('ho.reports.category-stock-in-out', [
            ...$this->filterOptions(),
            'filters' => $filters,
            'rows' => $data['rows'],
            'totalInQty' => $data['in_qty'],
            'totalInAmt' => $data['in_amount'],
            'totalOutQty' => $data['out_qty'],
        ]);
    }

    public function categoryPurchaseLedger(Request $request, Category $category): View|StreamedResponse
    {
        $filters = ReportFilters::fromRequest($request);
        $items = $this->reports->categoryPurchaseLedger($category->id, $filters);

        if ($request->query('export') === 'excel') {
            return SpreadsheetDownload::csv("category-purchase-ledger-{$category->id}.csv", [
                'Date',
                'PO Ref',
                'Plant',
                'Supplier',
                'Product',
                'Qty',
                'Unit Price',
                'Line Total',
                'Status',
            ], $items->map(fn($r) => [
                            $r->ordered_at ? \Carbon\Carbon::parse($r->ordered_at)->format('d M Y') : '',
                            $r->reference_no,
                            $r->plant_name,
                            $r->supplier_name ?? '—',
                            $r->product_name,
                            $r->quantity,
                            $r->unit_price,
                            $r->line_total,
                            $r->status,
                        ]));
        }

        return view('ho.reports.category-purchase-ledger', [
            ...$this->filterOptions(),
            'filters' => $filters,
            'category' => $category,
            'items' => $items,
        ]);
    }
}
