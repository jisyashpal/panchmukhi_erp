@extends('layouts.plant')
@section('title', 'Notifications')
@section('content')
    <x-admin-page-header title="Notifications" subtitle="Alerts for requests, purchases and stock updates">
        @if(auth()->user()->unreadNotifications()->count())
            <form method="POST" action="{{ route('plant.notifications.read-all') }}">
                @csrf
                <button type="submit" class="btn btn-admin-outline btn-sm">
                    <i class="bi bi-check2-all me-1"></i>Mark all read
                </button>
            </form>
        @endif
    </x-admin-page-header>

    <div class="admin-card">
        <div class="list-group list-group-flush">
            @forelse($notifications as $n)
                @php
                    $type = $n->data['type'] ?? '';
                    $iconClass = match($type) {
                        'stock_updated' => 'bi-box-seam text-primary bg-primary-subtle',
                        'product_request_new' => 'bi-file-earmark-text text-success bg-success-subtle',
                        'purchase_request_new' => 'bi-cart-plus text-warning bg-warning-subtle',
                        default => 'bi-bell text-secondary bg-secondary-subtle',
                    };
                    $targetUrl = \App\Support\RelativeUrl::normalize($n->data['url'] ?? null);
                @endphp
                <form method="POST" action="{{ route('plant.notifications.read', $n->id) }}" class="d-block w-100 m-0">
                    @csrf
                    <button type="submit" class="list-group-item list-group-item-action text-start p-3 border-bottom d-flex align-items-center justify-content-between gap-3 {{ $n->read_at ? 'bg-white' : 'bg-light-subtle' }}">
                        <div class="d-flex align-items-center gap-3 min-w-0">
                            <div class="rounded-circle p-2 d-flex align-items-center justify-content-center flex-shrink-0 {{ explode(' ', $iconClass)[2] ?? 'bg-light' }}" style="width: 40px; height: 40px;">
                                <i class="bi {{ explode(' ', $iconClass)[0] }} {{ explode(' ', $iconClass)[1] }} fs-5"></i>
                            </div>
                            <div class="min-w-0">
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <span class="fw-semibold text-dark {{ $n->read_at ? '' : 'fw-bold' }}">{{ $n->data['title'] ?? 'Notification' }}</span>
                                    @if(!$n->read_at)
                                        <span class="badge bg-primary rounded-pill" style="font-size: 0.65rem;">New</span>
                                    @endif
                                </div>
                                <div class="text-secondary small text-truncate mt-1" style="max-width: 600px;">{{ $n->data['body'] ?? '' }}</div>
                            </div>
                        </div>
                        <div class="text-end flex-shrink-0">
                            <small class="text-muted d-block" style="font-size: 0.75rem;">{{ $n->created_at->diffForHumans() }}</small>
                            @if($targetUrl)
                                <small class="text-primary d-inline-flex align-items-center gap-1 mt-1" style="font-size: 0.75rem;">
                                    View <i class="bi bi-arrow-right"></i>
                                </small>
                            @endif
                        </div>
                    </button>
                </form>
            @empty
                <div class="p-5 text-muted text-center">
                    <i class="bi bi-bell-slash fs-1 d-block mb-2 text-secondary opacity-50"></i>
                    No notifications found
                </div>
            @endforelse
        </div>
    </div>
    <div class="mt-3">{{ $notifications->links() }}</div>
@endsection
