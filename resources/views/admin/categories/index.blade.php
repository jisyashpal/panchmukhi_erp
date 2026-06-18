@extends('layouts.admin')
@section('title', 'Categories')
@section('content')
<x-admin-page-header title="Categories" subtitle="Product categories for catalog organization">
    <a href="{{ route('admin.categories.create') }}" class="btn btn-admin-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>Add Category
    </a>
</x-admin-page-header>

<div class="admin-card admin-table-card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Sub-categories</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($categories as $c)
                <tr>
                    <td class="fw-semibold">{{ $c->name }}</td>
                    <td>{{ $c->sub_categories_count }}</td>
                    <td class="text-end text-nowrap">
                        <x-admin-action-edit :href="route('admin.categories.edit', $c)" />
                        @if ($c->products_count === 0)
                            <x-admin-action-delete :action="route('admin.categories.destroy', $c)" confirm="Delete this category?" />
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="3">
                        <div class="admin-empty"><i class="bi bi-folder d-block"></i>No categories found</div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $categories->links() }}</div>
@endsection
