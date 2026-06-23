@extends('layouts.admin')
@section('title', 'Add Permission')
@section('content')
<x-admin-page-header title="Add Permission" subtitle="Create a new permission for role assignment">
    <a href="{{ route('admin.permissions.index') }}" class="btn btn-admin-outline btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
</x-admin-page-header>

<div class="admin-card">
    <div class="admin-card-body">
        <form method="POST" action="{{ route('admin.permissions.store') }}">
            @csrf
            <div class="row g-3">
                @include('admin.master.permissions.partials.form-fields', ['modules' => $modules])
                <div class="col-12">
                    <button type="submit" class="btn btn-admin-primary">Save Permission</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
