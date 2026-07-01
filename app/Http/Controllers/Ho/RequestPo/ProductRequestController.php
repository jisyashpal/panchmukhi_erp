<?php

namespace App\Http\Controllers\Ho\RequestPo;

use App\Enums\RequestStatus;
use App\Http\Controllers\Concerns\AppliesListFilters;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductRequest;
use App\Models\ProductRequestItem;
use App\Models\Plant;
use App\Models\Supplier;
use App\Services\NotificationService;
use App\Services\RequestWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductRequestController extends Controller
{
    use AppliesListFilters;

    public function __construct(
        protected RequestWorkflowService $workflow,
        protected NotificationService $notifications
    ) {}

    public function index(Request $request): View
    {
        $query = ProductRequest::with(['plant', 'requester', 'items.product', 'purchaseRequest.purchases'])
            ->latest();

        $this->applyPlantProductFilters($query, $request);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return view('ho.request-po.product-requests.index', [
            'productRequests' => $query->paginate(20)->withQueryString(),
            'plants' => Plant::orderBy('name')->get(),
            'products' => Product::orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('ho.request-po.product-requests.create', [
            'products' => Product::where('is_active', true)->orderBy('name')->get(),
            'plants' => Plant::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'plant_id' => ['required', 'exists:plants,id'],
            'remarks' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.remarks' => ['nullable', 'string'],
        ]);

        $productRequest = ProductRequest::create([
            'plant_id' => $data['plant_id'],
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

        $this->notifications->notifyProductRequestSubmitted($productRequest);

        return redirect()->route('ho.product-requests.show', $productRequest)
            ->with('success', 'Product request submitted.');
    }

    public function show(ProductRequest $productRequest): View
    {
        $productRequest->load(['plant', 'requester', 'items.product', 'items.supplier', 'purchaseRequest.purchases']);

        return view('ho.request-po.product-requests.show', compact('productRequest'));
    }

    public function edit(ProductRequest $productRequest): View
    {
        $productRequest->load(['items.product', 'items.supplier']);

        return view('ho.request-po.product-requests.edit', [
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
