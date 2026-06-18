@extends('layouts.ho')
@section('title', 'Stock In')
@section('content')
<x-admin-page-header title="Stock In" subtitle="Record received items from purchase or central store" />

<form method="POST" action="{{ route('ho.stocks.stock-in.store') }}" enctype="multipart/form-data" class="admin-card admin-card-body">
    @csrf
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label">Site</label>
            <select name="site_id" class="form-select" required>
                @foreach($sites as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Product</label>
            <select name="product_id" class="form-select" required>
                @foreach($products as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Quantity</label>
            <input type="number" step="0.01" name="quantity" class="form-control" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Link to PO (optional)</label>
            <select name="purchase_id" class="form-select">
                <option value="">Manual / central store</option>
                @foreach($purchases as $po)
                <option value="{{ $po->id }}">{{ $po->reference_no }} — {{ $po->site->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Photo confirmation</label>
            <select name="photo_confirmed" class="form-select" required>
                <option value="1">Yes — photo verified</option>
                <option value="0">No — no photo</option>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Photo upload</label>
            <input type="file" name="photo" accept="image/*" class="form-control">
        </div>
        <div class="col-md-6">
            <label class="form-label">Reason</label>
            <input type="text" name="reason" class="form-control">
        </div>
        <div class="col-12">
            <label class="form-label">Notes</label>
            <textarea name="notes" class="form-control" rows="2"></textarea>
        </div>
    </div>
    <button class="btn btn-primary mt-3">Record Stock In</button>
</form>
@endsection
