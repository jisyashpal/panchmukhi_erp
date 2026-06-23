<?php

namespace App\Http\Controllers\Ho\Reports;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Site;
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
    ) {}

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

    /** @return array{sites: \Illuminate\Database\Eloquent\Collection, categories: \Illuminate\Database\Eloquent\Collection, products: \Illuminate\Database\Eloquent\Collection} */
    private function filterOptions(): array
    {
        return [
            'sites' => Site::orderBy('name')->get(['id', 'name']),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'products' => Product::orderBy('name')->get(['id', 'name']),
        ];
    }

    private function exportProductLife(ReportFilters $filters): StreamedResponse
    {
        $entries = $this->reports->productLifeUsage($filters, forExport: true);

        return SpreadsheetDownload::csv('product-life-report.csv', [
            'Date', 'Site', 'Product', 'Category', 'Machine', 'Qty', 'Life hrs', 'Notes',
        ], $entries->map(fn ($e) => [
            $e->used_at->format('d M Y'),
            $e->site->name,
            $e->product->name,
            $e->product->category?->name ?? '',
            $e->machine?->name ?? '',
            $e->quantity,
            $e->life_hours,
            $e->notes ?? '',
        ]));
    }

    private function exportCategoryPurchase(ReportFilters $filters): StreamedResponse
    {
        $rows = $this->reports->categoryWisePurchaseAmount($filters);

        return SpreadsheetDownload::csv('category-purchase-report.csv', [
            'Category', 'Qty', 'Amount',
        ], $rows->map(fn ($r) => [
            $r->category_name,
            $r->total_qty,
            $r->total_amount,
        ]));
    }

    private function exportMultipleUsage(ReportFilters $filters): StreamedResponse
    {
        $rows = $this->reports->multipleUsageHighlights($filters, forExport: true);

        return SpreadsheetDownload::csv('multiple-usage-report.csv', [
            'Product', 'Use count', 'Total qty',
        ], $rows->map(fn ($r) => [
            $r->product?->name ?? '',
            $r->use_count,
            $r->total_qty,
        ]));
    }

    private function exportUsageByCategory(ReportFilters $filters): StreamedResponse
    {
        $rows = $this->reports->productUsageByCategory($filters);

        return SpreadsheetDownload::csv('usage-by-category-report.csv', [
            'Category', 'Qty', 'Life hours',
        ], $rows->map(fn ($r) => [
            $r->category_name,
            $r->total_qty,
            $r->total_life_hours,
        ]));
    }
}
