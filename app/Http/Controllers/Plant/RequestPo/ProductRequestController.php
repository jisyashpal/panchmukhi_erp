<?php

namespace App\Http\Controllers\Plant\RequestPo;

use App\Enums\RequestStatus;
use App\Http\Controllers\Concerns\AuthorizesPermissions;
use App\Http\Controllers\Concerns\BelongsToUserPlant;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductRequest;
use App\Models\ProductRequestItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Plant;
use App\Models\SubCategory;
use App\Services\NotificationService;
use App\Support\PermissionSlug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductRequestController extends Controller
{
    use AuthorizesPermissions, BelongsToUserPlant;

    public function __construct(
        protected NotificationService $notifications
    ) {}

    public function index(Request $request): View
    {
        $isPurchaser = $this->purchaserSeesAllPlants();

        $query = ProductRequest::with(['plant', 'requester', 'items.product', 'items.subCategory', 'purchaseRequest.purchases'])
            ->withCount('items')
            ->withSum('items', 'quantity')
            ->when(! $isPurchaser, fn ($q) => $q->where('plant_id', $this->plantId()))
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('product_id')) {
            $query->whereHas('items', fn ($q) => $q->where('product_id', $request->integer('product_id')));
        }

        return view('plant.request-po.product-requests.index', [
            'productRequests' => $query->paginate(20)->withQueryString(),
            'products' => Product::orderBy('name')->get(),
            'plants' => $isPurchaser ? Plant::orderBy('name')->get() : collect(),
            'isPurchaser' => $isPurchaser,
        ]);
    }

    public function create(): View
    {
        return view('plant.request-po.product-requests.create', [
            'products' => Product::where('is_active', true)->orderBy('name')->get(),
            'subCategories' => SubCategory::where('is_active', true)->orderBy('name')->get(),
            'categories' => \App\Models\Category::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'requester_name' => ['required', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_name' => ['required', 'string', 'max:255'],
            'items.*.product_id' => ['nullable', 'exists:products,id'],
            'items.*.category_id' => ['nullable', 'exists:categories,id'],
            'items.*.sub_category_id' => ['nullable', 'exists:sub_categories,id'],
            'items.*.size' => ['nullable', 'string', 'max:100'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.remarks' => ['nullable', 'string'],
            'items.*.image' => ['nullable', 'image', 'max:5120'],
        ]);

        $plantId = $this->plantId();

        $productRequest = ProductRequest::create([
            'plant_id' => $plantId,
            'requested_by' => auth()->id(),
            'requester_name' => trim($data['requester_name']),
            'remarks' => $data['remarks'] ?? null,
            'status' => RequestStatus::Submitted,
        ]);

        foreach ($data['items'] as $index => $item) {
            $productId = $item['product_id'] ?? null;
            $itemName = trim($item['item_name']);
            $size = isset($item['size']) ? trim((string) $item['size']) : null;
            $subCategoryId = $item['sub_category_id'] ?? null;

            if ($productId) {
                $product = Product::find($productId);
                if ($product) {
                    $itemName = $product->name;
                    $size = $size ?: $product->size;
                    $subCategoryId = $subCategoryId ?: $product->sub_category_id;
                }
            }

            $imagePath = null;
            if ($request->hasFile("items.{$index}.image")) {
                $file = $request->file("items.{$index}.image");
                $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('uploads'), $fileName);
                $imagePath = 'uploads/' . $fileName;
            }

            ProductRequestItem::create([
                'product_request_id' => $productRequest->id,
                'product_id' => $productId,
                'item_name' => $productId ? null : $itemName,
                'size' => $size ?: null,
                'sub_category_id' => $subCategoryId,
                'quantity' => $item['quantity'],
                'remarks' => $item['remarks'] ?? null,
                'image_path' => $imagePath,
            ]);
        }

        $this->notifications->notifyProductRequestSubmitted($productRequest);

        return redirect()->route('plant.product-requests.show', $productRequest)
            ->with('success', 'Product request submitted.');
    }

    public function show(ProductRequest $productRequest): View
    {
        $this->authorizePlantAccess($productRequest);
        $productRequest->load(['plant', 'requester', 'items.product', 'items.subCategory', 'items.supplier', 'purchaseRequest.purchases']);

        return view('plant.request-po.product-requests.show', compact('productRequest'));
    }

    public function edit(ProductRequest $productRequest): View|RedirectResponse
    {
        $this->authorizePlantAccess($productRequest);
        $this->authorizePermission(PermissionSlug::PLANT_MANAGE_PURCHASE_REQUESTS);

        if (in_array($productRequest->status, [RequestStatus::Completed, RequestStatus::Cancelled])) {
            return redirect()->route('plant.product-requests.index')
                ->with('error', 'Completed or cancelled product requests cannot be edited.');
        }

        $productRequest->load(['items.product']);

        return view('plant.request-po.product-requests.edit', compact('productRequest'));
    }

    public function update(Request $request, ProductRequest $productRequest): RedirectResponse
    {
        $this->authorizePlantAccess($productRequest);
        $this->authorizePermission(PermissionSlug::PLANT_MANAGE_PURCHASE_REQUESTS);

        if (in_array($productRequest->status, [RequestStatus::Completed, RequestStatus::Cancelled])) {
            return redirect()->route('plant.product-requests.index')
                ->with('error', 'Completed or cancelled product requests cannot be edited.');
        }

        $data = $request->validate([
            'remarks' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'exists:product_request_items,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.remarks' => ['nullable', 'string'],
            'items.*.image' => ['nullable', 'image', 'max:5120'],
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($productRequest, $data, $request) {
            $productRequest->update([
                'remarks' => $data['remarks'] ?? null,
            ]);

            foreach ($data['items'] as $index => $itemData) {
                $item = $productRequest->items()->find($itemData['id']);
                if ($item) {
                    $updateData = [
                        'quantity' => $itemData['quantity'],
                        'remarks' => $itemData['remarks'] ?? null,
                    ];

                    if ($request->hasFile("items.{$index}.image")) {
                        $file = $request->file("items.{$index}.image");
                        $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                        $file->move(public_path('uploads'), $fileName);
                        $updateData['image_path'] = 'uploads/' . $fileName;
                    }

                    $item->update($updateData);
                }
            }
        });

        return redirect()->route('plant.product-requests.show', $productRequest)
            ->with('success', 'Product request updated successfully.');
    }

    public function convertToPurchaseRequest(ProductRequest $productRequest): RedirectResponse
    {
        $this->authorizePermission(PermissionSlug::PLANT_MANAGE_PURCHASES);
        $this->authorizePlantAccess($productRequest);

        if ($productRequest->purchaseRequest) {
            return redirect()->route('plant.purchases.create', ['purchase_request_id' => $productRequest->purchaseRequest->id])
                ->with('info', 'Purchase request already exists. Complete the purchase order below.');
        }

        $purchaseRequest = PurchaseRequest::create([
            'plant_id' => $productRequest->plant_id,
            'product_request_id' => $productRequest->id,
            'requested_by' => auth()->id(),
            'plant_status' => RequestStatus::Completed,
            'status' => RequestStatus::Submitted,
            'remarks' => $productRequest->remarks,
        ]);

        foreach ($productRequest->items as $item) {
            PurchaseRequestItem::create([
                'purchase_request_id' => $purchaseRequest->id,
                'product_request_item_id' => $item->id,
                'product_id' => $item->product_id,
                'item_name' => $item->product_id ? null : $item->displayName(),
                'size' => $item->size,
                'sub_category_id' => $item->sub_category_id,
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

        return redirect()->route('plant.purchases.create', ['purchase_request_id' => $purchaseRequest->id])
            ->with('success', 'Product request converted. Complete the purchase order below.');
    }
}
