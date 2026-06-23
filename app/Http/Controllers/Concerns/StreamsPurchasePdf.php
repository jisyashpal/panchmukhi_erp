<?php

namespace App\Http\Controllers\Concerns;

use App\Mail\PurchaseOrderMail;
use App\Models\Purchase;
use App\Support\PurchaseShareLinks;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;

trait StreamsPurchasePdf
{
    protected function purchasePdfDefaults(Purchase $purchase): array
    {
        return [
            'company_name' => config('company.name') ?: $purchase->site->name,
            'company_address' => config('company.address') ?: ($purchase->site->address ?? ''),
            'company_gstin' => config('company.gstin', ''),
        ];
    }

    /**
     * @return array{company_name: string, company_address: ?string, company_gstin: ?string}
     */
    protected function validatePurchasePdfHeader(Request $request): array
    {
        return $request->validate([
            'company_name' => ['required', 'string', 'max:200'],
            'company_address' => ['nullable', 'string', 'max:1000'],
            'company_gstin' => ['nullable', 'string', 'max:20'],
        ]);
    }

    /**
     * @return array{pdf: DomPdfDocument, filename: string, header: array{name: string, address: string, gstin: string}}
     */
    protected function makePurchasePdf(Request $request, Purchase $purchase): array
    {
        $data = $this->validatePurchasePdfHeader($request);

        $purchase->load(['site', 'supplier', 'items.product.unit']);

        $header = [
            'name' => $data['company_name'],
            'address' => $data['company_address'] ?? '',
            'gstin' => $data['company_gstin'] ?? '',
        ];

        $filename = $this->purchasePdfFilename($purchase);

        $pdf = Pdf::loadView('pdf.purchase-order', [
            'purchase' => $purchase,
            'header' => $header,
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'defaultFont' => 'DejaVu Sans',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
                'dpi' => 96,
            ]);

        return compact('pdf', 'filename', 'header');
    }

    protected function purchasePdfFilename(Purchase $purchase): string
    {
        return 'purchase-'.preg_replace('/[^A-Za-z0-9_-]+/', '-', $purchase->reference_no).'.pdf';
    }

    protected function streamPurchasePdf(Request $request, Purchase $purchase): Response
    {
        $built = $this->makePurchasePdf($request, $purchase);

        return $built['pdf']->stream($built['filename']);
    }

    protected function purchaseShareLinksResponse(Request $request, Purchase $purchase, string $signedPdfRoute): JsonResponse
    {
        $defaults = $this->purchasePdfDefaults($purchase);

        $header = $this->validatePurchasePdfHeader($request);

        if (! filled($header['company_name'])) {
            $header['company_name'] = $defaults['company_name'];
        }

        return response()->json(
            PurchaseShareLinks::forPurchase($purchase, $header, $signedPdfRoute)
        );
    }

    protected function emailPurchasePdfToSupplier(Request $request, Purchase $purchase): JsonResponse
    {
        $purchase->loadMissing('supplier');

        if (! filled($purchase->supplier->email)) {
            return response()->json([
                'message' => 'Supplier email address is not available.',
            ], 422);
        }

        $built = $this->makePurchasePdf($request, $purchase);

        Mail::to($purchase->supplier->email)->send(new PurchaseOrderMail(
            purchase: $purchase,
            pdfContent: $built['pdf']->output(),
            pdfFilename: $built['filename'],
            companyName: $built['header']['name'],
        ));

        return response()->json([
            'message' => 'Purchase order PDF emailed to '.$purchase->supplier->name.'.',
        ]);
    }
}
