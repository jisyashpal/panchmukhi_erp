<?php

namespace App\Http\Controllers\Admin\Master;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MachineController extends Controller
{
    public function index(Request $request): View
    {
        $machines = Machine::query()
            ->with('site')
            ->when($request->filled('site_id'), fn ($q) => $q->where('site_id', $request->integer('site_id')))
            ->when($request->filled('search'), fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('code', 'like', '%'.$request->search.'%');
            }))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.master.machines.index', [
            'machines' => $machines,
            'sites' => Site::orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.master.machines.create', [
            'sites' => Site::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'site_id' => ['required', 'exists:sites,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('machines')->where(fn ($q) => $q->where('site_id', $request->integer('site_id'))),
            ],
            'is_active' => ['boolean'],
        ]);

        Machine::create($data);

        return redirect()->route('admin.machines.index')->with('success', 'Machine created.');
    }

    public function edit(Machine $machine): View
    {
        return view('admin.master.machines.edit', [
            'machine' => $machine,
            'sites' => Site::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Machine $machine): RedirectResponse
    {
        $data = $request->validate([
            'site_id' => ['required', 'exists:sites,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('machines')->where(fn ($q) => $q->where('site_id', $request->integer('site_id')))->ignore($machine->id),
            ],
            'is_active' => ['boolean'],
        ]);

        $machine->update($data);

        return redirect()->route('admin.machines.index')->with('success', 'Machine updated.');
    }

    public function destroy(Machine $machine): RedirectResponse
    {
        $machine->delete();

        return redirect()->route('admin.machines.index')->with('success', 'Machine deleted.');
    }
}
