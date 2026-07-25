@extends('layouts.ho')
@section('title', 'Products')
@section('content')
    <x-admin-page-header title="Products" subtitle="Product catalog — add and manage products">
        <a href="{{ route('ho.products.create') }}" class="btn btn-admin-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Add Product
        </a>
    </x-admin-page-header>

    <div class="admin-filters mb-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-12 col-md-2">
                <label class="form-label small" for="filter_category_id">Category</label>
                <select name="category_id" id="filter_category_id" class="form-select form-select-sm"
                    onchange="this.form.submit()">
                    <option value="">All categories</option>
                    @foreach($categories as $c)
                        <option value="{{ $c->id }}" @selected(request('category_id') == $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label small" for="filter_brand_id">Brand</label>
                <select name="brand_id" id="filter_brand_id" class="form-select form-select-sm"
                    onchange="this.form.submit()">
                    <option value="">All brands</option>
                    @foreach($brands as $b)
                        <option value="{{ $b->id }}" @selected(request('brand_id') == $b->id)>{{ $b->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label small" for="filter_is_active">Status</label>
                <select name="is_active" id="filter_is_active" class="form-select form-select-sm"
                    onchange="this.form.submit()">
                    <option value="">All</option>
                    <option value="1" @selected(request('is_active') === '1')>Active</option>
                    <option value="0" @selected(request('is_active') === '0')>Inactive</option>
                </select>
            </div>
            <div class="col-12 col-md-3 position-relative">
                <label class="form-label small" for="product-search">Search Item Name / SKU</label>
                <input type="text" name="search" id="product-search" value="{{ request('search') }}"
                    class="form-control form-control-sm" placeholder="Enter item name or SKU..." autocomplete="off">
                <div id="autocomplete-suggestions"
                    class="autocomplete-suggestions d-none shadow border rounded-bottom bg-white"></div>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-admin-primary btn-sm"><i class="bi bi-search me-1"></i>Search</button>
                @if(request()->anyFilled(['category_id', 'brand_id', 'is_active', 'search']))
                    <a href="{{ route('ho.products.index') }}" class="btn btn-admin-outline btn-sm">Clear</a>
                @endif
            </div>
        </form>
    </div>

    <div class="admin-card admin-table-card">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Item Name</th>
                        <th>Size/Part</th>
                        <th>Category</th>
                        <th>Item Type</th>
                        <th>Brand</th>
                        <th>Unit</th>
                        <th class="text-end">Reorder Level</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $p)
                        <tr>
                            <td><code class="small">{{ $p->sku }}</code></td>
                            <td class="fw-semibold">{{ $p->name }}</td>
                            <td>{{ $p->size ?? '—' }}</td>
                            <td>{{ $p->category->name }}</td>
                            <td>{{ $p->subCategory?->name ?? '—' }}</td>
                            <td>{{ $p->brand?->name ?? '—' }}</td>
                            <td>{{ $p->unit->name }} <span class="text-muted small">({{ $p->unit->abbreviation }})</span></td>
                            <td class="text-end">{{ number_format($p->reorder_level) }}</td>
                            <td>
                                @if($p->is_active)
                                    <span class="badge bg-success-subtle text-success">Active</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary">Inactive</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                <x-admin-action-edit :href="route('ho.products.edit', $p)" />
                                <x-admin-action-delete :action="route('ho.products.destroy', $p)"
                                    confirm="Delete this product?" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10">
                                <div class="admin-empty"><i class="bi bi-box-seam d-block"></i>No products found</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <x-table-pagination :paginator="$products" label="products" />

    @push('scripts')
        <style>
            .autocomplete-suggestions {
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                z-index: 1050;
                max-height: 240px;
                overflow-y: auto;
            }

            .suggestion-item {
                cursor: pointer;
                transition: background 0.15s ease-in-out;
            }

            .suggestion-item:hover {
                background-color: #f8f9fa;
            }

            .suggestion-item:last-child {
                border-bottom: none !important;
            }
        </style>
        @include('partials.infinite-suggestions')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const searchInput = document.getElementById('product-search');
                const suggestionsBox = document.getElementById('autocomplete-suggestions');
                const products = @json($allProducts->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'sku' => $p->sku]));

                if (!searchInput || !suggestionsBox) return;

                const infinite = window.PanchmukhiInfiniteSuggestions.create(suggestionsBox, {
                    pageSize: 25,
                    renderItem: function (p) {
                        const div = document.createElement('div');
                        div.className = 'suggestion-item px-3 py-2 border-bottom text-start';
                        div.innerHTML = `<span class="fw-semibold text-dark d-block text-truncate" style="max-width:100%">${p.name}</span> <span class="text-muted small">${p.sku}</span>`;
                        div.addEventListener('click', function () {
                            searchInput.value = p.name;
                            infinite.reset();
                            suggestionsBox.classList.add('d-none');
                            searchInput.form.submit();
                        });
                        return div;
                    },
                });

                searchInput.addEventListener('input', function () {
                    const val = this.value.trim().toLowerCase();

                    if (!val) {
                        infinite.reset();
                        suggestionsBox.classList.add('d-none');
                        return;
                    }

                    const filtered = products.filter(p =>
                        p.name.toLowerCase().includes(val) ||
                        (p.sku && p.sku.toLowerCase().includes(val))
                    );

                    if (!infinite.setResults(filtered)) {
                        suggestionsBox.classList.add('d-none');
                        return;
                    }

                    suggestionsBox.classList.remove('d-none');
                });

                document.addEventListener('click', function (e) {
                    if (!searchInput.contains(e.target) && !suggestionsBox.contains(e.target)) {
                        infinite.reset();
                        suggestionsBox.classList.add('d-none');
                    }
                });
            });
        </script>
    @endpush
@endsection