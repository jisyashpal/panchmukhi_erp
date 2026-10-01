<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Category;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

trait ManagesVehicles
{
    use HasCatalogPortal;

    public function index(Request $request): View
    {
        $vehicles = Vehicle::with('category')
            ->withCount('stockMovements')
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view($this->catalogView('vehicles.index'), [
            'vehicles' => $vehicles,
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view($this->catalogView('vehicles.create'), [
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'registration_no' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ]);
        $this->assertUniqueVehicle($data);

        Vehicle::create($data);

        return redirect()->route($this->catalogPortal().'.vehicles.index')
            ->with('success', 'Vehicle created.');
    }

    public function edit(Vehicle $vehicle): View
    {
        return view($this->catalogView('vehicles.edit'), [
            'vehicle' => $vehicle,
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $data = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'registration_no' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ]);
        $this->assertUniqueVehicle($data, $vehicle->id);

        $vehicle->update($data);

        return redirect()->route($this->catalogPortal().'.vehicles.index')
            ->with('success', 'Vehicle updated.');
    }

    public function destroy(Vehicle $vehicle): RedirectResponse
    {
        if ($vehicle->stockMovements()->exists()) {
            return redirect()->route($this->catalogPortal().'.vehicles.index')
                ->with('error', 'Cannot delete a vehicle that has stock-out history.');
        }

        if ($vehicle->productUseEntries()->exists()) {
            return redirect()->route($this->catalogPortal().'.vehicles.index')
                ->with('error', 'Cannot delete a vehicle that has product life history.');
        }

        $vehicle->delete();

        return redirect()->route($this->catalogPortal().'.vehicles.index')
            ->with('success', 'Vehicle deleted.');
    }

    private function assertUniqueVehicle(array $data, ?int $ignoreId = null): void
    {
        $query = Vehicle::query()->when($ignoreId, fn ($vehicle) => $vehicle->whereKeyNot($ignoreId));
        $registration = mb_strtolower(trim((string) ($data['registration_no'] ?? '')));

        if ($registration !== '') {
            $registrationTaken = (clone $query)
                ->whereRaw('LOWER(TRIM(registration_no)) = ?', [$registration])
                ->exists();

            if ($registrationTaken) {
                throw ValidationException::withMessages([
                    'registration_no' => 'This registration number already exists.',
                ]);
            }

            return;
        }

        $nameTaken = (clone $query)
            ->where('category_id', $data['category_id'])
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($data['name']))])
            ->where(function ($vehicle) {
                $vehicle->whereNull('registration_no')->orWhereRaw("TRIM(registration_no) = ''");
            })
            ->exists();

        if ($nameTaken) {
            throw ValidationException::withMessages([
                'name' => 'This vehicle already exists in the selected category.',
            ]);
        }
    }
}
