@extends('layouts.admin')
@section('title', 'Edit Item Type')
@section('content')
    <x-admin-page-header title="Edit Item Type" subtitle="Update Item Type details">
        <a href="{{ route('admin.sub-categories.index') }}" class="btn btn-admin-outline btn-sm"><i
                class="bi bi-arrow-left me-1"></i>Back</a>
    </x-admin-page-header>

    <div class="admin-card">
        <div class="admin-card-body">
            <form method="POST" action="{{ route('admin.sub-categories.update', $subCategory) }}">
                @csrf
                @method('PUT')
                <div class="row g-3">
                    <x-admin-form-group label="Category" name="category_id" required>
                        <select name="category_id" id="category_id" class="form-select" required>
                            @foreach($categories as $c)
                                <option value="{{ $c->id }}" @selected(old('category_id', $subCategory->category_id) == $c->id)>
                                    {{ $c->name }}
                                </option>
                            @endforeach
                        </select>
                    </x-admin-form-group>
                    <x-admin-form-input label="Name" name="name" :value="old('name', $subCategory->name)" required />
                    <div class="col-12">
                        <div class="form-check">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="active"
                                @checked(old('is_active', $subCategory->is_active))>
                            <label class="form-check-label" for="active">Active</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-admin-primary">Update Item Type</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection