@extends('layouts.site')
@section('title', 'Edit Product Request')
@section('content')
<x-admin-page-header title="Edit Product Request" subtitle="Request #{{ $productRequest->id }}">
    <a href="{{ route('site.product-requests.show', $productRequest) }}" class="btn btn-admin-outline btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</x-admin-page-header>

<div class="admin-card">
    <div class="admin-card-body">
        <form method="POST" action="{{ route('site.product-requests.update', $productRequest) }}">
            @csrf
            @method('PUT')
            
            <div class="row g-3">
                <x-admin-form-group label="Remarks" name="remarks" col="col-md-12">
                    <textarea name="remarks" class="form-control @error('remarks') is-invalid @enderror" rows="2">{{ old('remarks', $productRequest->remarks) }}</textarea>
                </x-admin-form-group>
            </div>

            <h2 class="h6 mt-4 mb-3">Items</h2>
            <div class="table-responsive mb-3">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th class="text-end" style="width: 180px;">Quantity</th>
                            <th>Item Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($productRequest->items as $i => $item)
                            <tr>
                                <td>
                                    <span class="fw-semibold text-dark">{{ $item->product->name }}</span>
                                    @if($item->product->sku)
                                        <span class="d-block small text-muted text-uppercase">SKU: {{ $item->product->sku }}</span>
                                    @endif
                                    <input type="hidden" name="items[{{ $i }}][id]" value="{{ $item->id }}">
                                </td>
                                <td class="text-end">
                                    <input type="number" step="0.01" min="0.01" name="items[{{ $i }}][quantity]" 
                                           value="{{ old('items.'.$i.'.quantity', $item->quantity) }}" 
                                           required class="form-control text-end @error('items.'.$i.'.quantity') is-invalid @enderror" style="max-width: 150px; display: inline-block;">
                                </td>
                                <td>
                                    <input type="text" name="items[{{ $i }}][remarks]" 
                                           value="{{ old('items.'.$i.'.remarks', $item->remarks) }}" 
                                           class="form-control @error('items.'.$i.'.remarks') is-invalid @enderror" placeholder="Optional notes for this product">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <button type="submit" class="btn btn-admin-primary">
                <i class="bi bi-check2-circle me-1"></i>Save Changes
            </button>
            <a href="{{ route('site.product-requests.show', $productRequest) }}" class="btn btn-admin-outline">Cancel</a>
        </form>
    </div>
</div>
@endsection
