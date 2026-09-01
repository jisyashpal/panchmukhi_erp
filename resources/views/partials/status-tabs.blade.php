@props(['route', 'param' => 'tab', 'tabs' => [], 'active' => null])

<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    @foreach($tabs as $tab)
        @php
            $key = $tab['key'] ?? null;
            $isActive = $active !== null
                ? (string) $active === (string) ($key ?? '')
                : ($key === null ? !request($param) : request($param) === $key);
            $query = request()->except($param);
            $url = $key === null
                ? route($route, $query)
                : route($route, array_merge($query, [$param => $key]));
        @endphp
        <a href="{{ $url }}" class="btn btn-sm {{ $isActive ? 'btn-admin-primary' : 'btn-admin-outline' }}">
            {{ $tab['label'] }}
        </a>
    @endforeach
</div>
