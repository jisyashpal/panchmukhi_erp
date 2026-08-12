@extends($portalLayout ?? 'layouts.ho')
@section('title', 'Item Types')
@section('content')
    <x-admin-page-header title="Item Types" subtitle="Item Types under parent categories">
        <a href="{{ route('ho.sub-categories.create') }}" class="btn btn-admin-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Add Item Type
        </a>
    </x-admin-page-header>

    <div class="admin-filters mb-0">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label">Category</label>
                <select name="category_id" class="form-select form-select-sm">
                    <option value="">All categories</option>
                    @foreach($categories as $c)
                        <option value="{{ $c->id }}" @selected(request('category_id') == $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-admin-outline btn-sm"><i class="bi bi-funnel me-1"></i>Filter</button>
            </div>
        </form>
    </div>

    <div class="admin-card admin-table-card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Name</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($subCategories as $sc)
                        <tr>
                            <td>{{ $sc->category->name }}</td>
                            <td class="fw-semibold">{{ $sc->name }}</td>
                            <td class="text-end text-nowrap">
                                <x-admin-action-edit :href="route('ho.sub-categories.edit', $sc)" />
                                @if ($sc->products_count === 0)
                                    <x-admin-action-delete :action="route('ho.sub-categories.destroy', $sc)"
                                        confirm="Delete this Item Type?" />
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3">
                                <div class="admin-empty"><i class="bi bi-folder2 d-block"></i>No Item Types found</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $subCategories->links() }}</div>
@endsection