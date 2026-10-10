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
            'summary' => $this->reports->productLifeUsageSummary($filters),
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

    public function productPurchase(Request $request): View|StreamedResponse
    {
        $filters = ReportFilters::fromRequest($request);

        if ($request->query('export') === 'excel') {
            return $this->exportProductPurchase($filters);
        }

        return view('ho.reports.product-purchase', [
            ...$this->filterOptions(),
            'filters' => $filters,
            'rows' => $this->reports->productWisePurchaseAmount($filters),
        ]);
    }

    public function purchaseItems(Request $request): View|StreamedResponse
    {
        $filters = ReportFilters::fromRequest($request);

        if ($request->query('export') === 'excel') {
            return $this->exportPurchaseItems($filters);
        }

        if ($request->query('export') === 'all') {
            return $this->exportPurchaseItems(new ReportFilters, allTime: true);
        }

        return view('ho.reports.purchase-items', [
            ...$this->filterOptions(),
            'filters' => $filters,
            'items' => $this->reports->purchaseItemRegister($filters),
            'totals' => $this->reports->purchaseItemRegisterTotals($filters),
        ]);
    }

    public function productPurchaseLedger(Request $request, Product $product): View|StreamedResponse
    {
        $filters = ReportFilters::fromRequest($request);
        $items = $this->reports->productPurchaseLedger($product->id, $filters);

        if ($request->query('export') === 'excel') {
            return SpreadsheetDownload::csv("product-purchase-ledger-{$product->id}.csv", [
                'Date',
                'PO Ref',
                'Plant',
                'Supplier',
                'Qty',
                'Unit Price',
                'Line Total',
                'Status',
            ], $items->map(fn ($r) => [
                $r->ordered_at ? \Carbon\Carbon::parse($r->ordered_at)->format('d M Y') : '',
                $r->reference_no,
                $r->plant_name,
                $r->supplier_name ?? '—',
                $r->quantity,
                $r->unit_price,
                $r->line_total,
                $r->status,
            ]));
        }

        return view('ho.reports.product-purchase-ledger', [
            ...$this->filterOptions(),
            'filters' => $filters,
            'product' => $product,
            'items' => $items,
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

        if ($request->query('export') === 'vehicle-expense') {
            return $this->exportVehicleStockOutExpense($filters);
        }

        if ($request->query('export') === 'vehicle-products') {
            return $this->exportVehicleStockOutProducts($filters);
        }

        return view('ho.reports.usage-by-category', [
            ...$this->filterOptions(),
            'filters' => $filters,
            'rows' => $this->reports->productUsageByCategory($filters),
            'vehicleRows' => $this->reports->vehicleStockOutExpense($filters),
        ]);
    }

    public function vehicleStockOutProducts(Request $request, string $vehicle): View|StreamedResponse
    {
        $filters = ReportFilters::fromRequest($request);
        $unassigned = $vehicle === 'unassigned';

        if (! $unassigned && ! ctype_digit($vehicle)) {
            abort(404);
        }

        $vehicleModel = $unassigned ? null : Vehicle::query()->findOrFail((int) $vehicle);
        $scoped = new ReportFilters(
            plantId: $filters->plantId,
            dateFrom: $filters->dateFrom,
            dateTo: $filters->dateTo,
            categoryId: $filters->categoryId,
            productId: $filters->productId,
            vehicleId: $vehicleModel?->id,
            search: $filters->search,
            financialYear: $filters->financialYear,
        );
        $rows = $this->reports->vehicleStockOutProducts($scoped, unassignedOnly: $unassigned);

        if ($request->query('export') === 'excel') {
            return $this->exportVehicleStockOutProducts($scoped, $unassigned);
        }

        $label = $vehicleModel
            ? trim($vehicleModel->name.($vehicleModel->registration_no ? ' ('.$vehicleModel->registration_no.')' : ''))
            : 'Other / Plant use';

        return view('ho.reports.vehicle-stock-out-products', [
            ...$this->filterOptions(),
            'filters' => $filters,
            'vehicleKey' => $unassigned ? 'unassigned' : $vehicleModel->id,
            'vehicleLabel' => $label,
            'rows' => $rows,
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
            $use = $e->productUse;
            $unit = $use?->meterUnitLabel() ?? '';

            return [
                $e->moved_at->format('d M Y'),
                $e->plant->name,
                $e->vehicle?->displayLabel() ?? '',
                $e->product->name,
                $use?->replacedProduct?->name ?? '',
                $e->product->category?->name ?? '',
                $use?->subCategory?->name ?? $e->subCategory?->name ?? '',
                $use ? ($use->meter_type === 'km' ? 'KM' : 'Hours') : '',
                $use?->previous_reading !== null ? $use->previous_reading.' '.$unit : '',
                $use?->current_reading !== null ? $use->current_reading.' '.$unit : '',
                $use ? $use->life_hours.' '.$unit : '',
                $e->quantity,
                $use?->usage_count ?? 1,
                $e->performer->name ?? '',
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
        $vehicleRows = $this->reports->vehicleStockOutExpense($filters);

        return response()->streamDownload(function () use ($rows, $vehicleRows) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, ['Usage by Category']);
            fputcsv($handle, [
                'Category',
                'Total Qty Used',
                'Consumption Amount',
                'Usage Count',
                'Products',
                'Vehicles',
                'Total Life Used',
                'Avg Life Used/Entry',
            ]);
            foreach ($rows as $r) {
                fputcsv($handle, [
                    $r->category_name,
                    $r->total_qty,
                    $r->total_amount,
                    $r->usage_count,
                    $r->product_count,
                    $r->vehicle_count ?? 0,
                    $r->total_life_hours,
                    $r->avg_life_hours,
                ]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Vehicle-wise Stock Out Expense']);
            fputcsv($handle, [
                'Vehicle',
                'Categories',
                'Products',
                'Usage Entries',
                'Total Qty',
                'Amount',
            ]);
            foreach ($vehicleRows as $r) {
                $vehicle = $r->vehicle_id
                    ? trim($r->vehicle_name.($r->registration_no ? ' ('.$r->registration_no.')' : ''))
                    : 'Other / Plant use';

                fputcsv($handle, [
                    $vehicle,
                    $r->category_names,
                    $r->product_count,
                    $r->usage_count,
                    $r->total_qty,
                    $r->total_amount,
                ]);
            }

            fclose($handle);
        }, 'usage-by-category-report.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function exportVehicleStockOutExpense(ReportFilters $filters): StreamedResponse
    {
        $rows = $this->reports->vehicleStockOutExpense($filters);

        return SpreadsheetDownload::csv('vehicle-stock-out-expense.csv', [
            'Vehicle',
            'Categories',
            'Products',
            'Usage Entries',
            'Total Qty',
            'Amount',
        ], $rows->map(fn ($r) => [
            $this->vehicleExpenseLabel($r),
            $r->category_names,
            $r->product_count,
            $r->usage_count,
            $r->total_qty,
            $r->total_amount,
        ]));
    }

    private function exportVehicleStockOutProducts(ReportFilters $filters, bool $unassignedOnly = false): StreamedResponse
    {
        $rows = $this->reports->vehicleStockOutProducts($filters, $unassignedOnly);

        return SpreadsheetDownload::csv('vehicle-stock-out-products.csv', [
            'Vehicle',
            'Registration No',
            'Product',
            'Category',
            'Usage Entries',
            'Total Qty',
            'Amount',
        ], $rows->map(fn ($r) => [
            $r->vehicle_id ? $r->vehicle_name : 'Other / Plant use',
            $r->registration_no,
            $r->product_name,
            $r->category_name,
            $r->usage_count,
            $r->total_qty,
            $r->total_amount,
        ]));
    }

    private function vehicleExpenseLabel(object $row): string
    {
        if (! $row->vehicle_id) {
            return 'Other / Plant use';
        }

        return trim($row->vehicle_name.($row->registration_no ? ' ('.$row->registration_no.')' : ''));
    }

    private function exportPurchaseItems(ReportFilters $filters, bool $allTime = false): StreamedResponse
    {
        $items = $this->reports->purchaseItemRegister($filters, forExport: true, oldestFirst: $allTime);

        return SpreadsheetDownload::csv($allTime ? 'all-purchase-items.csv' : 'purchase-items-report.csv', [
            'Date',
            'PO Ref',
            'Plant',
            'Supplier',
            'Product',
            'SKU',
            'Category',
            'Unit',
            'Qty',
            'Unit Price',
            'Line Total',
            'Status',
        ], $items->map(fn ($r) => [
            $r->ordered_at ? \Carbon\Carbon::parse($r->ordered_at)->format('d M Y') : '',
            $r->reference_no,
            $r->plant_name,
            $r->supplier_name ?? '',
            $r->product_name,
            $r->sku ?? '',
            $r->category_name,
            $r->unit ?? '',
            $r->quantity,
            $r->unit_price,
            $r->line_total,
            $r->status,
        ]));
    }

    private function exportProductPurchase(ReportFilters $filters): StreamedResponse
    {
        $rows = $this->reports->productWisePurchaseAmount($filters);

        return SpreadsheetDownload::csv('product-purchase-report.csv', [
            'Product',
            'SKU',
            'Category',
            'Unit',
            'Orders',
            'Suppliers',
            'Total Qty',
            'Avg Rate',
            'Total Amount',
            'Last Purchased',
        ], $rows->map(fn ($r) => [
            $r->product_name,
            $r->sku ?? '',
            $r->category_name,
            $r->unit ?? '',
            $r->order_count,
            $r->supplier_count,
            $r->total_qty,
            $r->avg_unit_price,
            $r->total_amount,
            $r->last_purchased_at ? \Carbon\Carbon::parse($r->last_purchased_at)->format('d M Y') : '',
        ]));
    }

    private function exportPartyPurchase(ReportFilters $filters): StreamedResponse
    {
        $rows = $this->reports->supplierWisePurchaseTotals($filters);

        return SpreadsheetDownload::csv('party-purchase-report.csv', [
            'Party (Supplier)',
            'Orders',
            'B/F',
            'Amount',
            'Paid',
            'Balance',
        ], $rows->map(fn($r) => [
                        $r->supplier?->name ?? 'Unknown',
                        $r->order_count,
                        $r->brought_forward,
                        $r->total_amount,
                        $r->paid_amount,
                        $r->balance,
                    ]));
    }

    private function exportPartyLedger(Supplier $supplier, ReportFilters $filters): StreamedResponse
    {
        $rows = $this->reports->supplierLedger($supplier->id, $filters);

        return SpreadsheetDownload::csv("party-ledger-{$supplier->id}.csv", [
            'Date',
            'Type',
            'Reference',
            // 'Particulars',
            'Debit',
            'Credit',
            'Balance',
        ], $rows->map(fn ($row) => [
            $row->date?->format('d M Y') ?? '',
            match ($row->type) {
                'payment' => 'Payment',
                'opening' => 'Opening',
                'balance_bf' => 'Balance B/F',
                default => 'Purchase',
            },
            $row->reference.($row->plant ? ' / '.$row->plant : ''),
            // $row->particulars,
            $row->debit,
            $row->credit,
            $row->balance,
        ]));
    }

    public function categoryStockInOut(Request $request): View|StreamedResponse
    {
        $filters = ReportFilters::fromRequest($request);
        $data = $this->reports->categoryStockInOut($filters);

        if ($request->query('export') === 'excel') {
            return SpreadsheetDownload::csv('product-stock-in-out.csv', [
                'Product',
                'SKU',
                'Category',
                'Unit',
                'Stock In Qty',
                'Stock In Amount (₹)',
                'Stock Out Qty',
                'Net (In − Out)',
            ], collect($data['rows'])->map(fn ($r) => [
                $r->product,
                $r->sku ?? '',
                $r->category,
                $r->unit ?? '',
                $r->in_qty,
                number_format((float) $r->in_amount, 2, '.', ''),
                $r->out_qty,
                $r->in_qty - $r->out_qty,
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
