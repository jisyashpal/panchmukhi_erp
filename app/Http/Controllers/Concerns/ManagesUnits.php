<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

trait ManagesUnits
{
    use HasCatalogPortal;
    use RedirectsToProductCreate;

    public function index(): View
    {
        $units = Unit::orderBy('name')->paginate(20);

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
        $unit->update($data);

        return redirect()->route($this->catalogPortal().'.units.index')->with('success', 'Unit updated.');
    }

    public function destroy(Unit $unit): RedirectResponse
    {
        $unit->delete();

        return redirect()->route($this->catalogPortal().'.units.index')->with('success', 'Unit deleted.');
    }
}
