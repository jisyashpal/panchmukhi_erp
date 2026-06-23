@props([
    'permission' => null,
    'any' => [],
])

@if($permission && Gate::check($permission))
    {{ $slot }}
@elseif(!empty($any) && collect($any)->contains(fn ($p) => Gate::check($p)))
    {{ $slot }}
@endif
