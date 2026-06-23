@extends('layouts.ho')
@section('title', 'Usage by Category')
@section('content')
<x-admin-page-header title="Product Usage by Category" subtitle="Consumption grouped by category" />

<x-report-filters
    :sites="$sites"
    :categories="$categories"
    :show-dates="true"
    search-placeholder="Category name..."
    :clear-route="route('ho.reports.usage-by-category')"
/>

<div class="admin-card admin-table-card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Category</th>
                    <th class="text-end">Qty</th>
                    <th class="text-end">Life hours</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $r)
                    <tr>
                        <td>{{ $r->category_name }}</td>
                        <td class="text-end">{{ $r->total_qty }}</td>
                        <td class="text-end">{{ $r->total_life_hours }}</td>
                    </tr>
                @empty
                    @include('partials.table-empty', ['colspan' => 3, 'icon' => 'bi-diagram-3', 'message' => 'No usage data found for the selected filters.'])
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
