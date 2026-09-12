<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:w="urn:schemas-microsoft-com:office:word"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta charset="utf-8">
    <title>Purchase Order {{ $purchase->reference_no }}</title>
    <!--[if gte mso 9]>
    <xml>
        <w:WordDocument>
            <w:View>Print</w:View>
            <w:Zoom>100</w:Zoom>
        </w:WordDocument>
    </xml>
    <![endif]-->
    <style>
        body { font-family: Arial, sans-serif; font-size: 11pt; color: #1a1a1a; }
        h1 { font-size: 16pt; margin-bottom: 4pt; }
        h2 { font-size: 12pt; margin: 14pt 0 6pt; text-transform: uppercase; }
        table { border-collapse: collapse; width: 100%; margin-top: 8pt; }
        th, td { border: 1px solid #888; padding: 6pt 8pt; vertical-align: top; }
        th { background: #e8e8e8; text-align: left; font-size: 9pt; }
        .meta td { border: none; padding: 2pt 0; }
        .num { text-align: right; }
        .muted { color: #555; font-size: 10pt; }
        .terms { white-space: pre-wrap; font-size: 10pt; }
    </style>
</head>
<body>
    <h1>{{ $header['name'] }}</h1>
    @if($header['address'])
        <div class="muted">{!! nl2br(e($header['address'])) !!}</div>
    @endif
    @if($header['gstin'])
        <div class="muted"><strong>GSTIN:</strong> {{ $header['gstin'] }}</div>
    @endif

    <h2>Purchase Order</h2>
    <table class="meta">
        <tr><td><strong>PO No:</strong> {{ $purchase->reference_no }}</td></tr>
        <tr><td><strong>Date:</strong> {{ $purchase->ordered_at?->format('d-m-Y') ?? now()->format('d-m-Y') }}</td></tr>
        <tr><td><strong>Supplier:</strong> {{ $purchase->supplier->name }}</td></tr>
        <tr><td><strong>Plant:</strong> {{ $purchase->plant->name }}</td></tr>
        <tr><td><strong>Tax Type:</strong> {{ $purchase->is_interstate ? 'Inter-state (IGST)' : 'Intra-state (CGST + SGST)' }}</td></tr>
        @if($purchase->supplier_invoice_no)
            <tr><td><strong>Quotation No.:</strong> {{ $purchase->supplier_invoice_no }}</td></tr>
            <tr><td><strong>Quotation Date:</strong> {{ $purchase->supplier_invoice_date?->format('d-m-Y') ?? '—' }}</td></tr>
        @endif
    </table>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Description</th>
                <th class="num">Qty</th>
                <th>Unit</th>
                <th class="num">Rate</th>
                <th class="num">GST%</th>
                <th class="num">Taxable</th>
                <th class="num">CGST</th>
                <th class="num">SGST</th>
                <th class="num">IGST</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($purchase->items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>
                        <strong>{{ $item->product?->name ?? '—' }}</strong>
                        @if($item->product?->sku)<div class="muted">SKU: {{ $item->product->sku }}</div>@endif
                        @if($item->product?->size)<div class="muted">Size/Part: {{ $item->product->size }}</div>@endif
                    </td>
                    <td class="num">{{ number_format((float) $item->quantity, 2) }}</td>
                    <td>{{ $item->product?->unit?->name ?? '—' }}</td>
                    <td class="num">{{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="num">{{ number_format((float) $item->gst_rate, 2) }}</td>
                    <td class="num">{{ number_format((float) $item->taxable_amount, 2) }}</td>
                    <td class="num">{{ number_format((float) $item->cgst_amount, 2) }}</td>
                    <td class="num">{{ number_format((float) $item->sgst_amount, 2) }}</td>
                    <td class="num">{{ number_format((float) $item->igst_amount, 2) }}</td>
                    <td class="num"><strong>{{ number_format((float) $item->line_total, 2) }}</strong></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table style="width: 45%; margin-left: auto;">
        <tr><td>Taxable Amount</td><td class="num">{{ number_format((float) $purchase->subtotal, 2) }}</td></tr>
        <tr><td>CGST</td><td class="num">{{ number_format((float) $purchase->total_cgst, 2) }}</td></tr>
        <tr><td>SGST</td><td class="num">{{ number_format((float) $purchase->total_sgst, 2) }}</td></tr>
        <tr><td>IGST</td><td class="num">{{ number_format((float) $purchase->total_igst, 2) }}</td></tr>
        <tr><td>Total GST</td><td class="num">{{ number_format((float) $purchase->total_gst, 2) }}</td></tr>
        <tr><td><strong>Grand Total (INR)</strong></td><td class="num"><strong>{{ number_format((float) $purchase->grand_total, 2) }}</strong></td></tr>
    </table>

    @if($purchase->notes)
        <h2>Notes / Remarks</h2>
        <div>{{ $purchase->notes }}</div>
    @endif

    @if(!empty($header['terms']))
        <h2>Terms and Conditions</h2>
        <div class="terms">{{ $header['terms'] }}</div>
    @endif
</body>
</html>
