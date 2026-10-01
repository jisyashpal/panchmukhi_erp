<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

trait ManagesUnits
{
    use HasCatalogPortal;
    use RedirectsToProductCreate;

    public function index(): View
    {
        $units = Unit::orderBy('name')->paginate(10);

        return view($this->catalogView('units.index'), compact('units'));
    }

    public function create(): View
    {
        return view($this->catalogView('units.create'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'abbreviation' => ['required', 'string', 'max:20'],
        ]);
        $this->assertUniqueUnit($data['name'], $data['abbreviation']);
        $unit = Unit::create($data);

        return $this->redirectAfterCatalogStore(
            $request,
            'Unit created.',
            $this->catalogPortal().'.units.index',
            ['unit_id' => $unit->id],
        );
    }

    public function edit(Unit $unit): View
    {
        return view($this->catalogView('units.edit'), compact('unit'));
    }

    public function update(Request $request, Unit $unit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'abbreviation' => ['required', 'string', 'max:20'],
        ]);
        $this->assertUniqueUnit($data['name'], $data['abbreviation'], $unit->id);
        $unit->update($data);

        return redirect()->route($this->catalogPortal().'.units.index')->with('success', 'Unit updated.');
    }

    public function destroy(Unit $unit): RedirectResponse
    {
        $unit->delete();

        return redirect()->route($this->catalogPortal().'.units.index')->with('success', 'Unit deleted.');
    }

    private function assertUniqueUnit(string $name, string $abbreviation, ?int $ignoreId = null): void
    {
        $query = Unit::query()->when($ignoreId, fn ($unit) => $unit->whereKeyNot($ignoreId));
        $errors = [];

        if ((clone $query)->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($name))])->exists()) {
            $errors['name'] = 'This unit already exists.';
        }

        if ((clone $query)->whereRaw('LOWER(TRIM(abbreviation)) = ?', [mb_strtolower(trim($abbreviation))])->exists()) {
            $errors['abbreviation'] = 'This abbreviation already exists.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
