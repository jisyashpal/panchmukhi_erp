<?php

namespace App\Http\Controllers\Ho\RequestPo;

use App\Http\Controllers\Concerns\StreamsPurchasePdf;
use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\Site;
use App\Services\NotificationService;
use App\Services\PurchasePricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PurchaseController extends Controller
{
    use StreamsPurchasePdf;

    public function __construct(
        protected PurchasePricingService $pricing,
        protected NotificationService $notifications
    ) {}

    public function index(Request $request): View
    {
        $purchases = Purchase::with(['site', 'supplier', 'purchaseRequest', 'latestHoComment.user'])
            ->when($request->filled('site_id'), fn ($q) => $q->where('site_id', $request->integer('site_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('purchase_request_id'), fn ($q) => $q->where('purchase_request_id', $request->integer('purchase_request_id')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->string('search');
                $q->where(function ($q) use ($search) {
                    $q->where('reference_no', 'like', '%'.$search.'%')
                        ->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->when($request->filled('from_date'), fn ($q) => $q->whereDate('ordered_at', '>=', $request->date('from_date')))
            ->when($request->filled('to_date'), fn ($q) => $q->whereDate('ordered_at', '<=', $request->date('to_date')))
            ->latest('ordered_at')
            ->paginate(20)
            ->withQueryString();

        return view('ho.request-po.purchases.index', [
            'purchases' => $purchases,
            'sites' => Site::orderBy('name')->get(),
        ]);
    }

    public function show(Purchase $purchase): View
    {
        $purchase->load(['site', 'supplier', 'items.product', 'purchaseRequest', 'receiver', 'dispatcher', 'hoComments.user']);

        return view('ho.request-po.purchases.show', [
            'purchase' => $purchase,
            'pdfDefaults' => $this->purchasePdfDefaults($purchase),
            'pdfRoute' => route('ho.purchases.pdf', $purchase),
            'emailRoute' => route('ho.purchases.email', $purchase),
            'shareLinksRoute' => route('ho.purchases.share-links', $purchase),
            'pdfFilename' => $this->purchasePdfFilename($purchase),
            'supplier' => $purchase->supplier,
        ]);
    }

    public function pdf(Request $request, Purchase $purchase): Response
    {
        return $this->streamPurchasePdf($request, $purchase);
    }

    public function sharedPdf(Request $request, Purchase $purchase): Response
    {
        return $this->streamPurchasePdf($request, $purchase);
    }

    public function shareLinks(Request $request, Purchase $purchase): JsonResponse
    {
        return $this->purchaseShareLinksResponse($request, $purchase, 'ho.purchases.shared-pdf');
    }

    public function email(Request $request, Purchase $purchase): JsonResponse
    {
        return $this->emailPurchasePdfToSupplier($request, $purchase);
    }

    public function storeComment(Request $request, Purchase $purchase): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $purchase->hoComments()->create([
            'user_id' => (int) auth()->id(),
            'body' => $data['body'],
        ]);

        return back()->with('success', 'HO comment added to '.$purchase->reference_no.'.');
    }

}
