@props([
    'action',
    'confirm' => 'Are you sure you want to delete this?',
])

<form action="{{ $action }}" method="POST" class="d-inline" onsubmit="return confirm(@js($confirm))">
    @csrf
    @method('DELETE')
    <button type="submit" {{ $attributes->merge(['class' => 'btn btn-sm btn-outline-danger', 'title' => 'Delete', 'aria-label' => 'Delete']) }}>
        <i class="bi bi-trash" aria-hidden="true"></i>
    </button>
</form>
