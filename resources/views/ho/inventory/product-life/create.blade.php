@extends('layouts.ho')
@section('title', 'Add Product Life')
@section('content')
<x-admin-page-header title="Add Product Life Entry" subtitle="Record product usage, quantity consumed, and life hours" />

<div class="row g-4">
    <div class="col-lg-8">
        <form method="POST" action="{{ route('ho.product-life.store') }}" class="admin-card admin-card-body">
            @csrf
            <div class="row g-3">
                {{-- Plant --}}
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Plant <span class="text-danger">*</span></label>
                    <select name="plant_id" class="form-select" required onchange="location.href='?plant_id='+this.value">
                        @foreach($plants as $s)
                            <option value="{{ $s->id }}" @selected($plantId == $s->id)>{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Product --}}
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Product <span class="text-danger">*</span></label>
                    <select name="product_id" id="product_id" class="form-select" required>
                        <option value="">— Select Product —</option>
                        @foreach($products as $p)
                            <option value="{{ $p->id }}" data-category="{{ $p->category?->name }}">
                                {{ $p->name }}@if($p->category) ({{ $p->category->name }})@endif
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Sub Category --}}
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Sub Category</label>
                    <select name="sub_category_id" id="sub_category_id" class="form-select">
                        <option value="">—</option>
                        @foreach($subCategories as $sc)
                            <option value="{{ $sc->id }}">{{ $sc->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Used At --}}
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Used At <span class="text-danger">*</span></label>
                    <input type="date" name="used_at" value="{{ date('Y-m-d') }}" class="form-control" required>
                </div>

                {{-- Quantity --}}
                <div class="col-md-4">
                    <label class="form-label fw-semibold d-flex align-items-center gap-1">
                        Quantity Used
                        <span class="text-danger">*</span>
                        <span class="ms-1 text-muted" style="font-size:.78rem;" title="How many units of this product were consumed in this usage event">
                            <i class="bi bi-info-circle" data-bs-toggle="tooltip" title="Units of the product consumed in this usage event (e.g. bags of cement used)"></i>
                        </span>
                    </label>
                    <input type="number" step="0.01" min="0.01" name="quantity" id="quantity" class="form-control" required placeholder="e.g. 5">
                </div>

                {{-- Life Hours --}}
                <div class="col-md-4">
                    <label class="form-label fw-semibold d-flex align-items-center gap-1">
                        Life Hours
                        <span class="ms-1 text-muted" style="font-size:.78rem;">
                            <i class="bi bi-info-circle" data-bs-toggle="tooltip" title="Total hours this product was in active use (e.g. machine running hours)"></i>
                        </span>
                    </label>
                    <input type="number" step="0.01" min="0" name="life_hours" id="life_hours" class="form-control" placeholder="e.g. 48">
                    <div id="life_hours_hint" class="form-text text-muted d-none">
                        <i class="bi bi-clock-history me-1"></i>Last entry: <strong id="last_life_hours">—</strong> hrs
                    </div>
                </div>

                {{-- Usage Count --}}
                <div class="col-md-4">
                    <label class="form-label fw-semibold d-flex align-items-center gap-1">
                        Usage Count
                        <span class="ms-1 text-muted" style="font-size:.78rem;">
                            <i class="bi bi-info-circle" data-bs-toggle="tooltip" title="Number of times used within this entry (e.g. 3 cycles)"></i>
                        </span>
                    </label>
                    <input type="number" min="1" name="usage_count" id="usage_count" value="1" class="form-control">
                </div>

                {{-- Notes --}}
                <div class="col-12">
                    <label class="form-label fw-semibold">Notes</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Optional remarks..."></textarea>
                </div>
            </div>
            <div class="d-flex gap-2 mt-4">
                <button class="btn btn-primary">
                    <i class="bi bi-check2 me-1"></i>Save Entry
                </button>
                <a href="{{ route('ho.product-life.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>

    {{-- Last Entry Reference Card --}}
    <div class="col-lg-4">
        <div id="last_entry_card" class="admin-card admin-card-body d-none">
            <div class="d-flex align-items-center gap-2 mb-3">
                <i class="bi bi-clock-history text-primary fs-5"></i>
                <h6 class="mb-0 fw-semibold">Previous Entry (Reference)</h6>
            </div>
            <table class="table table-sm mb-0">
                <tbody>
                    <tr><td class="text-muted">Date</td><td id="ref_used_at" class="fw-semibold">—</td></tr>
                    <tr><td class="text-muted">Quantity</td><td id="ref_quantity">—</td></tr>
                    <tr><td class="text-muted">Life Hours</td><td id="ref_life_hours">—</td></tr>
                    <tr><td class="text-muted">Usage Count</td><td id="ref_usage_count">—</td></tr>
                    <tr><td class="text-muted">Sub Category</td><td id="ref_sub_category">—</td></tr>
                    <tr id="ref_notes_row" class="d-none"><td class="text-muted">Notes</td><td id="ref_notes" class="text-muted small">—</td></tr>
                </tbody>
            </table>
            <div class="mt-3">
                <button type="button" id="btn_prefill" class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-arrow-down-circle me-1"></i>Use Previous Life Hours
                </button>
            </div>
        </div>
        <div id="last_entry_empty" class="admin-card admin-card-body text-muted text-center d-none">
            <i class="bi bi-journal-x fs-3 d-block mb-2 opacity-50"></i>
            No previous entry found for this product.
        </div>
        <div id="last_entry_loading" class="admin-card admin-card-body text-center text-muted d-none">
            <div class="spinner-border spinner-border-sm me-2"></div>Loading previous entry...
        </div>
        <div class="admin-card admin-card-body text-muted small" id="last_entry_placeholder">
            <i class="bi bi-info-circle me-1"></i>Select a product to see its last usage entry as a reference.
            <hr class="my-2">
            <ul class="ps-3 mb-0" style="line-height:1.8;">
                <li><strong>Quantity</strong> — units consumed (e.g. bags, kg, pcs)</li>
                <li><strong>Life Hours</strong> — machine/equipment running hours during this usage</li>
                <li><strong>Usage Count</strong> — how many times used in this event</li>
            </ul>
        </div>
    </div>
