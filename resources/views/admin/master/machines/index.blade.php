@extends('layouts.admin')
@section('title', 'Machines')
@section('content')
<x-admin-page-header title="Machines" subtitle="Equipment master by site / plant">
    <a href="{{ route('admin.machines.create') }}" class="btn btn-admin-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>Add Machine
    </a>
</x-admin-page-header>

<div class="admin-filters mb-0">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label small">Site</label>
            <select name="site_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All sites</option>
                @foreach($sites as $s)
                    <option value="{{ $s->id }}" @selected(request('site_id') == $s->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label small">Search</label>
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
                    <th>Site / Plant</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($machines as $machine)
                <tr>
                    <td class="fw-semibold">{{ $machine->name }}</td>
                    <td><code class="small">{{ $machine->code }}</code></td>
                    <td>{{ $machine->site->name }}</td>
                    <td>
                        @if($machine->is_active)
                            <span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <x-admin-action-edit :href="route('admin.machines.edit', $machine)" />
                        <x-admin-action-delete :action="route('admin.machines.destroy', $machine)" confirm="Delete this machine?" />
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5">
                        <div class="admin-empty">
                            <i class="bi bi-gear-wide-connected d-block"></i>
                            No machines found
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $machines->links() }}</div>
@endsection
