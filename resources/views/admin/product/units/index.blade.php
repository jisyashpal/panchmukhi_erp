@extends('layouts.admin')
@section('title', 'Units')
@section('content')
<x-admin-page-header title="Units" subtitle="Measurement units for products">
    <a href="{{ route('admin.units.create') }}" class="btn btn-admin-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>Add Unit
    </a>
</x-admin-page-header>

<div class="admin-card admin-table-card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Abbreviation</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($units as $u)
                <tr>
                    <td class="fw-semibold">{{ $u->name }}</td>
                    <td><code class="small">{{ $u->abbreviation }}</code></td>
                    <td class="text-end">
                        <x-admin-action-edit :href="route('admin.units.edit', $u)" />
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="3">
                        <div class="admin-empty"><i class="bi bi-rulers d-block"></i>No units found</div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $units->links() }}</div>
@endsection
