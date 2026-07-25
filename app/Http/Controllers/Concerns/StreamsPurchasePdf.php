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
use Symfony\Component\HttpFoundation\StreamedResponse;

trait StreamsPurchasePdf
{
    protected function purchasePdfDefaults(Purchase $purchase): array
    {
        return [
            'company_name' => config('company.name') ?: $purchase->plant->name,
            'company_address' => config('company.address') ?: ($purchase->plant->address ?? ''),
            'company_gstin' => config('company.gstin', ''),
            'terms_and_conditions' => config('company.purchase_terms', ''),
        ];
    }

    /**
     * @return array{company_name: string, company_address: ?string, company_gstin: ?string, terms_and_conditions: ?string}
     */
    protected function validatePurchasePdfHeader(Request $request): array
    {
        return $request->validate([
            'company_name' => ['required', 'string', 'max:200'],
            'company_address' => ['nullable', 'string', 'max:1000'],
            'company_gstin' => ['nullable', 'string', 'max:20'],
            'terms_and_conditions' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    /**
     * @return array{pdf: DomPdfDocument, filename: string, header: array{name: string, address: string, gstin: string, terms: string}}
     */
    protected function makePurchasePdf(Request $request, Purchase $purchase): array
    {
        $data = $this->validatePurchasePdfHeader($request);
        $purchase->load(['plant', 'supplier', 'items.product.unit']);
        $header = $this->purchaseExportHeader($data);
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

    /**
     * @param  array{company_name: string, company_address: ?string, company_gstin: ?string, terms_and_conditions: ?string}  $data
     * @return array{name: string, address: string, gstin: string, terms: string}
     */
    protected function purchaseExportHeader(array $data): array
    {
        $terms = array_key_exists('terms_and_conditions', $data)
            ? trim((string) ($data['terms_and_conditions'] ?? ''))
            : trim((string) config('company.purchase_terms', ''));

        return [
            'name' => $data['company_name'],
            'address' => $data['company_address'] ?? '',
            'gstin' => $data['company_gstin'] ?? '',
            'terms' => $terms,
        ];
    }

    protected function purchasePdfFilename(Purchase $purchase): string
    {
        return 'purchase-'.preg_replace('/[^A-Za-z0-9_-]+/', '-', $purchase->reference_no).'.pdf';
    }

    protected function purchaseExportBasename(Purchase $purchase): string
    {
        return 'purchase-'.preg_replace('/[^A-Za-z0-9_-]+/', '-', $purchase->reference_no);
    }

    protected function streamPurchasePdf(Request $request, Purchase $purchase): Response
    {
        $built = $this->makePurchasePdf($request, $purchase);

        return $built['pdf']->stream($built['filename']);
    }

    protected function streamPurchaseExcel(Request $request, Purchase $purchase): StreamedResponse
    {
        $data = $this->validatePurchasePdfHeader($request);
        $purchase->load(['plant', 'supplier', 'items.product.unit']);
        $header = $this->purchaseExportHeader($data);
        $filename = $this->purchaseExportBasename($purchase).'.csv';

        return response()->streamDownload(function () use ($purchase, $header) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['Purchase Order', $purchase->reference_no]);
            fputcsv($out, ['Company', $header['name']]);
            if ($header['address'] !== '') {
                fputcsv($out, ['Address', str_replace(["\r\n", "\n"], ', ', $header['address'])]);
            }
            if ($header['gstin'] !== '') {
                fputcsv($out, ['GSTIN', $header['gstin']]);
            }
            fputcsv($out, ['Supplier', $purchase->supplier->name]);
            fputcsv($out, ['Plant', $purchase->plant->name]);
            fputcsv($out, ['Date', $purchase->ordered_at?->format('d-m-Y') ?? now()->format('d-m-Y')]);
            fputcsv($out, ['Tax Type', $purchase->is_interstate ? 'Inter-state (IGST)' : 'Intra-state (CGST + SGST)']);
            if ($purchase->supplier_invoice_no) {
                fputcsv($out, ['Quotation No.', $purchase->supplier_invoice_no]);
                fputcsv($out, ['Quotation Date', $purchase->supplier_invoice_date?->format('d-m-Y') ?? '']);
            }
            fputcsv($out, []);
            fputcsv($out, ['#', 'Description', 'SKU', 'Size/Part', 'Qty', 'Unit', 'Rate', 'GST%', 'Taxable', 'CGST', 'SGST', 'IGST', 'Amount']);

            foreach ($purchase->items as $index => $item) {
                fputcsv($out, [
                    $index + 1,
                    $item->product->name,
                    $item->product->sku ?? '',
                    $item->product->size ?? '',
                    number_format((float) $item->quantity, 2, '.', ''),
                    $item->product->unit?->name ?? '',
                    number_format((float) $item->unit_price, 2, '.', ''),
                    number_format((float) $item->gst_rate, 2, '.', ''),
                    number_format((float) $item->taxable_amount, 2, '.', ''),
                    number_format((float) $item->cgst_amount, 2, '.', ''),
                    number_format((float) $item->sgst_amount, 2, '.', ''),
                    number_format((float) $item->igst_amount, 2, '.', ''),
                    number_format((float) $item->line_total, 2, '.', ''),
                ]);
            }

            fputcsv($out, []);
            fputcsv($out, ['Taxable Amount', number_format((float) $purchase->subtotal, 2, '.', '')]);
            fputcsv($out, ['CGST', number_format((float) $purchase->total_cgst, 2, '.', '')]);
            fputcsv($out, ['SGST', number_format((float) $purchase->total_sgst, 2, '.', '')]);
            fputcsv($out, ['IGST', number_format((float) $purchase->total_igst, 2, '.', '')]);
            fputcsv($out, ['Total GST', number_format((float) $purchase->total_gst, 2, '.', '')]);
            fputcsv($out, ['Grand Total (INR)', number_format((float) $purchase->grand_total, 2, '.', '')]);

            if ($header['terms'] !== '') {
                fputcsv($out, []);
                fputcsv($out, ['Terms and Conditions']);
                foreach (preg_split("/\r\n|\n|\r/", $header['terms']) as $line) {
                    if (trim($line) !== '') {
                        fputcsv($out, [$line]);
                    }
                }
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function streamPurchaseWord(Request $request, Purchase $purchase): Response
    {
        $data = $this->validatePurchasePdfHeader($request);
        $purchase->load(['plant', 'supplier', 'items.product.unit']);
        $header = $this->purchaseExportHeader($data);
        $filename = $this->purchaseExportBasename($purchase).'.doc';

        $html = view('exports.purchase-order-word', [
            'purchase' => $purchase,
            'header' => $header,
        ])->render();

        return response($html, 200, [
            'Content-Type' => 'application/msword; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    protected function purchaseShareLinksResponse(Request $request, Purchase $purchase, string $signedPdfRoute): JsonResponse
    {
        $defaults = $this->purchasePdfDefaults($purchase);

        $header = $this->validatePurchasePdfHeader($request);

        if (! filled($header['company_name'])) {
            $header['company_name'] = $defaults['company_name'];
        }

        if (! array_key_exists('terms_and_conditions', $header) || $header['terms_and_conditions'] === null) {
            $header['terms_and_conditions'] = $defaults['terms_and_conditions'];
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
