@extends('layouts.ho')
@section('title', 'Product Life Report')
@section('content')
<x-admin-page-header title="Product Life Usage" subtitle="Usage entries with life hours" />

<x-report-filters
    :sites="$sites"
    :categories="$categories"
    :products="$products"
    :show-dates="true"
    search-placeholder="Product, machine or site..."
    :clear-route="route('ho.reports.product-life')"
/>

<div class="admin-card admin-table-card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Site</th>
                    <th>Product</th>
                    <th>Machine</th>
                    <th class="text-end">Qty</th>
                    <th class="text-end">Life hrs</th>
                </tr>
            </thead>
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
                    @include('partials.table-empty', ['colspan' => 6, 'icon' => 'bi-clock-history', 'message' => 'No usage entries found for the selected filters.'])
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
