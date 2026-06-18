<?php

namespace App\Http\Controllers\Ho;

use App\Enums\StockReferenceType;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Site;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockInController extends Controller
{
    public function __construct(
        protected StockService $stockService
    ) {}

    public function create(Request $request): View
    {
        return view('ho.stocks.stock-in', [
            'sites' => Site::orderBy('name')->get(),
            'products' => Product::where('is_active', true)->orderBy('name')->get(),
            'purchases' => Purchase::with('site')
                ->whereIn('status', ['ordered', 'partial'])
                ->latest()
                ->limit(50)
                ->get(),
            'selectedPurchase' => $request->filled('purchase_id')
                ? Purchase::with('items.product')->find($request->integer('purchase_id'))
                : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'site_id' => ['required', 'exists:sites,id'],
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'purchase_id' => ['nullable', 'exists:purchases,id'],
            'photo_confirmed' => ['required', 'boolean'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ]);

        $meta = [
            'photo_confirmed' => $request->boolean('photo_confirmed'),
            'reason' => $data['reason'] ?? null,
            'notes' => $data['notes'] ?? 'Stock in',
        ];

        if ($request->hasFile('photo')) {
            $meta['photo_path'] = $request->file('photo')->store('stock-photos', 'public');
        }

        if (! empty($data['purchase_id'])) {
            $purchase = Purchase::findOrFail($data['purchase_id']);
            $this->stockService->recordInFromPurchase($purchase, [[
                'product_id' => $data['product_id'],
                'quantity' => $data['quantity'],
            ]], (int) auth()->id(), $meta);
        } else {
            $this->stockService->recordIn(
                (int) $data['site_id'],
                (int) $data['product_id'],
                (float) $data['quantity'],
                (int) auth()->id(),
                StockReferenceType::StockIn,
                null,
                $meta
            );
        }

        return redirect()->route('ho.stocks.index', ['site_id' => $data['site_id']])
            ->with('success', 'Stock in recorded.');
    }
}
