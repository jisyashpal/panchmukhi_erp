@extends($portalLayout ?? 'layouts.ho')
@section('title', 'Purchases')
@section('content')
<x-admin-page-header title="Purchases" subtitle="Track purchase orders — status, supplier and amounts">
    <a href="{{ route('ho.purchases.bulk-upload') }}" class="btn btn-admin-outline btn-sm">
        <i class="bi bi-file-earmark-arrow-up me-1"></i>Bulk Upload
    </a>
</x-admin-page-header>
<div class="admin-filters mb-3">
    <form method="GET" class="row g-2 align-items-end">
        @if(isset($financialYear))
            <div class="col-6 col-md-2">
                <label class="form-label small">Financial Year</label>
                <x-financial-year-select compact :selected="request('fy', $financialYear->label)" />
            </div>
        @endif
        <div class="col-12 col-md-3">
            <label class="form-label small">Search PO / Supplier</label>
            <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm"
                placeholder="Reference no. or supplier...">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small">Plant</label>
            <select name="plant_id" class="form-select form-select-sm">
                <option value="">All plants</option>
                @foreach($plants as $s)
                    <option value="{{ $s->id }}" @selected(request('plant_id') == $s->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">All statuses</option>
                @foreach(\App\Enums\PurchaseStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small">From date</label>
            <input type="date" name="date_from" value="{{ request('date_from', $financialYear->dateFrom ?? '') }}" class="form-control form-control-sm">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small">To date</label>
            <input type="date" name="date_to" value="{{ request('date_to', $financialYear->dateTo ?? '') }}" class="form-control form-control-sm">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-admin-primary btn-sm"><i class="bi bi-search me-1"></i>Search</button>
            @if(request()->anyFilled(['search', 'plant_id', 'status', 'fy', 'date_from', 'date_to', 'purchase_request_id']))
                <a href="{{ route('ho.purchases.index') }}" class="btn btn-admin-outline btn-sm">Clear</a>
            @endif
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
                    <th>Plant</th>
                    <th>Supplier</th>
                    <th class="text-end">Grand total</th>
                    <th>GST</th>
                    <th>Status</th>
                    <th>Ordered</th>
                    <th>HO Comment</th>
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
                    <td>{{ $p->plant->name }}</td>
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
                    <td class="small" style="max-width: 180px;">
                        @if($p->latestHoComment)
                            <span class="text-muted d-block text-truncate" title="{{ $p->latestHoComment->body }}">
                                {{ Str::limit($p->latestHoComment->body, 50) }}
                            </span>
                            <span class="text-muted" style="font-size: .7rem;">{{ $p->latestHoComment->user?->name }} · {{ $p->latestHoComment->created_at->format('d M') }}</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-end text-nowrap">
                        <button type="button"
                            class="btn btn-sm btn-outline-secondary me-1 btn-ho-comment"
                            data-comment-url="{{ route('ho.purchases.comments.store', $p) }}"
                            data-reference="{{ $p->reference_no }}">
                            <i class="bi bi-chat-left-text me-1"></i>Comment
                        </button>
                        @if(auth()->user()?->isSuperAdmin())
                            <a href="{{ route('ho.purchases.edit', $p) }}" class="btn btn-sm btn-outline-primary me-1">
                                <i class="bi bi-pencil me-1"></i>Edit
                            </a>
                        @endif
                        <a href="{{ route('ho.purchases.show', $p) }}" class="btn btn-sm btn-admin-outline">View</a>
                    </td>
                </tr>
                @empty
                @include('partials.table-empty', ['colspan' => 10, 'icon' => 'bi-receipt', 'message' => 'No purchases found for the selected filters.'])
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $purchases->links() }}</div>

<div class="modal fade" id="hoPurchaseCommentModal" tabindex="-1" aria-labelledby="hoPurchaseCommentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" id="ho_purchase_comment_form">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="hoPurchaseCommentModalLabel">HO comment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-2">Add internal comment for <strong id="ho_comment_reference">—</strong></p>
                    <label class="form-label small mb-1" for="ho_comment_body_modal">Comment</label>
                    <textarea name="body" id="ho_comment_body_modal" rows="4" class="form-control form-control-sm"
                        placeholder="Write HO note for this PO..." maxlength="2000" required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-admin-outline btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-admin-primary btn-sm">
                        <i class="bi bi-send me-1"></i>Save comment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const modalEl = document.getElementById('hoPurchaseCommentModal');
    const form = document.getElementById('ho_purchase_comment_form');
    if (!modalEl || !form) return;

    const modal = new bootstrap.Modal(modalEl);

    document.querySelectorAll('.btn-ho-comment').forEach(function (btn) {
        btn.addEventListener('click', function () {
            form.action = btn.dataset.commentUrl || '';
            document.getElementById('ho_comment_reference').textContent = btn.dataset.reference || '—';
            document.getElementById('ho_comment_body_modal').value = '';
            modal.show();
        });
    });
})();
</script>
@endpush
@endsection
