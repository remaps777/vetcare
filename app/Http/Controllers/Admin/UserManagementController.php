<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Profile;
use App\Models\User;
use App\Services\PermissionService;
use App\Services\UserService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.users.index', [
            'users' => User::with(['profile', 'owner', 'doctorProfile'])->orderBy('name')->paginate(20)->withQueryString(),
            'profiles' => Profile::when(! auth()->user()->hasPermission('usuarios.cambiar_permisos'), fn ($query) => $query->where('code', '!=', 'ADMINISTRADOR'))->orderBy('name')->get(),
        ]);
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', [
            'user' => $user->load(['profile', 'owner', 'doctorProfile']),
            'profiles' => Profile::when(! auth()->user()->hasPermission('usuarios.cambiar_permisos'), fn ($query) => $query->where('code', '!=', 'ADMINISTRADOR'))->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $user, UserService $users): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:30'],
            'profile_id' => ['required', 'integer', 'exists:profiles,id'],
            'is_active' => ['required', 'boolean'],
        ]);
        $profile = Profile::findOrFail($data['profile_id']);
        $isActive = (bool) $data['is_active'];
        $currentProfileId = $user->profile_id;
        $currentIsActive = $user->is_active;
        $users->update($user, collect($data)->only(['name', 'email', 'phone'])->all(), $request->user());

        if ((int) $currentProfileId !== (int) $profile->id) {
            $users->assignProfile($user, $profile, $request->user());
        }

        if ($currentIsActive !== $isActive) {
            $isActive ? $users->activate($user, $request->user()) : $users->deactivate($user, $request->user());
        }

        return redirect()->route('admin.users')->with('success', 'Usuario actualizado correctamente.');
    }

    public function profile(User $user): View
    {
        return view('admin.users.profile', ['user' => $user->load('profile'), 'profiles' => Profile::when(! auth()->user()->hasPermission('usuarios.cambiar_permisos'), fn ($query) => $query->where('code', '!=', 'ADMINISTRADOR'))->orderBy('name')->get()]);
    }

    public function changeProfile(Request $request, User $user, UserService $users): RedirectResponse
    {
        $data = $request->validate(['profile_id' => ['required', 'integer', 'exists:profiles,id']]);
        $users->assignProfile($user, Profile::findOrFail($data['profile_id']), $request->user());

        return back()->with('success', 'Perfil operativo actualizado.');
    }

    public function permissions(User $user, PermissionService $permissions): View
    {
        return view('admin.users.permissions', ['user' => $user->load('profile'), 'permissions' => $permissions->byModule($user)]);
    }

    public function permissionData(User $user, PermissionService $permissions): JsonResponse
    {
        return response()->json([
            'user' => ['name' => $user->name, 'profile' => $user->profile?->name ?? 'Sin perfil'],
            'permissions' => $permissions->byModule($user),
        ]);
    }

    public function syncPermissions(Request $request, User $user, PermissionService $permissions): RedirectResponse
    {
        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);
        $permissions->syncUserPermissions($user, $data['permissions'] ?? [], $request->user());

        return back()->with('success', 'Permisos actualizados correctamente.');
    }

    public function setPermission(Request $request, User $user, PermissionService $permissions): RedirectResponse
    {
        $data = $request->validate([
            'permission_id' => ['required', 'integer', 'exists:permissions,id'],
            'effect' => ['required', Rule::in(['INHERIT', 'ALLOW', 'DENY'])],
        ]);
        $permission = Permission::findOrFail($data['permission_id']);

        DB::transaction(function () use ($data, $permission, $permissions, $user, $request): void {
            $users = app(UserService::class);
            $users->lockAdministrators();
            $wasEffective = $users->isEffectiveAdministrator($user->fresh());
            if ($data['effect'] === 'INHERIT') {
                $permissions->removeOverride($user, $permission);
                $action = 'USER_PERMISSION_INHERITED';
            } else {
                $permissions->setOverride($user, $permission, $data['effect']);
                $action = $data['effect'] === 'ALLOW' ? 'USER_PERMISSION_ALLOWED' : 'USER_PERMISSION_DENIED';
            }
            $users->protectLastAdministrator($wasEffective);
            AuditLog::create(['user_id' => $request->user()->id, 'action' => $action, 'subject_type' => User::class, 'subject_id' => $user->id, 'before_data' => [], 'after_data' => ['permission' => $permission->code, 'effect' => $data['effect']]]);
        });

        return back()->with('success', 'Permiso actualizado.');
    }

    public function toggle(Request $request, User $user, UserService $users): RedirectResponse
    {
        $user->is_active ? $users->deactivate($user, $request->user()) : $users->activate($user, $request->user());

        return back()->with('success', $user->is_active ? 'Usuario activado.' : 'Usuario desactivado.');
    }

    public function restorePassword(Request $request, User $user, UserService $users): RedirectResponse
    {
        return back()->with('temporary_password', $users->restorePassword($user, $request->user()));
    }
}
