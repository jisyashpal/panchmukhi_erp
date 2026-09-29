@extends($portalLayout ?? 'layouts.ho')
@section('title', 'Product Requests')
@section('content')
    <x-admin-page-header title="Product Requests" subtitle="Review plant product requests and update status">
        <a href="{{ route('ho.product-requests.create') }}" class="btn btn-admin-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>New Request
        </a>
    </x-admin-page-header>

    <x-list-filters :plants="$plants" :products="$products" :showStatus="true" :showFinancialYear="true" :showDates="true"
        :clearRoute="route('ho.product-requests.index')" />

    <div class="admin-card admin-table-card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Plant</th>
                        <th>Requested By</th>
                        <th>Items</th>
                        {{-- <th>Priority</th> --}}
                        <th>Status</th>
                        <th>Date</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($productRequests as $pr)
                        <tr>
                            <td>#{{ $pr->id }}</td>
                            <td class="fw-semibold">{{ $pr->plant->name }}</td>
                            <td>{{ $pr->displayRequesterName() }}</td>
                            <td>
                                <span class="fw-semibold">{{ $pr->items_count }} items</span>
                                <span class="d-block small text-muted">Qty
                                    {{ number_format((float) ($pr->items_sum_quantity ?? 0), 2) }}</span>
                            </td>
                            {{-- <td><x-status-badge :status="$pr->priority" /></td> --}}
                            <td><x-status-badge :status="$pr->status" /></td>
                            <td>{{ $pr->created_at->format('d M Y') }}</td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2 align-items-center justify-content-end flex-wrap">
                                    @if ($pr->status === \App\Enums\RequestStatus::Submitted)
                                        <span class="badge bg-danger" style="font-size: 0.65rem;">New</span>
                                    @endif
                                    @if ($pr->purchaseRequest?->purchases->isNotEmpty())
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                                            <i class="bi bi-receipt me-1"></i>PO created
                                        </span>
                                    @endif
                                    <a href="{{ route('ho.product-requests.show', $pr) }}"
                                        class="btn btn-sm btn-admin-outline" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="admin-empty">No product requests</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $productRequests->links() }}</div>
@endsection
