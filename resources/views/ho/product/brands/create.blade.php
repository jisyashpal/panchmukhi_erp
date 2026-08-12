@extends($portalLayout ?? 'layouts.ho')
@section('title', 'Add Brand')
@section('content')
<x-admin-page-header title="Add Brand" subtitle="Create a product brand">
    @include('partials.catalog-create-back', ['fallback' => route('ho.brands.index'), 'portal' => 'ho'])
</x-admin-page-header>

<div class="admin-card">
    <div class="admin-card-body">
        <form method="POST" action="{{ route('ho.brands.store') }}">
            @csrf
            @include('partials.catalog-form-return')
            <div class="row g-3">
                <x-admin-form-input label="Name" name="name" required />
                <div class="col-12">
                    <button type="submit" class="btn btn-admin-primary">Save Brand</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
