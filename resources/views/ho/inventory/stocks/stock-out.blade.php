@extends('layouts.ho')
@section('title', 'Stock Out')
@section('content')
<x-admin-page-header title="Stock Out" subtitle="Record site consumption — machine use or other purpose">
    <a href="{{ route('ho.stocks.index') }}" class="btn btn-admin-outline btn-sm">Back</a>
</x-admin-page-header>

<form method="POST" action="{{ route('ho.stocks.stock-out.store') }}" enctype="multipart/form-data">
    @csrf
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="admin-card mb-4">
                <div class="admin-card-header"><h2 class="h6 mb-0">Stock details</h2></div>
                <div class="admin-card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="site_id">Site / Plant</label>
                            <select name="site_id" id="site_id" class="form-select form-select-sm" required onchange="location.href='?site_id='+this.value">
                                @foreach($sites as $s)
                                <option value="{{ $s->id }}" @selected($siteId==$s->id)>{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="product_id">Product</label>
                            <select name="product_id" id="product_id" class="form-select form-select-sm" required>
                                @foreach($products as $p)
                                <option value="{{ $p->id }}" @selected(old('product_id')==$p->id)>{{ $p->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="quantity">Quantity</label>
                            <input type="number" step="0.01" min="0.01" name="quantity" id="quantity" value="{{ old('quantity') }}" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label d-block">Used for</label>
                            <div class="d-flex flex-wrap gap-3 pt-1">
                                <div class="form-check">
                                    <input type="radio" name="usage_type" value="machine" id="usage_machine" class="form-check-input usage-type-input" @checked(old('usage_type', 'machine') === 'machine')>
                                    <label class="form-check-label" for="usage_machine">Machine</label>
                                </div>
                                <div class="form-check">
                                    <input type="radio" name="usage_type" value="other" id="usage_other" class="form-check-input usage-type-input" @checked(old('usage_type') === 'other')>
                                    <label class="form-check-label" for="usage_other">Other / site use</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6" id="machine-field">
                            <label class="form-label" for="machine_id">Machine <span class="text-muted fw-normal">(optional)</span></label>
                            <select name="machine_id" id="machine_id" class="form-select form-select-sm">
                                <option value="">— Not linked to a machine —</option>
                                @foreach($machines as $m)
                                <option value="{{ $m->id }}" @selected(old('machine_id')==$m->id)>{{ $m->name }} ({{ $m->code }})</option>
                                @endforeach
                            </select>
                            @if($machines->isEmpty() && $siteId)
                                <div class="form-text">No machines for this site. Add them in Admin → Master → Machines.</div>
                            @endif
                        </div>
                        <div class="col-md-6 d-none" id="purpose-field">
                            <label class="form-label" for="reason">Purpose / location <span class="text-danger">*</span></label>
                            <input type="text" name="reason" id="reason" value="{{ old('reason') }}" class="form-control form-control-sm @error('reason') is-invalid @enderror" placeholder="e.g. Maintenance dept, site office, wastage">
                            @error('reason')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6" id="reason-optional-field">
                            <label class="form-label" for="reason_optional">Notes / reason</label>
                            <input type="text" name="reason" id="reason_optional" value="{{ old('reason') }}" class="form-control form-control-sm" placeholder="Optional">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="admin-card mb-4">
                <div class="admin-card-header"><h2 class="h6 mb-0">Confirmation</h2></div>
                <div class="admin-card-body">
                    <div class="mb-3">
                        <label class="form-label small" for="photo_confirmed">Photo confirmation</label>
                        <select name="photo_confirmed" id="photo_confirmed" class="form-select form-select-sm" required>
                            <option value="1" @selected(old('photo_confirmed', '1') == '1')>Yes — photo verified</option>
                            <option value="0" @selected(old('photo_confirmed') === '0')>No</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small" for="photo">Photo upload</label>
                        <input type="file" name="photo" id="photo" accept="image/*" class="form-control form-control-sm">
                    </div>
                    <div>
                        <label class="form-label small" for="notes">Notes</label>
                        <textarea name="notes" id="notes" class="form-control form-control-sm" rows="2" placeholder="Optional">{{ old('notes') }}</textarea>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-warning w-100">
                <i class="bi bi-box-arrow-up me-1"></i>Record stock out
            </button>
        </div>
    </div>
</form>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const machineField = document.getElementById('machine-field');
    const purposeField = document.getElementById('purpose-field');
    const reasonOptionalField = document.getElementById('reason-optional-field');
    const reasonRequired = document.getElementById('reason');
    const reasonOptional = document.getElementById('reason_optional');
    const machineSelect = document.getElementById('machine_id');

    function syncUsageFields() {
        const isMachine = document.getElementById('usage_machine').checked;
        machineField.classList.toggle('d-none', !isMachine);
        purposeField.classList.toggle('d-none', isMachine);
        reasonOptionalField.classList.toggle('d-none', !isMachine);

        if (isMachine) {
            reasonRequired.removeAttribute('name');
            reasonRequired.removeAttribute('required');
            reasonOptional.setAttribute('name', 'reason');
            machineSelect.disabled = false;
        } else {
            reasonOptional.removeAttribute('name');
            reasonRequired.setAttribute('name', 'reason');
            reasonRequired.setAttribute('required', 'required');
            machineSelect.disabled = true;
            machineSelect.value = '';
        }
    }

    document.querySelectorAll('.usage-type-input').forEach(function(el) {
        el.addEventListener('change', syncUsageFields);
    });
    syncUsageFields();
});
</script>
@endpush
@endsection
