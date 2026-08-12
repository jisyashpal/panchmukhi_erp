@extends($portalLayout ?? 'layouts.ho')
@section('title', 'Add Unit')
@section('content')
<x-admin-page-header title="Add Unit" subtitle="Create a measurement unit">
    @include('partials.catalog-create-back', ['fallback' => route('ho.units.index'), 'portal' => 'ho'])
</x-admin-page-header>

<div class="admin-card">
    <div class="admin-card-body">
        <form method="POST" action="{{ route('ho.units.store') }}">
            @csrf
            @include('partials.catalog-form-return')
            <div class="row g-3">
                <x-admin-form-input label="Name" name="name" required />
                <x-admin-form-input label="Abbreviation" name="abbreviation" required />
                <div class="col-12">
                    <button type="submit" class="btn btn-admin-primary">Save Unit</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
