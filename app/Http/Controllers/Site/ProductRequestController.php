<?php

namespace App\Http\Controllers\Site;

use App\Enums\RequestStatus;
use App\Http\Controllers\Concerns\BelongsToUserSite;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductRequest;
use App\Models\ProductRequestItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductRequestController extends Controller
{
    use BelongsToUserSite;

    public function index(Request $request): View
    {
        $siteId = $this->siteId();

        $query = ProductRequest::with(['requester', 'items.product'])
            ->where('site_id', $siteId)
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('product_id')) {
            $query->whereHas('items', fn ($q) => $q->where('product_id', $request->integer('product_id')));
        }

        return view('site.product-requests.index', [
            'productRequests' => $query->paginate(20)->withQueryString(),
            'products' => Product::orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('site.product-requests.create', [
            'products' => Product::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'remarks' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.remarks' => ['nullable', 'string'],
        ]);

        $siteId = $this->siteId();

        $productRequest = ProductRequest::create([
            'site_id' => $siteId,
            'requested_by' => auth()->id(),
            'remarks' => $data['remarks'] ?? null,
            'status' => RequestStatus::Submitted,
        ]);

        foreach ($data['items'] as $item) {
            ProductRequestItem::create([
                'product_request_id' => $productRequest->id,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'remarks' => $item['remarks'] ?? null,
            ]);
        }

        return redirect()->route('site.product-requests.show', $productRequest)
            ->with('success', 'Product request submitted.');
    }

    public function show(ProductRequest $productRequest): View
    {
        $this->authorizeSite($productRequest);
        $productRequest->load(['site', 'requester', 'items.product', 'purchaseRequest']);

        return view('site.product-requests.show', compact('productRequest'));
    }
}
