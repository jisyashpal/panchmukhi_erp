Hello {{ $purchase->supplier->name }},

Please find attached our Purchase Order {{ $purchase->reference_no }} dated {{ $purchase->ordered_at?->format('d M Y') ?? now()->format('d M Y') }}.

Site: {{ $purchase->site->name }}
Grand Total: Rs.{{ number_format((float) $purchase->grand_total, 2) }}

Regards,
{{ $companyName }}
