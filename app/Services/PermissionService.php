<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\User;
use App\Models\UserPermissionOverride;
use Illuminate\Support\Facades\DB;

class PermissionService
{
    public function has(User $user, string $permission): bool
    {
        $override = $user->permissionOverrides()
            ->whereHas('permission', fn ($query) => $query->where('code', $permission))
            ->value('effect');

        if ($override !== null) {
            return $override === UserPermissionOverride::ALLOW;
        }

        // Legacy administrator accounts created before profiles were introduced retain full access until assigned a profile.
        if ($user->profile === null && $user->isAdmin()) {
            return true;
        }

        return $user->profile?->permissions()->where('code', $permission)->exists() ?? false;
    }

    /**
     * @return array<string, bool>
     */
    public function effective(User $user): array
    {
        if ($user->profile === null && $user->isAdmin()) {
            $permissions = Permission::pluck('code')->mapWithKeys(fn (string $code): array => [$code => true])->all();
        } else {
            $permissions = $user->profile?->permissions()->pluck('code')->mapWithKeys(fn (string $code): array => [$code => true])->all() ?? [];
        }
        foreach ($user->permissionOverrides()->with('permission')->get() as $override) {
            $permissions[$override->permission->code] = $override->effect === UserPermissionOverride::ALLOW;
        }

        return $permissions;
    }

    /**
     * @return array<string, array<int, array{code: string, name: string, inherited: bool, override: ?string, effective: bool}>>
     */
    public function byModule(User $user): array
    {
        $overrides = $user->permissionOverrides()->with('permission')->get()->keyBy('permission.code');
        $inherited = $user->profile?->permissions()->pluck('code')->all() ?? [];

        return Permission::query()->orderBy('module')->orderBy('name')->get()
            ->groupBy('module')->map(fn ($permissions): array => $permissions->map(function (Permission $permission) use ($inherited, $overrides): array {
                $override = $overrides->get($permission->code)?->effect;

                return [
                    'id' => $permission->id,
                    'code' => $permission->code,
                    'name' => $permission->name,
                    'inherited' => in_array($permission->code, $inherited, true),
                    'override' => $override,
                    'effective' => $override === UserPermissionOverride::ALLOW || ($override === null && in_array($permission->code, $inherited, true)),
                ];
            })->values()->all())->all();
    }

    public function setOverride(User $user, Permission $permission, string $effect): UserPermissionOverride
    {
        return $user->permissionOverrides()->updateOrCreate(
            ['permission_id' => $permission->id],
            ['effect' => $effect],
        );
    }

    public function removeOverride(User $user, Permission $permission): void
    {
        $user->permissionOverrides()->where('permission_id', $permission->id)->delete();
    }

    public function syncUserPermissions(User $user, array $selectedPermissionIds, User $actor): void
    {
        $selectedPermissionIds = array_map('intval', $selectedPermissionIds);
        $permissions = Permission::query()->get();
        $inheritedIds = $user->profile?->permissions()->pluck('permissions.id')->all() ?? [];
        $existing = $user->permissionOverrides()->get()->keyBy('permission_id');

        DB::transaction(function () use ($actor, $existing, $inheritedIds, $permissions, $selectedPermissionIds, $user): void {
            $users = app(UserService::class);
            $users->lockAdministrators();
            $wasEffective = $users->isEffectiveAdministrator($user->fresh());
            foreach ($permissions as $permission) {
                $inherited = in_array($permission->id, $inheritedIds, true);
                $selected = in_array($permission->id, $selectedPermissionIds, true);

                if ($inherited === $selected) {
                    $user->permissionOverrides()->where('permission_id', $permission->id)->delete();

                    continue;
                }

                $effect = $selected ? UserPermissionOverride::ALLOW : UserPermissionOverride::DENY;
                $this->setOverride($user, $permission, $effect);
            }

            $users->protectLastAdministrator($wasEffective);
            AuditLog::create([
                'user_id' => $actor->id,
                'action' => 'USER_PERMISSIONS_SYNCED',
                'subject_type' => User::class,
                'subject_id' => $user->id,
                'before_data' => ['overrides' => $existing->map(fn (UserPermissionOverride $override): string => $override->effect)->all()],
                'after_data' => ['selected_permission_ids' => $selectedPermissionIds],
            ]);
        });
    }
}
