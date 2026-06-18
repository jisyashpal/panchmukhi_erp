@props(['href'])

<a href="{{ $href }}" {{ $attributes->merge(['class' => 'btn btn-sm btn-admin-outline', 'title' => 'Edit', 'aria-label' => 'Edit']) }}>
    <i class="bi bi-pencil" aria-hidden="true"></i>
</a>
