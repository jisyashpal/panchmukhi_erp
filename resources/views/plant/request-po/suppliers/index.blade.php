@extends('layouts.plant')
@section('title', 'Suppliers')
@section('content')
<x-admin-page-header title="Suppliers" subtitle="Manage vendor and supplier records">
    <a href="{{ route('plant.suppliers.create') }}" class="btn btn-admin-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>Add Supplier
    </a>
</x-admin-page-header>

<div class="admin-card admin-table-card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Contact Person</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>GSTIN</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($suppliers as $s)
                <tr>
                    <td class="fw-semibold">{{ $s->name }}</td>
                    <td>{{ $s->contact_person ?? '—' }}</td>
                    <td>{{ $s->phone ?? '—' }}</td>
                    <td>{{ $s->email ?? '—' }}</td>
                    <td>{{ $s->gstin ?? '—' }}</td>
                    <td class="text-end">
                        <x-admin-action-edit :href="route('plant.suppliers.edit', $s)" />
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4">
                        <div class="admin-empty"><i class="bi bi-truck d-block"></i>No suppliers found</div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $suppliers->links() }}</div>
@endsection
