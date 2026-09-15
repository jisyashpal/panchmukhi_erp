<?php

namespace App\Http\Controllers\Ho\RequestPo;

use App\Http\Controllers\Concerns\ManagesPurchaseImports;
use App\Http\Controllers\Concerns\StreamsPurchasePdf;
use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\Plant;
use App\Services\NotificationService;
use App\Services\PurchasePricingService;
use App\Support\FinancialYearResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PurchaseController extends Controller
{
    use ManagesPurchaseImports, StreamsPurchasePdf;

    public function __construct(
        protected PurchasePricingService $pricing,
        protected NotificationService $notifications
    ) {}

    public function index(Request $request): View
    {
        $query = Purchase::with(['plant', 'supplier', 'purchaseRequest', 'latestHoComment.user'])
            ->purchaseOrders()
            ->when($request->filled('plant_id'), fn ($q) => $q->where('plant_id', $request->integer('plant_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('purchase_request_id'), fn ($q) => $q->where('purchase_request_id', $request->integer('purchase_request_id')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->string('search');
                $q->where(function ($q) use ($search) {
                    $q->where('reference_no', 'like', '%'.$search.'%')
                        ->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'like', '%'.$search.'%'));
                });
            });

        app(FinancialYearResolver::class)->applyToQuery($query, 'ordered_at', $request);

        $purchases = $query->latest('ordered_at')
            ->paginate(20)
            ->withQueryString();

        return view('ho.request-po.purchases.index', [
            'purchases' => $purchases,
            'plants' => Plant::orderBy('name')->get(),
        ]);
    }

    public function show(Purchase $purchase): View
    {
        $purchase->load(['plant', 'supplier', 'items.product', 'purchaseRequest', 'receiver', 'dispatcher', 'hoComments.user']);

        return view('ho.request-po.purchases.show', [
            'purchase' => $purchase,
            'pdfDefaults' => $this->purchasePdfDefaults($purchase),
            'pdfRoute' => route('ho.purchases.pdf', $purchase),
            'excelRoute' => route('ho.purchases.excel', $purchase),
            'wordRoute' => route('ho.purchases.word', $purchase),
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

    public function excel(Request $request, Purchase $purchase): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return $this->streamPurchaseExcel($request, $purchase);
    }

    public function word(Request $request, Purchase $purchase): Response
    {
        return $this->streamPurchaseWord($request, $purchase);
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
