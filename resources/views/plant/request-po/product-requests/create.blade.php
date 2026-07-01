@extends('layouts.plant')
@section('title', 'New Product Request')
@section('content')
<x-admin-page-header title="New Product Request" subtitle="Submit to Plant In-Charge for approval">
    <a href="{{ route('plant.product-requests.index') }}" class="btn btn-admin-outline btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
</x-admin-page-header>

<div class="admin-card">
    <div class="admin-card-body">
        <form method="POST" action="{{ route('plant.product-requests.store') }}">
            @csrf
            <div class="row g-3">
                <x-admin-form-group label="Remarks" name="remarks" col="col-md-12">
                    <textarea name="remarks" class="form-control" rows="2">{{ old('remarks') }}</textarea>
                </x-admin-form-group>
            </div>

            @include('partials.request-item-lines', ['products' => $products])

            <button type="submit" class="btn btn-admin-primary">Submit Request</button>
        </form>
    </div>
</div>
@endsection
