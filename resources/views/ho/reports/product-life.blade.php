@extends('layouts.ho')
@section('title', 'Product Life Report')
@section('content')
<x-admin-page-header title="Product Life Usage">
    <form method="GET"><select name="site_id" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">All sites</option>@foreach($sites as $s)<option value="{{ $s->id }}" @selected($siteId==$s->id)>{{ $s->name }}</option>@endforeach</select></form>
</x-admin-page-header>
<div class="admin-card admin-table-card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Date</th><th>Site</th><th>Product</th><th>Machine</th><th class="text-end">Qty</th><th class="text-end">Life hrs</th></tr></thead><tbody>@foreach($entries as $e)<tr><td>{{ $e->used_at->format('d M Y') }}</td><td>{{ $e->site->name }}</td><td>{{ $e->product->name }}</td><td>{{ $e->machine?->name ?? '—' }}</td><td class="text-end">{{ $e->quantity }}</td><td class="text-end">{{ $e->life_hours }}</td></tr>@endforeach</tbody></table></div></div>
@endsection
