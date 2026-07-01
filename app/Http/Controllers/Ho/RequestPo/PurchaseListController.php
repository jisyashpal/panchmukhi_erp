<?php

namespace App\Http\Controllers\Ho\RequestPo;

use App\Http\Controllers\Controller;
use App\Models\Plant;
use App\Services\ReportService;
use App\Support\ReportFilters;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseListController extends Controller
{
    public function __construct(
        protected ReportService $reports
    ) {}

    public function index(Request $request): View
    {
        $filters = ReportFilters::fromRequest($request);

        return view('ho.request-po.purchase-list.index', [
            'plants' => Plant::orderBy('name')->get(),
            'filters' => $filters,
            'supplierTotals' => $this->reports->supplierWisePurchaseTotals($filters),
        ]);
    }
}
