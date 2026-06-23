@extends('layouts.ho')
@section('title', 'Brands')
@section('content')
<x-admin-page-header title="Brands" subtitle="Product brands in the catalog">
    <a href="{{ route('ho.brands.create') }}" class="btn btn-admin-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>Add Brand
    </a>
</x-admin-page-header>

<div class="admin-card admin-table-card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($brands as $b)
                <tr>
                    <td class="fw-semibold">{{ $b->name }}</td>
                    <td class="text-end text-nowrap">
                        <x-admin-action-edit :href="route('ho.brands.edit', $b)" />
                        @if ($b->products_count === 0)
                            <x-admin-action-delete :action="route('ho.brands.destroy', $b)" confirm="Delete this brand?" />
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="2">
                        <div class="admin-empty"><i class="bi bi-tag d-block"></i>No brands found</div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $brands->links() }}</div>
@endsection
