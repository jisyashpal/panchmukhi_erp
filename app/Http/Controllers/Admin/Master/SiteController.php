<?php

namespace App\Http\Controllers\Admin\Master;

use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SiteController extends Controller
{
    public function index(Request $request): View
    {
        $sites = Site::query()
            ->when($request->filled('search'), fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('code', 'like', '%'.$request->search.'%');
            }))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.master.sites.index', compact('sites'));
    }

    public function create(): View
    {
        return view('admin.master.sites.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:sites,code'],
            'type' => ['required', 'in:site,plant'],
            'address' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        Site::create($data);

        return redirect()->route('admin.sites.index')->with('success', 'Site created successfully.');
    }

    public function edit(Site $site): View
    {
        return view('admin.master.sites.edit', compact('site'));
    }

    public function update(Request $request, Site $site): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:sites,code,'.$site->id],
            'type' => ['required', 'in:site,plant'],
            'address' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $site->update($data);

        return redirect()->route('admin.sites.index')->with('success', 'Site updated successfully.');
    }

    public function destroy(Site $site): RedirectResponse
    {
        $site->delete();

        return redirect()->route('admin.sites.index')->with('success', 'Site deleted.');
    }
}
