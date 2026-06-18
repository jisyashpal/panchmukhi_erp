@props(['route', 'param' => 'tab', 'tabs' => []])

<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    @foreach($tabs as $tab)
        @php
            $key = $tab['key'] ?? null;
            $active = $key === null ? !request($param) : request($param) === $key;
            $url = $key === null ? route($route) : route($route, [$param => $key]);
        @endphp
        <a href="{{ $url }}" class="btn btn-sm {{ $active ? 'btn-admin-primary' : 'btn-admin-outline' }}">
            {{ $tab['label'] }}
        </a>
    @endforeach
</div>
