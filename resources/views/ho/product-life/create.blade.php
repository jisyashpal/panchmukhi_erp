@extends('layouts.ho')
@section('title', 'Add Product Life')
@section('content')
<x-admin-page-header title="Add Product Life" subtitle="Record used product life and usage" />
<form method="POST" action="{{ route('ho.product-life.store') }}" class="admin-card admin-card-body">
    @csrf
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label">Site</label>
            <select name="site_id" class="form-select" required onchange="location.href='?site_id='+this.value">
                @foreach($sites as $s)<option value="{{ $s->id }}" @selected($siteId==$s->id)>{{ $s->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Product</label>
            <select name="product_id" class="form-select" required>@foreach($products as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Machine</label>
            <select name="machine_id" class="form-select"><option value="">—</option>@foreach($machines as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach</select>
        </div>
        <div class="col-md-3"><label class="form-label">Quantity</label><input type="number" step="0.01" name="quantity" class="form-control" required></div>
        <div class="col-md-3"><label class="form-label">Life hours</label><input type="number" step="0.01" name="life_hours" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">Usage count</label><input type="number" name="usage_count" value="1" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">Used at</label><input type="date" name="used_at" value="{{ date('Y-m-d') }}" class="form-control" required></div>
        <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
    </div>
    <button class="btn btn-primary mt-3">Save</button>
</form>
@endsection
