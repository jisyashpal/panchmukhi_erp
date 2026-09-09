@extends('layouts.plant')
@section('title', 'Edit Product Request')
@section('content')
<x-admin-page-header :title="$productRequest->items->count() === 1 ? 'Edit Product Request Item' : 'Edit Product Request'" subtitle="Request #{{ $productRequest->id }}">
    <a href="{{ route('plant.product-requests.index') }}" class="btn btn-admin-outline btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</x-admin-page-header>

<div class="admin-card">
    <div class="admin-card-body">
        <form method="POST" action="{{ route('plant.product-requests.update', $productRequest) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            
            <div class="row g-3">
                <x-admin-form-group label="Remarks" name="remarks" col="col-md-12">
                    <textarea name="remarks" class="form-control @error('remarks') is-invalid @enderror" rows="2">{{ old('remarks', $productRequest->remarks) }}</textarea>
                </x-admin-form-group>
            </div>

            <h2 class="h6 mt-4 mb-3">Items</h2>
            <div class="table-responsive mb-3">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th style="min-width: 250px;">Product</th>
                            <th class="text-end" style="width: 180px;">Quantity</th>
                            <th style="width: 150px;">Priority</th>
                            <th>Vehicle No / Machine No</th>
                            <th>Image</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($productRequest->items as $i => $item)
                            <tr class="product-request-edit-row">
                                <td class="position-relative">
                                    <input type="text"
                                           name="items[{{ $i }}][item_name]"
                                           value="{{ old('items.'.$i.'.item_name', $item->displayName()) }}"
                                           class="form-control form-control-sm item-name-input @error('items.'.$i.'.item_name') is-invalid @enderror"
                                           placeholder="Search item name or SKU..."
                                           required
                                           autocomplete="off">
                                    <input type="hidden"
                                           name="items[{{ $i }}][product_id]"
                                           value="{{ old('items.'.$i.'.product_id', $item->product_id) }}"
                                           class="item-product-id">
                                    <input type="hidden" name="items[{{ $i }}][id]" value="{{ $item->id }}">
                                </td>
                                <td class="text-end">
                                    <input type="number" step="1" min="1" name="items[{{ $i }}][quantity]" 
                                           value="{{ old('items.'.$i.'.quantity', $item->quantity) }}" 
                                           required class="form-control text-end @error('items.'.$i.'.quantity') is-invalid @enderror" style="max-width: 150px; display: inline-block;">
                                </td>
                                <td>
                                    <select name="items[{{ $i }}][priority]" class="form-select form-select-sm" required>
                                        @foreach(\App\Enums\PriorityLevel::cases() as $priority)
                                            <option value="{{ $priority->value }}" @selected(old('items.'.$i.'.priority', $item->priority?->value ?? 'medium') === $priority->value)>
                                                {{ $priority->label() }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input type="text" name="items[{{ $i }}][remarks]" 
                                           value="{{ old('items.'.$i.'.remarks', $item->remarks) }}" 
                                           class="form-control @error('items.'.$i.'.remarks') is-invalid @enderror" placeholder="Vehicle / machine no">
                                </td>
                                <td>
                                    @if($item->image_path)
                                        <div class="mb-1">
                                            <a href="{{ $item->image_url }}" target="_blank" class="d-inline-block">
                                                <img src="{{ $item->image_url }}" alt="Preview" class="img-thumbnail" style="max-height: 40px;">
                                            </a>
                                        </div>
                                    @endif
                                    <input type="file" name="items[{{ $i }}][image]" class="form-control form-control-sm" accept="image/*">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <button type="submit" class="btn btn-admin-primary">
                <i class="bi bi-check2-circle me-1"></i>Save Changes
            </button>
            <a href="{{ route('plant.product-requests.index') }}" class="btn btn-admin-outline">Cancel</a>
        </form>
    </div>
</div>

@php
    $productCatalog = isset($products) ? $products->map(function ($p) {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'sku' => $p->sku ?? '',
        ];
    })->values() : collect();
@endphp

