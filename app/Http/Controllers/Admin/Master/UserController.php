<?php

namespace App\Http\Controllers\Admin\Master;

use App\Enums\UserApprovalStatus;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Plant;
use App\Models\User;
use App\Notifications\ErpDatabaseNotification;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(
        protected NotificationService $notifications
    ) {}

    public function index(Request $request): View
    {
        $users = User::query()
            ->with(['plant', 'role'])
            ->when($request->filled('plant_id'), fn ($q) => $q->where('plant_id', $request->integer('plant_id')))
            ->when($request->filled('role_id'), fn ($q) => $q->where('role_id', $request->integer('role_id')))
            ->when($request->has('is_active') && $request->input('is_active') !== '', fn ($q) => $q->where('is_active', (bool) $request->integer('is_active')))
            ->when($request->filled('search'), fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('email', 'like', '%'.$request->search.'%');
            }))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.master.users.index', [
            'users' => $users,
            'plants' => Plant::where('is_active', true)->orderBy('name')->get(),
            'roles' => Role::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.master.users.create', [
            'plants' => Plant::where('is_active', true)->orderBy('name')->get(),
            'roles' => Role::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->prepareUserData($this->validated($request));
        $data['password'] = Hash::make($data['password']);
        $data['approval_status'] = UserApprovalStatus::Approved;
        $data['is_active'] = $data['is_active'] ?? true;

        User::create($data);

        return redirect()->route('admin.users.index')->with('success', 'User created successfully.');
    }

    public function edit(User $user): View
    {
        return view('admin.master.users.edit', [
            'user' => $user->load(['role', 'plant']),
            'plants' => Plant::where('is_active', true)->orderBy('name')->get(),
            'roles' => Role::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->prepareUserData($this->validated($request, $user));

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $superAdminRoleId = Role::where('slug', Role::SLUG_SUPER_ADMIN)->value('id');

        if ($user->role_id === $superAdminRoleId
            && ($data['role_id'] ?? $user->role_id) != $superAdminRoleId
            && User::where('role_id', $superAdminRoleId)->where('id', '!=', $user->id)->count() === 0
        ) {
            return back()->withInput()->with('error', 'Cannot remove the last Super Admin.');
        }

        $user->update($data);

        return redirect()->route('admin.users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $superAdminRoleId = Role::where('slug', Role::SLUG_SUPER_ADMIN)->value('id');

        if ($user->role_id === $superAdminRoleId && User::where('role_id', $superAdminRoleId)->count() <= 1) {
            return back()->with('error', 'Cannot delete the last Super Admin.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'User deleted successfully.');
    }

    protected function validated(Request $request, ?User $user = null): array
    {
        $role = Role::query()
            ->where('id', $request->integer('role_id'))
            ->where('is_active', true)
            ->first();

        $plantRequired = ($role?->requires_plant ?? false) && $role?->slug !== Role::SLUG_PURCHASER;

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'role_id' => ['required', Rule::exists('roles', 'id')->where('is_active', true)],
            'plant_id' => [$plantRequired ? 'required' : 'nullable', 'exists:plants,id'],
            'is_active' => ['boolean'],
        ]);
    }

    protected function prepareUserData(array $data): array
    {
        $role = Role::find($data['role_id']);

        if (! $role?->requires_plant || $role?->slug === Role::SLUG_PURCHASER) {
            $data['plant_id'] = null;
        }

        return $data;
    }
}
