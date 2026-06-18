@extends('layouts.ho')
@section('title', 'Multiple Usage Report')
@section('content')
<x-admin-page-header title="Multiple Usage Highlights"><form method="GET"><select name="site_id" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">All sites</option>@foreach($sites as $s)<option value="{{ $s->id }}" @selected($siteId==$s->id)>{{ $s->name }}</option>@endforeach</select></form></x-admin-page-header>
<div class="admin-card admin-table-card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Product</th><th class="text-end">Use count</th><th class="text-end">Total qty</th></tr></thead><tbody>@foreach($rows as $r)<tr><td>{{ $r->product?->name }}</td><td class="text-end text-warning fw-semibold">{{ $r->use_count }}</td><td class="text-end">{{ $r->total_qty }}</td></tr>@endforeach</tbody></table></div></div>
@endsection
