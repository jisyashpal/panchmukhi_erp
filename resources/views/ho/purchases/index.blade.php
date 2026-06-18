@extends('layouts.ho')
@section('title', 'Purchases')
@section('content')
<x-admin-page-header title="Purchases" subtitle="Purchase orders with supplier, price and GST">
    <a href="{{ route('ho.purchases.create') }}" class="btn btn-admin-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>New Purchase
    </a>
</x-admin-page-header>

@if($readyForPo > 0)
<div class="admin-alert admin-alert-warning mb-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
    <span>
        <i class="bi bi-exclamation-triangle-fill me-1"></i>
        <strong>{{ $readyForPo }}</strong> purchase request(s) without a purchase order yet.
    </span>
    <a href="{{ route('ho.purchases.create') }}" class="btn btn-sm btn-warning">Create PO</a>
</div>
@endif

<div class="admin-filters mb-0">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-6 col-md-3">
            <label class="form-label small">Site</label>
            <select name="site_id" class="form-select form-select-sm">
                <option value="">All sites</option>
                @foreach($sites as $s)
                    <option value="{{ $s->id }}" @selected(request('site_id') == $s->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">All statuses</option>
                <option value="ordered" @selected(request('status') === 'ordered')>Ordered</option>
                <option value="delivered" @selected(request('status') === 'delivered')>Delivered</option>
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-admin-primary btn-sm"><i class="bi bi-funnel me-1"></i>Filter</button>
        </div>
    </form>
</div>

<div class="admin-card admin-table-card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>PO Ref</th>
                    <th>PR #</th>
                    <th>Site</th>
                    <th>Supplier</th>
                    <th class="text-end">Grand total</th>
                    <th>GST</th>
                    <th>Status</th>
                    <th>Ordered</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($purchases as $p)
                <tr>
                    <td><code class="small">{{ $p->reference_no }}</code></td>
                    <td>
                        @if($p->purchase_request_id)
                            <span class="small text-muted">#{{ $p->purchase_request_id }}</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>{{ $p->site->name }}</td>
                    <td>
                        <span class="d-block">{{ $p->supplier->name }}</span>
                        @if($p->supplier->gstin)
                            <small class="text-muted">{{ $p->supplier->gstin }}</small>
                        @endif
                    </td>
                    <td class="text-end fw-semibold">₹{{ number_format($p->grand_total, 2) }}</td>
                    <td class="small">
                        @if($p->is_interstate)
                            <span class="badge bg-info-subtle text-info">IGST ₹{{ number_format($p->total_igst, 2) }}</span>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary">CGST+SGST</span>
                        @endif
                    </td>
                    <td><x-status-badge :status="$p->status" /></td>
                    <td class="small text-muted">{{ $p->ordered_at?->format('d M Y') ?? '—' }}</td>
                    <td class="text-end">
                        <a href="{{ route('ho.purchases.show', $p) }}" class="btn btn-sm btn-admin-outline">View</a>
                    </td>
                </tr>
                @empty
                @include('partials.table-empty', ['colspan' => 9, 'icon' => 'bi-receipt', 'message' => 'No purchases yet. Create a purchase order from a request or manually.'])
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $purchases->links() }}</div>
@endsection
