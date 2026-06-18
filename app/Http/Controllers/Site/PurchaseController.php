<?php

namespace App\Http\Controllers\Site;

use App\Enums\PurchaseStatus;
use App\Http\Controllers\Concerns\BelongsToUserSite;
use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseController extends Controller
{
    use BelongsToUserSite;

    public function __construct(
        protected StockService $stockService
    ) {}

    public function index(): View
    {
        $siteId = $this->siteId();

        $purchases = Purchase::with(['supplier'])
            ->where('site_id', $siteId)
            ->whereIn('status', [PurchaseStatus::Ordered, PurchaseStatus::Partial])
            ->latest()
            ->paginate(20);

        return view('site.purchases.index', compact('purchases'));
    }

    public function receiveForm(Purchase $purchase): View|RedirectResponse
    {
        $this->authorizeSite($purchase);

        if ($purchase->status === PurchaseStatus::Delivered) {
            return redirect()->route('site.purchases.index')
                ->with('error', 'Purchase already received.');
        }

        $purchase->load(['items.product', 'supplier']);

        return view('site.purchases.receive', compact('purchase'));
    }

    public function receive(Request $request, Purchase $purchase): RedirectResponse
    {
        $this->authorizeSite($purchase);

        if ($purchase->status === PurchaseStatus::Delivered) {
            return back()->with('error', 'Purchase already received.');
        }

        $data = $request->validate([
            'lines' => ['required', 'array'],
            'lines.*.product_id' => ['required', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0'],
        ]);

        $lines = collect($data['lines'])->filter(fn ($l) => (float) $l['quantity'] > 0)->values()->all();

        if (empty($lines)) {
            return back()->with('error', 'Enter at least one quantity to receive.');
        }

        $this->stockService->recordInFromPurchase($purchase, $lines, (int) auth()->id());

        $purchase->update([
            'status' => PurchaseStatus::Delivered,
            'delivered_at' => now(),
            'received_by' => auth()->id(),
            'received_at' => now(),
        ]);

        return redirect()->route('site.purchases.index')
            ->with('success', 'Stock in recorded for this purchase.');
    }
}
