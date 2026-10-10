@extends($portalLayout ?? 'layouts.ho')
@section('title', 'Party-wise Purchase Summary')
@section('content')
<x-admin-page-header title="Party-wise Purchase Summary" subtitle="Purchase amount, payments and balance grouped by supplier">
    <x-report-export-button />
</x-admin-page-header>

<x-report-filters
    :plants="$plants"
    :show-dates="true"
    search-placeholder="Supplier name..."
    :clear-route="route('ho.reports.party-purchase')"
/>

<div class="admin-card admin-table-card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Supplier / Party</th>
                    <th class="text-end">Orders</th>
                    <th class="text-end">B/F</th>
                    <th class="text-end">Amount</th>
                    <th class="text-end">Paid</th>
                    <th class="text-end">Balance</th>
                    <th class="text-end" style="width: 150px;">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $r)
                    <tr>
                        <td>
                            @if($r->supplier)
                                <span class="d-block fw-semibold">{{ $r->supplier->name }}</span>
                                @if($r->supplier->gstin)
                                    <small class="text-muted">GSTIN: {{ $r->supplier->gstin }}</small>
                                @endif
                            @else
                                <span class="text-muted">Unknown</span>
                            @endif
                        </td>
                        <td class="text-end">{{ $r->order_count }}</td>
                        <td class="text-end">₹{{ number_format($r->brought_forward, 2) }}</td>
                        <td class="text-end fw-semibold">₹{{ number_format($r->total_amount, 2) }}</td>
                        <td class="text-end">₹{{ number_format($r->paid_amount, 2) }}</td>
                        <td class="text-end fw-semibold">₹{{ number_format($r->balance, 2) }}</td>
                        <td class="text-end">
                            <a href="{{ route('ho.reports.party-ledger', ['supplier' => $r->supplier_id, 'fy' => request('fy'), 'plant_id' => request('plant_id'), 'date_from' => request('date_from'), 'date_to' => request('date_to')]) }}" class="btn btn-sm btn-admin-outline">
                                <i class="bi bi-journal-text me-1"></i>View Ledger
                            </a>
                        </td>
                    </tr>
                @empty
                    @include('partials.table-empty', ['colspan' => 7, 'icon' => 'bi-building', 'message' => 'No purchase data found for the selected filters.'])
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
