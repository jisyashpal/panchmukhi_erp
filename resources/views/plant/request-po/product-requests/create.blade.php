@extends('layouts.plant')
@section('title', 'New Product Request')
@section('content')
    <x-admin-page-header title="New Product Request" subtitle="Submit to Plant In-Charge for approval">
        <a href="{{ route('plant.product-requests.index') }}" class="btn btn-admin-outline btn-sm"><i
                class="bi bi-arrow-left me-1"></i>Back</a>
    </x-admin-page-header>

    <div class="admin-card">
        <div class="admin-card-body">
            <form method="POST" action="{{ route('plant.product-requests.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="row g-3 mb-1">
                    <x-admin-form-group label="Requested By" name="requester_name" col="col-md-6" required>
                        <input type="text" name="requester_name" value="{{ old('requester_name', auth()->user()->name) }}"
                            class="form-control @error('requester_name') is-invalid @enderror"
                            placeholder="Enter requester name" required>
                    </x-admin-form-group>
                    <x-admin-form-group label="Remarks" name="remarks" col="col-md-6">
                        <textarea name="remarks" class="form-control @error('remarks') is-invalid @enderror" rows="1"
                            placeholder="Enter remarks (optional)">{{ old('remarks') }}</textarea>
                    </x-admin-form-group>
                </div>

                @include('plant.request-po.product-requests.partials.item-lines', [
                    'products' => $products,
                    'subCategories' => $subCategories,
                    'categories' => $categories,
                ])

                <div class="d-flex  align-items-center justify-content-between mt-2 pt-2 border-top">
                    <button type="button" class="btn btn-sm btn-success "
                        onclick="addDynamicRow('item-lines', document.getElementById('plant-item-line-template').innerHTML)">
                        <i class="bi bi-plus-lg me-1"></i> Add New Item
                    </button>
                    <button type="submit" class="btn btn-admin-primary">
                        <i class="bi bi-send me-1"></i> Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection