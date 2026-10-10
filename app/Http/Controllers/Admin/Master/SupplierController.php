<?php

namespace App\Http\Controllers\Admin\Master;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Support\SupplierOpeningBalance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $suppliers = Supplier::query()
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->search.'%'))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.master.suppliers.index', compact('suppliers'));
    }

    public function create(): View
    {
        return view('admin.master.suppliers.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'gstin' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            ...SupplierOpeningBalance::rules(),
        ], [], SupplierOpeningBalance::attributeNames());

        Supplier::create(array_merge($data, SupplierOpeningBalance::attributes($data)));

        return redirect()->route('admin.suppliers.index')->with('success', 'Supplier created.');
    }

    public function edit(Supplier $supplier): View
    {
        return view('admin.master.suppliers.edit', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'gstin' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];

        if ($supplier->opening_balance_date === null) {
            $rules = [...$rules, ...SupplierOpeningBalance::rules()];
        }

        $data = $request->validate($rules, [], SupplierOpeningBalance::attributeNames());

        if ($supplier->opening_balance_date === null) {
            $data = array_merge($data, SupplierOpeningBalance::attributes($data));
        }

        $supplier->update($data);

        return redirect()->route('admin.suppliers.index')->with('success', 'Supplier updated.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $supplier->delete();

        return redirect()->route('admin.suppliers.index')->with('success', 'Supplier deleted.');
    }
}