@push('scripts')
<style>
#product-request-edit-autocomplete-portal {
    position: fixed;
    max-height: 250px;
    overflow-y: auto;
    z-index: 2000;
    display: none;
}
#product-request-edit-autocomplete-portal.is-open {
    display: block;
}
#product-request-edit-autocomplete-portal .suggestion-item {
    cursor: pointer;
    transition: background 0.15s ease-in-out;
}
#product-request-edit-autocomplete-portal .suggestion-item:hover {
    background-color: #f8f9fa;
}
</style>
@include('partials.infinite-suggestions')
<script type="application/json" id="product-request-edit-catalog-data">@json($productCatalog)</script>
<script>
(function() {
    const catalogEl = document.getElementById('product-request-edit-catalog-data');
    if (!catalogEl) return;
    const products = JSON.parse(catalogEl.textContent);
    let activeInput = null;
    let activeRow = null;

    const portal = document.createElement('div');
    portal.id = 'product-request-edit-autocomplete-portal';
    portal.className = 'autocomplete-suggestions shadow border rounded-bottom bg-white';
    document.body.appendChild(portal);

    const infinite = window.PanchmukhiInfiniteSuggestions.create(portal, {
        pageSize: 25,
        renderItem: function (product) {
            const div = document.createElement('div');
            div.className = 'suggestion-item px-3 py-2 border-bottom text-start';
            div.innerHTML = '<span class="fw-semibold text-dark d-block text-truncate">'
                + product.name
                + '</span> <span class="text-muted small">'
                + (product.sku || 'No SKU')
                + '</span>';
            div.addEventListener('mousedown', function (event) {
                event.preventDefault();
                if (activeRow) {
                    applyProductSelection(activeRow, product);
                }
            });
            return div;
        },
    });

    function hideSuggestions() {
        infinite.reset();
        portal.classList.remove('is-open');
        activeInput = null;
        activeRow = null;
    }

    function positionPortal(input) {
        const rect = input.getBoundingClientRect();
        portal.style.top = (rect.bottom + 2) + 'px';
        portal.style.left = rect.left + 'px';
        portal.style.width = rect.width + 'px';
    }

    function applyProductSelection(row, product) {
        const input = row.querySelector('.item-name-input');
        const productIdInput = row.querySelector('.item-product-id');
        input.value = product.name;
        productIdInput.value = product.id;
        hideSuggestions();
    }

    function showSuggestions(input) {
        const row = input.closest('tr');
        if (!row) return;
        const val = input.value.trim().toLowerCase();
        activeInput = input;
        activeRow = row;

        const filtered = products.filter(function (product) {
            if (!val) return true;
            return product.name.toLowerCase().includes(val)
                || (product.sku && product.sku.toLowerCase().includes(val));
        });

        if (filtered.length === 0) {
            hideSuggestions();
            return;
        }

        infinite.setResults(filtered);
        positionPortal(input);
        portal.classList.add('is-open');
    }

    document.addEventListener('input', function (event) {
        if (event.target.matches('.item-name-input')) {
            const row = event.target.closest('tr');
            const productIdInput = row?.querySelector('.item-product-id');
            if (productIdInput) {
                productIdInput.value = '';
            }
            showSuggestions(event.target);
        }
    });

    document.addEventListener('focusin', function (event) {
        if (event.target.matches('.item-name-input')) {
            showSuggestions(event.target);
        }
    });

    document.addEventListener('click', function (event) {
        if (event.target.matches('.item-name-input')) {
            showSuggestions(event.target);
            return;
        }
        if (activeInput && (activeInput.contains(event.target) || portal.contains(event.target))) {
            return;
        }
        hideSuggestions();
    });

    window.addEventListener('scroll', function (event) {
        if (!activeInput || !portal.classList.contains('is-open')) return;
        if (portal.contains(event.target)) return;
        positionPortal(activeInput);
    }, true);

    window.addEventListener('resize', function () {
        if (activeInput && portal.classList.contains('is-open')) {
            positionPortal(activeInput);
        }
    });
})();
</script>
@endpush
@endsection
