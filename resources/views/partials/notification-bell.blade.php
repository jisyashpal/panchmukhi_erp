@php
    $user = auth()->user();
    $unread = $user->unreadNotifications()->limit(5)->get();
    $unreadCount = $user->unreadNotifications()->count();
    $indexRoute = $indexRoute ?? (auth()->user()->isSuperAdmin() ? 'admin.notifications.index' : 'site.notifications.index');
    $readRoute = $readRoute ?? (auth()->user()->isSuperAdmin() ? 'admin.notifications.read' : 'site.notifications.read');
    $readAllRoute = $readAllRoute ?? (auth()->user()->isSuperAdmin() ? 'admin.notifications.read-all' : 'site.notifications.read-all');
@endphp
<div class="dropdown">
    <button class="btn btn-admin-outline btn-sm position-relative" type="button" data-bs-toggle="dropdown">
        <i class="bi bi-bell"></i>
        @if($unreadCount > 0)
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:.6rem">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>
        @endif
    </button>
    <ul class="dropdown-menu dropdown-menu-end shadow" style="min-width: 280px;">
        <li class="dropdown-header d-flex justify-content-between align-items-center">
            <span>Notifications</span>
            @if($unreadCount > 0)
                <form method="POST" action="{{ route($readAllRoute) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-link btn-sm p-0">Mark all read</button>
                </form>
            @endif
        </li>
        @forelse($unread as $notification)
            <li>
                <form method="POST" action="{{ route($readRoute, $notification->id) }}">
                    @csrf
                    <button type="submit" class="dropdown-item py-2 text-wrap">
                        <strong class="d-block small">{{ $notification->data['title'] ?? 'Notification' }}</strong>
                        <span class="text-muted small">{{ $notification->data['body'] ?? '' }}</span>
                    </button>
                </form>
            </li>
        @empty
            <li><span class="dropdown-item text-muted small">No new notifications</span></li>
        @endforelse
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item small" href="{{ route($indexRoute) }}">View all</a></li>
    </ul>
</div>
