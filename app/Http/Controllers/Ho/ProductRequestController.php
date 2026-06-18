<?php

namespace App\Http\Controllers\Ho;

use App\Enums\RequestStatus;
use App\Http\Controllers\Concerns\AppliesListFilters;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductRequest;
use App\Models\Site;
use App\Models\Supplier;
use App\Services\RequestWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductRequestController extends Controller
{
    use AppliesListFilters;

    public function __construct(
        protected RequestWorkflowService $workflow
    ) {}

    public function index(Request $request): View
    {
        $query = ProductRequest::with(['site', 'requester', 'items.product'])
            ->latest();

        $this->applySiteProductFilters($query, $request);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return view('ho.product-requests.index', [
            'productRequests' => $query->paginate(20)->withQueryString(),
            'sites' => Site::orderBy('name')->get(),
            'products' => Product::orderBy('name')->get(),
        ]);
    }

    public function show(ProductRequest $productRequest): View
    {
        $productRequest->load(['site', 'requester', 'items.product', 'items.supplier', 'purchaseRequest']);

        return view('ho.product-requests.show', compact('productRequest'));
    }

    public function edit(ProductRequest $productRequest): View
    {
        $productRequest->load(['items.product', 'items.supplier']);

        return view('ho.product-requests.edit', [
            'productRequest' => $productRequest,
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, ProductRequest $productRequest): RedirectResponse
    {
        $data = $request->validate([
            'remarks' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'exists:product_request_items,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.gst_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.supplier_id' => ['nullable', 'exists:suppliers,id'],
            'items.*.billing_notes' => ['nullable', 'string'],
        ]);

        $productRequest->update(['remarks' => $data['remarks'] ?? $productRequest->remarks]);

        foreach ($data['items'] as $itemData) {
            $item = $productRequest->items()->findOrFail($itemData['id']);
            $item->update([
                'quantity' => $itemData['quantity'],
                'unit_price' => $itemData['unit_price'] ?? 0,
                'gst_rate' => $itemData['gst_rate'] ?? 0,
                'supplier_id' => $itemData['supplier_id'] ?? null,
                'billing_notes' => $itemData['billing_notes'] ?? null,
            ]);
        }

        if ($productRequest->status === RequestStatus::Submitted) {
            $this->workflow->advanceProductRequest($productRequest, RequestStatus::InProgress, (int) auth()->id());
        }

        return redirect()->route('ho.product-requests.show', $productRequest)
            ->with('success', 'Product request updated with billing details.');
    }

    public function updateStatus(Request $request, ProductRequest $productRequest): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:in_progress,completed,cancelled'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $status = RequestStatus::from($data['status']);

        if ($status === RequestStatus::Cancelled) {
            $this->workflow->cancelProductRequest($productRequest, (int) auth()->id(), $data['reason'] ?? null);
        } else {
            $this->workflow->advanceProductRequest($productRequest, $status, (int) auth()->id());
        }

        return back()->with('success', 'Status updated.');
    }
}
