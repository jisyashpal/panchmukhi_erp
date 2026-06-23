<?php

namespace App\Http\Controllers\Ho\Inventory;

use App\Enums\StockReferenceType;
use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\Product;
use App\Models\Site;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StockOutController extends Controller
{
    public function __construct(
        protected StockService $stockService
    ) {}

    public function create(Request $request): View
    {
        $siteId = $request->integer('site_id') ?: null;

        return view('ho.inventory.stocks.stock-out', [
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
            'usage_type' => ['required', 'in:machine,other'],
            'machine_id' => [
                'nullable',
                'exists:machines,id',
                Rule::exists('machines', 'id')->where(fn ($q) => $q->where('site_id', $request->integer('site_id'))),
            ],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'photo_confirmed' => ['required', 'boolean'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ]);

        if ($data['usage_type'] === 'other' && blank($data['reason'] ?? null)) {
            return back()->withInput()->withErrors([
                'reason' => 'Please specify where or how the stock was used.',
            ]);
        }

        $machineId = $data['usage_type'] === 'machine' ? ($data['machine_id'] ?? null) : null;

        $meta = [
            'machine_id' => $machineId,
            'photo_confirmed' => $request->boolean('photo_confirmed'),
            'reason' => $data['reason'] ?? null,
        ];

        if ($request->hasFile('photo')) {
            $meta['photo_path'] = $request->file('photo')->store('stock-photos', 'public');
        }

        $this->stockService->recordOut(
            (int) $data['site_id'],
            (int) $data['product_id'],
            (float) $data['quantity'],
            StockReferenceType::StockOut,
            null,
            (int) auth()->id(),
            $data['notes'] ?? null,
            $meta
        );

        return redirect()->route('ho.stocks.index', ['site_id' => $data['site_id']])
            ->with('success', 'Stock out recorded.');
    }
}
