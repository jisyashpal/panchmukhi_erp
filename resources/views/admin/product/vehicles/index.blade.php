@extends('layouts.admin')
@section('title', 'Vehicles')
@section('content')
    <x-admin-page-header title="Vehicles" subtitle="Category-wise vehicles for stock-out usage">
        <a href="{{ route('admin.vehicles.create') }}" class="btn btn-admin-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Add Vehicle
        </a>
    </x-admin-page-header>

    <div class="admin-filters mb-0">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label">Category</label>
                <select name="category_id" class="form-select form-select-sm">
                    <option value="">All categories</option>
                    @foreach($categories as $c)
                        <option value="{{ $c->id }}" @selected(request('category_id') == $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-admin-outline btn-sm"><i class="bi bi-funnel me-1"></i>Filter</button>
            </div>
        </form>
    </div>

    <div class="admin-card admin-table-card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Name</th>
                        <th>Registration No.</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($vehicles as $vehicle)
                        <tr>
                            <td>{{ $vehicle->category->name }}</td>
                            <td class="fw-semibold">{{ $vehicle->name }}</td>
                            <td>{{ $vehicle->registration_no ?: '—' }}</td>
                            <td>
                                @if($vehicle->is_active)
                                    <span class="badge bg-success-subtle text-success">Active</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary">Inactive</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                <x-admin-action-edit :href="route('admin.vehicles.edit', $vehicle)" />
                                @if ($vehicle->stock_movements_count === 0)
                                    <x-admin-action-delete :action="route('admin.vehicles.destroy', $vehicle)"
                                        confirm="Delete this vehicle?" />
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="admin-empty"><i class="bi bi-truck d-block"></i>No vehicles found</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $vehicles->links() }}</div>
@endsection
