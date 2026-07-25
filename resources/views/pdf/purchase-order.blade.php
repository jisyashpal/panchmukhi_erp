<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Purchase Order {{ $purchase->reference_no }}</title>
    <style>
        @page {
            margin: 0;
        }

        html, body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, sans-serif;
            font-size: 9pt;
            color: #1a1a1a;
            line-height: 1.4;
        }

        .page-margin {
            padding: 28pt 32pt 28pt 32pt;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        .section-gap {
            height: 14pt;
            line-height: 14pt;
            font-size: 1pt;
        }

        .border-bottom {
            border-bottom: 1px solid #333;
        }

        .company-name {
            font-size: 15pt;
            font-weight: bold;
            margin-bottom: 4pt;
        }

        .small {
            font-size: 8pt;
            color: #444;
        }

        .doc-title {
            font-size: 13pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 6pt;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .box td {
            border: 1px solid #999;
            padding: 8pt 10pt;
            vertical-align: top;
            font-size: 8.5pt;
        }

        .box-label {
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #666;
            margin-bottom: 4pt;
        }

        .box-name {
            font-size: 10pt;
            font-weight: bold;
            margin-bottom: 3pt;
        }

        .meta td {
            border: 1px solid #bbb;
            padding: 5pt 8pt;
            font-size: 8.5pt;
            vertical-align: middle;
        }

        .meta .lbl {
            width: 18%;
            background: #f5f5f5;
            color: #555;
            font-weight: bold;
        }

        .items th {
            background: #e8e8e8;
            border: 1px solid #888;
            padding: 6pt 4pt;
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
            text-align: left;
        }

        .items th.num,
        .items td.num {
            text-align: right;
        }

        .items th.center,
        .items td.center {
            text-align: center;
        }

        .items td {
            border: 1px solid #bbb;
            padding: 5pt 4pt;
            font-size: 8pt;
            vertical-align: top;
        }

        .items td.desc {
            word-wrap: break-word;
        }

        .product-name {
            font-weight: bold;
        }

        .product-sub {
            font-size: 7pt;
            color: #666;
            margin-top: 2pt;
        }

        .totals td {
            border: 1px solid #bbb;
            padding: 5pt 8pt;
            font-size: 8.5pt;
        }

        .totals td.num {
            text-align: right;
            width: 95pt;
        }

        .totals tr.grand td {
            font-weight: bold;
            font-size: 9.5pt;
            background: #efefef;
        }

        .footer {
            margin-top: 16pt;
            padding-top: 8pt;
            border-top: 1px solid #bbb;
            font-size: 7pt;
            color: #777;
            text-align: center;
        }
    </style>
</head>
<body>
<table width="100%" cellpadding="0" cellspacing="0">
<tr>
<td class="page-margin">

    {{-- Header --}}
    <table>
        <tr>
            <td style="width: 58%; vertical-align: top; padding-bottom: 10pt;" class="border-bottom">
                <div class="company-name">{{ $header['name'] }}</div>
                @if($header['address'])
                    <div class="small">{!! nl2br(e($header['address'])) !!}</div>
                @endif
                @if($header['gstin'])
                    <div class="small" style="margin-top: 4pt;"><strong>GSTIN:</strong> {{ $header['gstin'] }}</div>
                @endif
            </td>
            <td style="width: 42%; vertical-align: top; padding-bottom: 10pt; padding-left: 12pt;" class="border-bottom text-right">
                <div class="doc-title">Purchase Order</div>
                <div class="small"><strong>PO No:</strong> {{ $purchase->reference_no }}</div>
                <div class="small"><strong>Date:</strong> {{ $purchase->ordered_at?->format('d-m-Y') ?? now()->format('d-m-Y') }}</div>
                @if($purchase->purchase_request_id)
                    <div class="small"><strong>PR No:</strong> #{{ $purchase->purchase_request_id }}</div>
                @endif
            </td>
        </tr>
    </table>

    <div class="section-gap">&nbsp;</div>

    {{-- Supplier / Plant --}}
    <table class="box">
        <tr>
            <td style="width: 50%;">
                <div class="box-label">Supplier</div>
                <div class="box-name">{{ $purchase->supplier->name }}</div>
                @if($purchase->supplier->gstin)
                    <div class="small">GSTIN: {{ $purchase->supplier->gstin }}</div>
                @endif
                @if($purchase->supplier->phone)
                    <div class="small">Phone: {{ $purchase->supplier->phone }}</div>
                @endif
                @if($purchase->supplier->address)
                    <div class="small" style="margin-top: 3pt;">{{ $purchase->supplier->address }}</div>
                @endif
            </td>
            <td style="width: 50%;">
                <div class="box-label">Ship To / Plant</div>
                <div class="box-name">{{ $purchase->plant->name }}</div>
                @if($purchase->plant->code)
                    <div class="small">Code: {{ $purchase->plant->code }}</div>
                @endif
                @if($purchase->plant->address)
                    <div class="small" style="margin-top: 3pt;">{{ $purchase->plant->address }}</div>
                @endif
            </td>
        </tr>
    </table>

    <div class="section-gap">&nbsp;</div>

    {{-- Meta --}}
    <table class="meta">
        <tr>
            <td class="lbl">Status</td>
            <td style="width: 32%;">{{ $purchase->status->label() }}</td>
            <td class="lbl">Tax Type</td>
            <td>{{ $purchase->is_interstate ? 'Inter-state (IGST)' : 'Intra-state (CGST + SGST)' }}</td>
        </tr>
        @if($purchase->supplier_invoice_no)
        <tr>
            <td class="lbl">Quotation No.</td>
            <td>{{ $purchase->supplier_invoice_no }}</td>
            <td class="lbl">Quotation Date</td>
            <td>{{ $purchase->supplier_invoice_date?->format('d-m-Y') ?? '—' }}</td>
        </tr>
        @endif
    </table>

    <div class="section-gap">&nbsp;</div>

    {{-- Line items --}}
    <table class="items" style="table-layout: fixed;">
        <thead>
            <tr>
                <th class="center" style="width: 4%;">#</th>
                <th style="width: 22%;">Description</th>
                <th class="num" style="width: 8%;">Qty</th>
                <th class="center" style="width: 7%;">Unit</th>
                <th class="num" style="width: 9%;">Rate</th>
                <th class="num" style="width: 6%;">GST%</th>
                <th class="num" style="width: 9%;">Taxable</th>
                <th class="num" style="width: 8%;">CGST</th>
                <th class="num" style="width: 8%;">SGST</th>
                <th class="num" style="width: 8%;">IGST</th>
                <th class="num" style="width: 11%;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($purchase->items as $index => $item)
            <tr>
                <td class="center">{{ $index + 1 }}</td>
                <td class="desc">
                    <div class="product-name">{{ $item->product->name }}</div>
                    @if($item->product->sku)
                        <div class="product-sub">SKU: {{ $item->product->sku }}</div>
                    @endif
                    @if($item->product->size)
                        <div class="product-sub">Size/Part: {{ $item->product->size }}</div>
                    @endif
                </td>
                <td class="num">{{ number_format((float) $item->quantity, 2) }}</td>
                <td class="center">{{ $item->product->unit?->name ?? '—' }}</td>
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

    <div class="section-gap">&nbsp;</div>

    {{-- Notes + totals --}}
    <table>
        <tr>
            <td style="width: 58%; vertical-align: top; padding-right: 12pt;">
                @if($purchase->notes)
                    <div class="box-label">Notes / Remarks</div>
                    <div class="small">{{ $purchase->notes }}</div>
                @endif
            </td>
            <td style="width: 42%; vertical-align: top;">
                <table class="totals">
                    <tr>
                        <td>Taxable Amount</td>
                        <td class="num">{{ number_format((float) $purchase->subtotal, 2) }}</td>
                    </tr>
                    <tr>
                        <td>CGST</td>
                        <td class="num">{{ number_format((float) $purchase->total_cgst, 2) }}</td>
                    </tr>
                    <tr>
                        <td>SGST</td>
                        <td class="num">{{ number_format((float) $purchase->total_sgst, 2) }}</td>
                    </tr>
                    <tr>
                        <td>IGST</td>
                        <td class="num">{{ number_format((float) $purchase->total_igst, 2) }}</td>
                    </tr>
                    <tr>
                        <td>Total GST</td>
                        <td class="num">{{ number_format((float) $purchase->total_gst, 2) }}</td>
                    </tr>
                    <tr class="grand">
                        <td>Grand Total (INR)</td>
                        <td class="num">{{ number_format((float) $purchase->grand_total, 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    @if(!empty($header['terms']))
        <div class="section-gap">&nbsp;</div>
        <div class="box-label">Terms and Conditions</div>
        <div class="small">{!! nl2br(e($header['terms'])) !!}</div>
    @endif

    <div class="footer">
        Computer-generated purchase order &mdash; {{ $purchase->reference_no }} &mdash; {{ now()->format('d-m-Y H:i') }}
    </div>

</td>
</tr>
</table>
</body>
</html>
