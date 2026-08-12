@extends($portalLayout ?? 'layouts.ho')
@section('title', 'New Product Request')
@section('content')
<x-admin-page-header title="New Product Request" subtitle="Create product request for a plant">
    <a href="{{ route('ho.product-requests.index') }}" class="btn btn-admin-outline btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
</x-admin-page-header>

<div class="admin-card">
    <div class="admin-card-body">
        <form method="POST" action="{{ route('ho.product-requests.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Plant</label>
                    <select name="plant_id" required class="form-select">
                        <option value="">Select plant</option>
                        @foreach($plants as $s)
                            <option value="{{ $s->id }}" @selected(old('plant_id') == $s->id)>{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <!-- spacing or other fields if any -->
                </div>
                <x-admin-form-group label="Remarks" name="remarks" col="col-md-12">
                    <textarea name="remarks" class="form-control" rows="2">{{ old('remarks') }}</textarea>
                </x-admin-form-group>
            </div>

            @include('partials.request-item-lines', ['products' => $products])

            <button type="submit" class="btn btn-admin-primary mt-3">Submit Request</button>
        </form>
    </div>
</div>
@endsection
