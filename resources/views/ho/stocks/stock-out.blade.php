@extends('layouts.ho')
@section('title', 'Stock Out')
@section('content')
<x-admin-page-header title="Stock Out" subtitle="Site usage — select machine, photo & reason" />

<form method="POST" action="{{ route('ho.stocks.stock-out.store') }}" enctype="multipart/form-data" class="admin-card admin-card-body">
    @csrf
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label">Site</label>
            <select name="site_id" id="site_id" class="form-select" required onchange="location.href='?site_id='+this.value">
                @foreach($sites as $s)
                <option value="{{ $s->id }}" @selected($siteId==$s->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Product</label>
            <select name="product_id" class="form-select" required>
                @foreach($products as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Machine</label>
            <select name="machine_id" class="form-select">
                <option value="">—</option>
                @foreach($machines as $m)<option value="{{ $m->id }}">{{ $m->name }} ({{ $m->code }})</option>@endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Quantity</label>
            <input type="number" step="0.01" name="quantity" class="form-control" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">Photo confirmation</label>
            <select name="photo_confirmed" class="form-select" required>
                <option value="1">Yes</option>
                <option value="0">No</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Photo</label>
            <input type="file" name="photo" accept="image/*" class="form-control">
        </div>
        <div class="col-md-6">
            <label class="form-label">Reason</label>
            <input type="text" name="reason" class="form-control">
        </div>
        <div class="col-md-6">
            <label class="form-label">Notes</label>
            <input type="text" name="notes" class="form-control">
        </div>
    </div>
    <button class="btn btn-warning mt-3">Record Stock Out</button>
</form>
@endsection
