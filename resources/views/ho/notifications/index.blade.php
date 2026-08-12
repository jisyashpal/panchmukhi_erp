@extends($portalLayout ?? 'layouts.ho')
@section('title', 'Notifications')
@section('content')
<x-admin-page-header title="Notifications">
    @if(auth()->user()->unreadNotifications()->count())
    <form method="POST" action="{{ route('ho.notifications.read-all') }}">@csrf<button class="btn btn-admin-outline btn-sm">Mark all read</button></form>
    @endif
</x-admin-page-header>
<div class="admin-card">
    <div class="list-group list-group-flush">
        @forelse($notifications as $n)
        <form method="POST" action="{{ route('ho.notifications.read', $n->id) }}">
            @csrf
            <button type="submit" class="list-group-item list-group-item-action text-start {{ $n->read_at ? '' : 'fw-semibold' }}">
                <div>{{ $n->data['title'] ?? 'Notification' }}</div>
                <small class="text-muted">{{ $n->data['body'] ?? '' }}</small>
                <small class="d-block text-muted">{{ $n->created_at->diffForHumans() }}</small>
            </button>
        </form>
        @empty
        <div class="p-4 text-muted text-center">No notifications</div>
        @endforelse
    </div>
</div>
<div class="mt-3">{{ $notifications->links() }}</div>
@endsection
