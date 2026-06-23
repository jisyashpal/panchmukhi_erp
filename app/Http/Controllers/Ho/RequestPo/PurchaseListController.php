<?php

namespace App\Http\Controllers\Ho\RequestPo;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseListController extends Controller
{
    public function __construct(
        protected ReportService $reports
    ) {}

    public function index(Request $request): View
    {
        $siteId = $request->integer('site_id') ?: null;

        return view('ho.request-po.purchase-list.index', [
            'sites' => Site::orderBy('name')->get(),
            'siteId' => $siteId,
            'supplierTotals' => $this->reports->supplierWisePurchaseTotals($siteId),
        ]);
    }
}
