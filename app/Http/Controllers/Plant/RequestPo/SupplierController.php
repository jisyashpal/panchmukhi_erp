<?php

namespace App\Http\Controllers\Plant\RequestPo;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Support\SupplierOpeningBalance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(): View
    {
        return view('plant.request-po.suppliers.index', [
            'suppliers' => Supplier::orderBy('name')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('plant.request-po.suppliers.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'gstin' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            ...SupplierOpeningBalance::rules(),
        ], [], SupplierOpeningBalance::attributeNames());

        Supplier::create(array_merge($data, SupplierOpeningBalance::attributes($data), ['is_active' => true]));

        return redirect()->route('plant.suppliers.index')->with('success', 'Supplier added.');
    }

    public function edit(Supplier $supplier): View
    {
        return view('plant.request-po.suppliers.edit', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'gstin' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $supplier->update($data);

        return redirect()->route('plant.suppliers.index')->with('success', 'Supplier updated.');
    }
}
