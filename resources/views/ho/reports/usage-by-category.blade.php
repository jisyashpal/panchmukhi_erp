@extends('layouts.ho')
@section('title', 'Usage by Category')
@section('content')
<x-admin-page-header title="Product Usage by Category"><form method="GET"><select name="site_id" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">All sites</option>@foreach($sites as $s)<option value="{{ $s->id }}" @selected($siteId==$s->id)>{{ $s->name }}</option>@endforeach</select></form></x-admin-page-header>
<div class="admin-card admin-table-card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Category</th><th class="text-end">Qty</th><th class="text-end">Life hours</th></tr></thead><tbody>@foreach($rows as $r)<tr><td>{{ $r->category_name }}</td><td class="text-end">{{ $r->total_qty }}</td><td class="text-end">{{ $r->total_life_hours }}</td></tr>@endforeach</tbody></table></div></div>
@endsection
