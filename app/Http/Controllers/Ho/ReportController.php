<?php

namespace App\Http\Controllers\Ho;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(
        protected ReportService $reports
    ) {}

    public function index(): View
    {
        return view('ho.reports.index');
    }

    public function productLife(Request $request): View
    {
        $siteId = $request->integer('site_id') ?: null;

        return view('ho.reports.product-life', [
            'sites' => Site::orderBy('name')->get(),
            'siteId' => $siteId,
            'entries' => $this->reports->productLifeUsage($siteId),
        ]);
    }

    public function categoryPurchase(Request $request): View
    {
        $siteId = $request->integer('site_id') ?: null;

        return view('ho.reports.category-purchase', [
            'sites' => Site::orderBy('name')->get(),
            'siteId' => $siteId,
            'rows' => $this->reports->categoryWisePurchaseAmount($siteId),
        ]);
    }

    public function multipleUsage(Request $request): View
    {
        $siteId = $request->integer('site_id') ?: null;

        return view('ho.reports.multiple-usage', [
            'sites' => Site::orderBy('name')->get(),
            'siteId' => $siteId,
            'rows' => $this->reports->multipleUsageHighlights($siteId),
        ]);
    }

    public function usageByCategory(Request $request): View
    {
        $siteId = $request->integer('site_id') ?: null;

        return view('ho.reports.usage-by-category', [
            'sites' => Site::orderBy('name')->get(),
            'siteId' => $siteId,
            'rows' => $this->reports->productUsageByCategory($siteId),
        ]);
    }
}
