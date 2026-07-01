<?php

namespace App\Http\Controllers\Plant\RequestPo;

use App\Enums\RequestStatus;
use App\Http\Controllers\Concerns\AuthorizesPermissions;
use App\Http\Controllers\Concerns\BelongsToUserPlant;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Supplier;
use App\Support\PermissionSlug;
use App\Services\NotificationService;
use App\Services\RequestWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseRequestController extends Controller
{
    use AuthorizesPermissions, BelongsToUserPlant;

    public function __construct(
        protected RequestWorkflowService $workflow,
        protected NotificationService $notifications
    ) {}

    public function index(Request $request): View
    {
        $query = PurchaseRequest::with(['items.product', 'productRequest', 'purchases', 'plant'])
            ->when(! $this->purchaserSeesAllPlants(), fn ($q) => $q->where('plant_id', $this->plantId()))
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('product_id')) {
            $query->whereHas('items', fn ($q) => $q->where('product_id', $request->integer('product_id')));
        }

        return view('plant.request-po.purchase-requests.index', [
            'purchaseRequests' => $query->paginate(20)->withQueryString(),
            'products' => Product::orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('plant.request-po.purchase-requests.create', [
            'products' => Product::where('is_active', true)->orderBy('name')->get(),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'remarks' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.supplier_id' => ['nullable', 'exists:suppliers,id'],
        ]);

        $plantId = $this->plantId();

        $purchaseRequest = PurchaseRequest::create([
            'plant_id' => $plantId,
            'requested_by' => auth()->id(),
            'remarks' => $data['remarks'] ?? null,
            'plant_status' => RequestStatus::Submitted,
            'status' => RequestStatus::Submitted,
        ]);

        foreach ($data['items'] as $item) {
            PurchaseRequestItem::create([
                'purchase_request_id' => $purchaseRequest->id,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'] ?? 0,
                'supplier_id' => $item['supplier_id'] ?? null,
            ]);
        }

        $this->notifications->notifyPurchaseRequestSubmitted($purchaseRequest);

        return redirect()->route('plant.purchase-requests.show', $purchaseRequest)
            ->with('success', 'Purchase request created.');
    }

    public function show(PurchaseRequest $purchaseRequest): View
    {
        $this->authorizePlantAccess($purchaseRequest);
        $purchaseRequest->load(['plant', 'requester', 'items.product', 'items.supplier', 'productRequest', 'purchases.supplier']);

        return view('plant.request-po.purchase-requests.show', [
            'purchaseRequest' => $purchaseRequest,
            'canEdit' => auth()->user()->canManagePurchaseRequests(),
        ]);
    }

    public function edit(PurchaseRequest $purchaseRequest): View
    {
        $this->authorizePermission(PermissionSlug::PLANT_MANAGE_PURCHASE_REQUESTS);
        $this->authorizePlantAccess($purchaseRequest);
        $purchaseRequest->load(['items.product', 'items.supplier']);

        return view('plant.request-po.purchase-requests.edit', [
            'purchaseRequest' => $purchaseRequest,
            'products' => Product::where('is_active', true)->orderBy('name')->get(),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $this->authorizePermission(PermissionSlug::PLANT_MANAGE_PURCHASE_REQUESTS);
        $this->authorizePlantAccess($purchaseRequest);

        $data = $request->validate([
            'remarks' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'exists:purchase_request_items,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.supplier_id' => ['nullable', 'exists:suppliers,id'],
        ]);

        $purchaseRequest->update(['remarks' => $data['remarks'] ?? $purchaseRequest->remarks]);

        foreach ($data['items'] as $itemData) {
            $item = $purchaseRequest->items()->findOrFail($itemData['id']);
            $item->update([
                'quantity' => $itemData['quantity'],
                'unit_price' => $itemData['unit_price'] ?? 0,
                'supplier_id' => $itemData['supplier_id'] ?? null,
            ]);
        }

        if ($purchaseRequest->status === RequestStatus::Submitted) {
            $this->workflow->advancePurchaseRequest($purchaseRequest, RequestStatus::InProgress, (int) auth()->id());
        }

        return redirect()->route('plant.purchase-requests.show', $purchaseRequest)
            ->with('success', 'Purchase request updated.');
    }

    public function updateStatus(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $this->authorizePermission(PermissionSlug::PLANT_MANAGE_PURCHASE_REQUESTS);
        $this->authorizePlantAccess($purchaseRequest);

        $data = $request->validate([
            'status' => ['required', 'in:in_progress,completed,cancelled'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $status = RequestStatus::from($data['status']);

        if ($status === RequestStatus::Cancelled) {
            $this->workflow->cancelPurchaseRequest($purchaseRequest, (int) auth()->id(), $data['reason'] ?? null);
        } else {
            $this->workflow->advancePurchaseRequest($purchaseRequest, $status, (int) auth()->id());
        }

        return back()->with('success', 'Status updated.');
    }
}
