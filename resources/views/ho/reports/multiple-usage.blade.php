@extends('layouts.ho')
@section('title', 'Multiple Usage Report')
@section('content')
<x-admin-page-header title="Multiple Usage Highlights" subtitle="Products used more than once" />

<x-report-filters
    :sites="$sites"
    :categories="$categories"
    :products="$products"
    :show-dates="true"
    search-placeholder="Product name..."
    :clear-route="route('ho.reports.multiple-usage')"
/>

<div class="admin-card admin-table-card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Product</th>
                    <th class="text-end">Use count</th>
                    <th class="text-end">Total qty</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $r)
                    <tr>
                        <td>{{ $r->product?->name }}</td>
                        <td class="text-end text-warning fw-semibold">{{ $r->use_count }}</td>
                        <td class="text-end">{{ $r->total_qty }}</td>
                    </tr>
                @empty
                    @include('partials.table-empty', ['colspan' => 3, 'icon' => 'bi-exclamation-diamond', 'message' => 'No products with multiple usage found for the selected filters.'])
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
