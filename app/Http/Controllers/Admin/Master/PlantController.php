<?php

namespace App\Http\Controllers\Admin\Master;

use App\Http\Controllers\Controller;
use App\Models\Plant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlantController extends Controller
{
    public function index(Request $request): View
    {
        $plants = Plant::query()
            ->when($request->filled('search'), fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('code', 'like', '%'.$request->search.'%');
            }))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.master.plants.index', compact('plants'));
    }

    public function create(): View
    {
        return view('admin.master.plants.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:plants,code'],
            'type' => ['required', 'in:plant,mine'],
            'address' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        Plant::create($data);

        return redirect()->route('admin.plants.index')->with('success', 'Plant created successfully.');
    }

    public function edit(Plant $plant): View
    {
        return view('admin.master.plants.edit', compact('plant'));
    }

    public function update(Request $request, Plant $plant): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:plants,code,'.$plant->id],
            'type' => ['required', 'in:plant,mine'],
            'address' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $plant->update($data);

        return redirect()->route('admin.plants.index')->with('success', 'Plant updated successfully.');
    }

    public function destroy(Plant $plant): RedirectResponse
    {
        $plant->delete();

        return redirect()->route('admin.plants.index')->with('success', 'Plant deleted.');
    }
}
