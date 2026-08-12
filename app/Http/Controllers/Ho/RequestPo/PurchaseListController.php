<?php

namespace App\Http\Controllers\Ho\RequestPo;

use App\Http\Controllers\Controller;
use App\Models\Plant;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Services\ReportService;
use App\Support\ReportFilters;
use Illuminate\Http\RedirectResponse;
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

    public function storePayment(Request $request, Supplier $supplier): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        SupplierPayment::create([
            'supplier_id' => $supplier->id,
            'amount' => $validated['amount'],
            'payment_date' => $validated['payment_date'],
            'notes' => $validated['notes'] ?? null,
            'paid_by' => $request->user()?->id,
        ]);

        return redirect()
            ->back()
            ->with('success', 'Payment recorded for '.$supplier->name.'.');
    }
}
