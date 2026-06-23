@extends('layouts.ho')
@section('title', 'Category Purchase Report')
@section('content')
<x-admin-page-header title="Category-wise Purchase Amount" subtitle="Purchase amount grouped by category" />

<x-report-filters
    :sites="$sites"
    :categories="$categories"
    :show-dates="true"
    search-placeholder="Category name..."
    :clear-route="route('ho.reports.category-purchase')"
/>

<div class="admin-card admin-table-card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Category</th>
                    <th class="text-end">Qty</th>
                    <th class="text-end">Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $r)
                    <tr>
                        <td>{{ $r->category_name }}</td>
                        <td class="text-end">{{ $r->total_qty }}</td>
                        <td class="text-end">₹{{ number_format($r->total_amount, 2) }}</td>
                    </tr>
                @empty
                    @include('partials.table-empty', ['colspan' => 3, 'icon' => 'bi-pie-chart', 'message' => 'No purchase data found for the selected filters.'])
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
