@extends('layouts.admin')
@section('title', 'Sites')
@section('content')
<x-admin-page-header title="Sites / Plants" subtitle="Manage all sites and plants">
    <a href="{{ route('admin.sites.create') }}" class="btn btn-admin-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>Add Site
    </a>
</x-admin-page-header>

<div class="admin-filters mb-0">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-auto flex-grow-1">
            <label class="form-label">Search</label>
            <input type="search" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Name or code...">
        </div>
        <div class="col-auto">
            <button class="btn btn-admin-outline btn-sm"><i class="bi bi-search me-1"></i>Search</button>
        </div>
    </form>
</div>

<div class="admin-card admin-table-card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Code</th>
                    <th>Type</th>
                    <th>Adress</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sites as $site)
                <tr>
                    <td class="fw-semibold">{{ $site->name }}</td>
                    <td><code class="small">{{ $site->code }}</code></td>
                    <td><span class="badge bg-light text-dark border">{{ ucfirst($site->type) }}</span></td>
                    <td>{{ $site->address }}</td>
                    <td>
                        @if($site->is_active)
                            <span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <x-admin-action-edit :href="route('admin.sites.edit', $site)" />
                        <x-admin-action-delete :action="route('admin.sites.destroy', $site)" confirm="Delete this site?" />
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5">
                        <div class="admin-empty">
                            <i class="bi bi-geo-alt d-block"></i>
                            No sites found
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $sites->links() }}</div>
@endsection
