<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): RedirectResponse|View
    {
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            return view('admin.profile.edit', compact('user'));
        }

        if ($user->hasPlantRole()) {
            return redirect()->route('plant.profile.edit');
        }

        return view('admin.profile.edit', compact('user'));
    }

    public function editHo(Request $request): View
    {
        $user = $request->user()->load(['role']);

        return view('ho.profile.edit', compact('user'));
    }

    public function updateHo(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('ho.profile.edit')->with('status', 'profile-updated');
    }

    public function editPlant(Request $request): View
    {
        $user = $request->user()->load(['role', 'plant']);

        return view('plant.profile.edit', compact('user'));
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        $route = match (true) {
            $request->user()->isSuperAdmin() => 'admin.profile.edit',
            $request->user()->isHoDataOperator() => 'ho.profile.edit',
            $request->user()->hasPlantRole() => 'plant.profile.edit',
            default => 'admin.profile.edit',
        };

        return Redirect::route($route)->with('status', 'profile-updated');
    }

    public function updatePlant(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('plant.profile.edit')->with('status', 'profile-updated');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    public function destroyPlant(Request $request): RedirectResponse
    {
        return $this->destroy($request);
    }
}
