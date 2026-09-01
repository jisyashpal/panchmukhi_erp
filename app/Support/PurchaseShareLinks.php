<?php

namespace App\Support;

use App\Models\Purchase;
use Illuminate\Support\Facades\URL;

class PurchaseShareLinks
{
    public static function forPurchase(Purchase $purchase, array $header, string $signedPdfRoute): array
    {
        $purchase->loadMissing(['supplier', 'plant', 'items.product']);

        $message = self::buildMessage($purchase, $header);
        $pdfUrl = self::signedPdfUrl($purchase, $header, $signedPdfRoute);
        $whatsappMessage = self::buildWhatsAppMessage($purchase, $header);

        $phone = self::normalizePhone($purchase->supplier->phone);
        $email = $purchase->supplier->email;

        return [
            'whatsapp' => $phone ? self::whatsappUrl($phone, $whatsappMessage) : null,
            'whatsapp_message' => $whatsappMessage,
            'email' => $email ? self::mailtoUrl($email, 'Purchase Order '.$purchase->reference_no, $message) : null,
            'has_phone' => (bool) $phone,
            'has_email' => filled($email),
            'pdf_url' => $pdfUrl,
        ];
    }

    public static function signedPdfUrl(Purchase $purchase, array $header, string $routeName): string
    {
        return URL::temporarySignedRoute(
            $routeName,
            now()->addDays(7),
            [
                'purchase' => $purchase->id,
                'company_name' => $header['company_name'],
                'company_address' => $header['company_address'] ?? '',
                'company_gstin' => $header['company_gstin'] ?? '',
            ]
        );
    }

    public static function normalizePhone(?string $phone): ?string
    {
        if (! filled($phone)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $phone);
        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 10) {
            return '91'.$digits;
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            return '91'.substr($digits, 1);
        }

        return $digits;
    }

    public static function whatsappUrl(string $phone, string $message): string
    {
        return 'https://wa.me/'.$phone.'?text='.rawurlencode($message);
    }

    public static function mailtoUrl(string $email, string $subject, string $body): string
    {
        return 'mailto:'.$email.'?subject='.rawurlencode($subject).'&body='.rawurlencode($body);
    }

    protected static function buildMessage(Purchase $purchase, array $header): string
    {
        $lines = [
            'Hello '.$purchase->supplier->name.',',
            '',
            'Please find our '.($purchase->isQuotation() ? 'Quotation' : 'Purchase Order').' details below:',
            '',
            ($purchase->isQuotation() ? 'Quotation No: ' : 'PO No: ').$purchase->reference_no,
            'Date: '.($purchase->ordered_at?->format('d M Y') ?? '—'),
            'Plant: '.$purchase->plant->name,
        ];

        if ($purchase->supplier_invoice_no) {
            $lines[] = 'Quotation no.: '.$purchase->supplier_invoice_no;
            if ($purchase->supplier_invoice_date) {
                $lines[] = 'Quotation date: '.$purchase->supplier_invoice_date->format('d M Y');
            }
        }

        $lines[] = '';
        $lines[] = 'Items:';

        foreach ($purchase->items as $index => $item) {
            $lines[] = ($index + 1).'. '.$item->product->name
                .' | Qty: '.number_format((float) $item->quantity, 2)
                .' | Rate: '.number_format((float) $item->unit_price, 2)
                .' | Total: '.number_format((float) $item->line_total, 2);
        }

        $lines[] = '';
        $lines[] = 'Taxable: Rs.'.number_format((float) $purchase->subtotal, 2);
        $lines[] = 'CGST: Rs.'.number_format((float) $purchase->total_cgst, 2);
        $lines[] = 'SGST: Rs.'.number_format((float) $purchase->total_sgst, 2);
        $lines[] = 'IGST: Rs.'.number_format((float) $purchase->total_igst, 2);
        $lines[] = 'Grand Total: Rs.'.number_format((float) $purchase->grand_total, 2);
        $lines[] = '';
        $lines[] = 'Regards,';
        $lines[] = $header['company_name'];

        if (filled($header['company_gstin'] ?? null)) {
            $lines[] = 'GSTIN: '.$header['company_gstin'];
        }

        return implode("\n", $lines);
    }

    protected static function buildWhatsAppMessage(Purchase $purchase, array $header): string
    {
        $lines = [
            'Hello '.$purchase->supplier->name.',',
            '',
            'Please find our Purchase Order '.$purchase->reference_no.'.',
            'Date: '.($purchase->ordered_at?->format('d M Y') ?? '—'),
            'Plant: '.$purchase->plant->name,
            'Grand Total: Rs.'.number_format((float) $purchase->grand_total, 2),
            '',
            'The PDF is attached with this message.',
            '',
            'Regards,',
            $header['company_name'],
        ];

        return implode("\n", $lines);
    }
}
