@extends('layouts.ho')
@section('title', 'Stocks')
@section('content')
<x-admin-page-header title="Stocks" subtitle="Current stock balances by site and product">
    <a href="{{ route('ho.stocks.stock-in') }}" class="btn btn-success btn-sm me-1">
        <i class="bi bi-box-arrow-in-down me-1"></i>Stock In
    </a>
    <a href="{{ route('ho.stocks.stock-out') }}" class="btn btn-warning btn-sm">
        <i class="bi bi-box-arrow-up me-1"></i>Stock Out
    </a>
</x-admin-page-header>

<x-list-filters :sites="$sites" :products="$products" />

<div class="admin-card admin-table-card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Site</th>
                    <th>Product</th>
                    <th class="text-end">Quantity</th>
                </tr>
            </thead>
            <tbody>
                @forelse($balances as $b)
                <tr>
                    <td>{{ $b->site->name }}</td>
                    <td class="fw-semibold">{{ $b->product->name }}</td>
                    <td class="text-end">{{ $b->quantity }} {{ $b->product->unit->abbreviation ?? '' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="3">
                        <div class="admin-empty"><i class="bi bi-boxes d-block"></i>No stock records found</div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $balances->links() }}</div>
@endsection
