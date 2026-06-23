<?php

namespace App\Http\Controllers\Site\RequestPo;

use App\Enums\RequestStatus;
use App\Http\Controllers\Concerns\AuthorizesPermissions;
use App\Http\Controllers\Concerns\BelongsToUserSite;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductRequest;
use App\Models\ProductRequestItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Site;
use App\Services\NotificationService;
use App\Support\PermissionSlug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductRequestController extends Controller
{
    use AuthorizesPermissions, BelongsToUserSite;

    public function __construct(
        protected NotificationService $notifications
    ) {}

    public function index(Request $request): View
    {
        $isPurchaser = $this->purchaserSeesAllSites();

        $query = ProductRequest::with(['site', 'requester', 'items.product', 'purchaseRequest.purchases'])
            ->when(! $isPurchaser, fn ($q) => $q->where('site_id', $this->siteId()))
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('product_id')) {
            $query->whereHas('items', fn ($q) => $q->where('product_id', $request->integer('product_id')));
        }

        return view('site.request-po.product-requests.index', [
            'productRequests' => $query->paginate(20)->withQueryString(),
            'products' => Product::orderBy('name')->get(),
            'sites' => $isPurchaser ? Site::orderBy('name')->get() : collect(),
            'isPurchaser' => $isPurchaser,
        ]);
    }

    public function create(): View
    {
        return view('site.request-po.product-requests.create', [
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

        $this->notifications->notifyProductRequestSubmitted($productRequest);

        return redirect()->route('site.product-requests.show', $productRequest)
            ->with('success', 'Product request submitted.');
    }

    public function show(ProductRequest $productRequest): View
    {
        $this->authorizeSiteAccess($productRequest);
        $productRequest->load(['site', 'requester', 'items.product', 'items.supplier', 'purchaseRequest.purchases']);

        return view('site.request-po.product-requests.show', compact('productRequest'));
    }

    public function edit(ProductRequest $productRequest): View|RedirectResponse
    {
        $this->authorizeSiteAccess($productRequest);
        $this->authorizePermission(PermissionSlug::SITE_MANAGE_PURCHASE_REQUESTS);

        if (in_array($productRequest->status, [RequestStatus::Completed, RequestStatus::Cancelled])) {
            return redirect()->route('site.product-requests.index')
                ->with('error', 'Completed or cancelled product requests cannot be edited.');
        }

        $productRequest->load(['items.product']);

        return view('site.request-po.product-requests.edit', compact('productRequest'));
    }

    public function update(Request $request, ProductRequest $productRequest): RedirectResponse
    {
        $this->authorizeSiteAccess($productRequest);
        $this->authorizePermission(PermissionSlug::SITE_MANAGE_PURCHASE_REQUESTS);

        if (in_array($productRequest->status, [RequestStatus::Completed, RequestStatus::Cancelled])) {
            return redirect()->route('site.product-requests.index')
                ->with('error', 'Completed or cancelled product requests cannot be edited.');
        }

        $data = $request->validate([
            'remarks' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'exists:product_request_items,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.remarks' => ['nullable', 'string'],
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($productRequest, $data) {
            $productRequest->update([
                'remarks' => $data['remarks'] ?? null,
            ]);

            foreach ($data['items'] as $itemData) {
                $item = $productRequest->items()->find($itemData['id']);
                if ($item) {
                    $item->update([
                        'quantity' => $itemData['quantity'],
                        'remarks' => $itemData['remarks'] ?? null,
                    ]);
                }
            }
        });

        return redirect()->route('site.product-requests.show', $productRequest)
            ->with('success', 'Product request updated successfully.');
    }

    public function convertToPurchaseRequest(ProductRequest $productRequest): RedirectResponse
    {
        $this->authorizePermission(PermissionSlug::SITE_MANAGE_PURCHASES);
        $this->authorizeSiteAccess($productRequest);

        if ($productRequest->purchaseRequest) {
            return redirect()->route('site.purchases.create', ['purchase_request_id' => $productRequest->purchaseRequest->id])
                ->with('info', 'Purchase request already exists. Complete the purchase order below.');
        }

        $purchaseRequest = PurchaseRequest::create([
            'site_id' => $productRequest->site_id,
            'product_request_id' => $productRequest->id,
            'requested_by' => auth()->id(),
            'site_status' => RequestStatus::Completed,
            'status' => RequestStatus::Submitted,
            'remarks' => $productRequest->remarks,
        ]);

        foreach ($productRequest->items as $item) {
            PurchaseRequestItem::create([
                'purchase_request_id' => $purchaseRequest->id,
                'product_id' => $item->product_id,
                'supplier_id' => $item->supplier_id,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price ?? 0,
            ]);
        }

        $productRequest->update([
            'status' => RequestStatus::Completed,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return redirect()->route('site.purchases.create', ['purchase_request_id' => $purchaseRequest->id])
            ->with('success', 'Product request converted. Complete the purchase order below.');
    }
}