</div>

<script>
const lastEntryUrl = "{{ route('ho.product-life.last-entry') }}";
let lastEntry = null;

document.getElementById('product_id').addEventListener('change', function () {
    const productId = this.value;
    if (! productId) { showPlaceholder(); return; }

    showLoading();

    fetch(`${lastEntryUrl}?product_id=${productId}`)
        .then(r => r.json())
        .then(data => {
            lastEntry = data;
            if (data) {
                showCard(data);
            } else {
                showEmpty();
            }
        })
        .catch(() => showPlaceholder());
});

document.getElementById('btn_prefill').addEventListener('click', function () {
    if (lastEntry) {
        document.getElementById('life_hours').value = lastEntry.life_hours ?? '';
        document.getElementById('last_life_hours').textContent = lastEntry.life_hours ?? '—';
        document.getElementById('life_hours_hint').classList.remove('d-none');

        // Pre-select sub_category if it matches
        const subSel = document.getElementById('sub_category_id');
        if (lastEntry.sub_category_id) {
            for (let opt of subSel.options) {
                if (opt.value == lastEntry.sub_category_id) { opt.selected = true; break; }
            }
        }
    }
});

function showCard(data) {
    document.getElementById('last_entry_card').classList.remove('d-none');
    document.getElementById('last_entry_empty').classList.add('d-none');
    document.getElementById('last_entry_loading').classList.add('d-none');
    document.getElementById('last_entry_placeholder').classList.add('d-none');

    document.getElementById('ref_used_at').textContent      = data.used_at ?? '—';
    document.getElementById('ref_quantity').textContent     = data.quantity ?? '—';
    document.getElementById('ref_life_hours').textContent   = (data.life_hours ?? '—') + (data.life_hours ? ' hrs' : '');
    document.getElementById('ref_usage_count').textContent  = data.usage_count ?? '—';
    document.getElementById('ref_sub_category').textContent = data.sub_category ?? '—';

    const notesRow = document.getElementById('ref_notes_row');
    if (data.notes) {
        document.getElementById('ref_notes').textContent = data.notes;
        notesRow.classList.remove('d-none');
    } else {
        notesRow.classList.add('d-none');
    }

    // Show life hours hint
    document.getElementById('last_life_hours').textContent = data.life_hours ?? '—';
    document.getElementById('life_hours_hint').classList.remove('d-none');
}

function showEmpty() {
    document.getElementById('last_entry_card').classList.add('d-none');
    document.getElementById('last_entry_empty').classList.remove('d-none');
    document.getElementById('last_entry_loading').classList.add('d-none');
    document.getElementById('last_entry_placeholder').classList.add('d-none');
    document.getElementById('life_hours_hint').classList.add('d-none');
}

function showLoading() {
    document.getElementById('last_entry_card').classList.add('d-none');
    document.getElementById('last_entry_empty').classList.add('d-none');
    document.getElementById('last_entry_loading').classList.remove('d-none');
    document.getElementById('last_entry_placeholder').classList.add('d-none');
}

function showPlaceholder() {
    document.getElementById('last_entry_card').classList.add('d-none');
    document.getElementById('last_entry_empty').classList.add('d-none');
    document.getElementById('last_entry_loading').classList.add('d-none');
    document.getElementById('last_entry_placeholder').classList.remove('d-none');
    document.getElementById('life_hours_hint').classList.add('d-none');
}
</script>
@endsection
