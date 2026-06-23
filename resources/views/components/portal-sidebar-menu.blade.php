@props([
    'portal' => 'site',
    'menuIdSuffix' => '',
])

@php
    use App\Support\MenuBuilder;
    use Illuminate\Support\Str;

    $items = MenuBuilder::visibleItems($portal);
    $menuLabel = MenuBuilder::portalLabel($portal);
@endphp

<p class="sidebar-menu-label px-2 mb-1">{{ $menuLabel }}</p>

@foreach($items as $item)
    @if(($item['type'] ?? 'link') === 'link')
        <a href="{{ route($item['route']) }}"
            class="sidebar-link {{ MenuBuilder::isActive($item['routes'] ?? []) ? 'active' : '' }}">
            <i class="bi {{ $item['icon'] }}"></i>
            <span>{{ $item['label'] }}</span>
        </a>
    @elseif(($item['type'] ?? '') === 'group')
        @php
            $groupOpen = MenuBuilder::groupOpen($item);
            $groupId = 'menu-'.$portal.'-'.Str::slug($item['label']).($menuIdSuffix !== '' ? '-'.Str::slug($menuIdSuffix) : '');
        @endphp
        <div class="sidebar-menu-group {{ $groupOpen ? 'is-open' : '' }}">
            <button class="sidebar-menu-toggle {{ $groupOpen ? '' : 'collapsed' }}" type="button"
                data-bs-toggle="collapse" data-bs-target="#{{ $groupId }}"
                aria-expanded="{{ $groupOpen ? 'true' : 'false' }}">
                <span class="d-flex align-items-center gap-2">
                    <i class="bi {{ $item['icon'] }}"></i>
                    <span>{{ $item['label'] }}</span>
                </span>
                <i class="bi bi-chevron-down sidebar-chevron"></i>
            </button>
            <div class="collapse {{ $groupOpen ? 'show' : '' }}" id="{{ $groupId }}">
                <div class="sidebar-submenu">
                    @foreach($item['children'] ?? [] as $child)
                        <a href="{{ route($child['route']) }}"
                            class="sidebar-sublink {{ MenuBuilder::isActive($child['routes'] ?? []) ? 'active' : '' }}">
                            <i class="bi {{ $child['icon'] }}"></i> {{ $child['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
@endforeach

@once
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('.sidebar-menu-group .collapse').forEach(function (collapseEl) {
                    collapseEl.addEventListener('show.bs.collapse', function () {
                        this.closest('.sidebar-menu-group')?.classList.add('is-open');
                    });
                    collapseEl.addEventListener('hide.bs.collapse', function () {
                        this.closest('.sidebar-menu-group')?.classList.remove('is-open');
                    });
                });
            });
        </script>
    @endpush
@endonce
