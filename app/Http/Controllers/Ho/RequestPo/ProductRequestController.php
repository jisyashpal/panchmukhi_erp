<?php

namespace App\Http\Controllers\Ho\RequestPo;

use App\Enums\RequestStatus;
use App\Http\Controllers\Concerns\AppliesListFilters;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductRequest;
use App\Models\ProductRequestItem;
use App\Models\Plant;
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
        $query = ProductRequest::with(['plant', 'requester', 'items.product', 'items.subCategory', 'purchaseRequest.purchases'])
            ->withCount('items')
            ->withSum('items', 'quantity')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $this->applyPlantProductFilters($query, $request);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->string('priority'));
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
            'products' => Product::with('unit')->where('is_active', true)->orderBy('name')->get(),
            'subCategories' => \App\Models\SubCategory::where('is_active', true)->orderBy('name')->get(),
            'categories' => \App\Models\Category::where('is_active', true)->orderBy('name')->get(),
            'plants' => Plant::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'plant_id' => ['required', 'exists:plants,id'],
            'requester_name' => ['required', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_name' => ['required', 'string', 'max:255'],
            'items.*.product_id' => ['nullable', 'exists:products,id'],
            'items.*.unit' => ['nullable', 'string', 'max:100'],
            'items.*.category_id' => ['nullable', 'exists:categories,id'],
            'items.*.sub_category_id' => ['nullable', 'exists:sub_categories,id'],
            'items.*.size' => ['nullable', 'string', 'max:100'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.priority' => ['nullable', 'string', 'in:low,medium,high,urgent'],
            'items.*.remarks' => ['nullable', 'string'],
            'items.*.image' => ['nullable', 'image', 'max:5120'],
        ]);

        $productRequest = ProductRequest::create([
            'plant_id' => $data['plant_id'],
            'requested_by' => auth()->id(),
            'requester_name' => trim($data['requester_name']),
            'remarks' => $data['remarks'] ?? null,
            'status' => RequestStatus::Submitted,
        ]);

        foreach ($data['items'] as $index => $item) {
            $productId = $item['product_id'] ?? null;
            $itemName = trim($item['item_name']);
            $size = isset($item['size']) ? trim((string) $item['size']) : null;
            $unit = isset($item['unit']) ? trim((string) $item['unit']) : null;
            $subCategoryId = $item['sub_category_id'] ?? null;

            if ($productId) {
                $product = Product::find($productId);
                if ($product) {
                    $itemName = $product->name;
                    $size = $size ?: $product->size;
                    $unit = $unit ?: ($product->unit?->abbreviation ?: $product->unit?->name);
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
                'unit' => $unit ?: null,
                'sub_category_id' => $subCategoryId,
                'quantity' => $item['quantity'],
                'priority' => $item['priority'] ?? \App\Enums\PriorityLevel::Medium->value,
                'remarks' => $item['remarks'] ?? null,
                'image_path' => $imagePath,
            ]);
        }

        $this->notifications->notifyProductRequestSubmitted($productRequest);

        return redirect()->route('ho.product-requests.show', $productRequest)
            ->with('success', 'Product request submitted.');
    }

    public function show(ProductRequest $productRequest): View
    {
        $productRequest->load(['plant', 'requester', 'items.product', 'items.subCategory', 'items.supplier', 'purchaseRequest.purchases']);

        return view('ho.request-po.product-requests.show', compact('productRequest'));
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
