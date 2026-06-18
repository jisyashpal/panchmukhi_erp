<?php

namespace App\Http\Controllers\Ho;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\Product;
use App\Models\ProductUseEntry;
use App\Models\Site;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductLifeController extends Controller
{
    public function __construct(
        protected StockService $stockService
    ) {}

    public function index(Request $request): View
    {
        $entries = ProductUseEntry::with(['site', 'product', 'machine', 'recorder'])
            ->when($request->filled('site_id'), fn ($q) => $q->where('site_id', $request->integer('site_id')))
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->integer('product_id')))
            ->latest('used_at')
            ->paginate(20)
            ->withQueryString();

        return view('ho.product-life.index', [
            'entries' => $entries,
            'sites' => Site::orderBy('name')->get(),
            'products' => Product::orderBy('name')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $siteId = $request->integer('site_id') ?: null;

        return view('ho.product-life.create', [
            'sites' => Site::orderBy('name')->get(),
            'products' => Product::where('is_active', true)->orderBy('name')->get(),
            'machines' => Machine::when($siteId, fn ($q) => $q->where('site_id', $siteId))
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'siteId' => $siteId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'site_id' => ['required', 'exists:sites,id'],
            'product_id' => ['required', 'exists:products,id'],
            'machine_id' => ['nullable', 'exists:machines,id'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'life_hours' => ['nullable', 'numeric', 'min:0'],
            'usage_count' => ['nullable', 'integer', 'min:1'],
            'used_at' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($data) {
            $entry = ProductUseEntry::create([
                'site_id' => $data['site_id'],
                'product_id' => $data['product_id'],
                'machine_id' => $data['machine_id'] ?? null,
                'quantity' => $data['quantity'],
                'life_hours' => $data['life_hours'] ?? 0,
                'usage_count' => $data['usage_count'] ?? 1,
                'used_at' => $data['used_at'],
                'recorded_by' => auth()->id(),
                'notes' => $data['notes'] ?? null,
            ]);

            $this->stockService->recordProductUse($entry);
        });

        return redirect()->route('ho.product-life.index')->with('success', 'Product life entry recorded.');
    }
}
