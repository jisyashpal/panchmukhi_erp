@extends('layouts.ho')
@section('title', 'Product Life')
@section('content')
<x-admin-page-header title="Product Life" subtitle="Used product life entries">
    <a href="{{ route('ho.product-life.create') }}" class="btn btn-primary btn-sm">Add entry</a>
</x-admin-page-header>
<x-list-filters :sites="$sites" :products="$products" />
<div class="admin-card admin-table-card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Date</th><th>Site</th><th>Product</th><th>Machine</th><th class="text-end">Qty</th><th class="text-end">Life hrs</th></tr></thead>
            <tbody>
                @forelse($entries as $e)
                <tr>
                    <td>{{ $e->used_at->format('d M Y') }}</td>
                    <td>{{ $e->site->name }}</td>
                    <td>{{ $e->product->name }}</td>
                    <td>{{ $e->machine?->name ?? '—' }}</td>
                    <td class="text-end">{{ $e->quantity }}</td>
                    <td class="text-end">{{ $e->life_hours }}</td>
                </tr>
                @empty
                <tr><td colspan="6"><div class="admin-empty">No entries</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $entries->links() }}</div>
@endsection
