<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Profile;
use App\Models\User;
use App\Models\WorkerApplication;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function update(User $target, array $data, User $actor): User
    {
        $before = $target->only(['name', 'email', 'phone', 'is_active', 'profile_id']);
        $target->fill($data)->save();
        $this->audit($actor, $target, 'USER_UPDATED', $before, $target->only(['name', 'email', 'phone', 'is_active', 'profile_id']));

        return $target;
    }

    public function assignProfile(User $target, Profile $profile, User $actor): User
    {
        return DB::transaction(function () use ($target, $profile, $actor): User {
            $this->lockAdministrators();
            $target->refresh();
            if ((int) $target->profile_id === (int) $profile->id) {
                return $target;
            }
            abort_if($profile->code === 'ADMINISTRADOR' && ! $actor->hasPermission('usuarios.cambiar_permisos'), 403);
            abort_if($profile->code === 'DOCTOR' && ! $target->doctorProfile?->isApproved(), 422, 'Completa y aprueba los datos profesionales antes de asignar el perfil Doctor.');
            $wasEffective = $this->isEffectiveAdministrator($target);
            $before = ['profile_id' => $target->profile_id, 'profile' => $target->profile?->code];
            $target->profile()->associate($profile);
            $target->role = $target->effectiveRole();
            $target->save();
            $this->protectLastAdministrator($wasEffective);
            $this->audit($actor, $target, 'USER_PROFILE_CHANGED', $before, ['profile_id' => $profile->id, 'profile' => $profile->code]);

            return $target;
        });
    }

    public function activate(User $target, User $actor, bool $approvingApplication = false): User
    {
        abort_if(! $approvingApplication && WorkerApplication::where('user_id', $target->id)->where('status', '!=', 'APPROVED')->exists(), 422, 'Aprueba primero la solicitud del trabajador.');
        abort_if($target->isDoctor() && ! $target->doctorProfile?->isApproved(), 422, 'Aprueba primero la solicitud del doctor.');
        $target->update(['is_active' => true]);
        $this->audit($actor, $target, 'USER_ACTIVATED', ['is_active' => false], ['is_active' => true]);

        return $target;
    }

    public function deactivate(User $target, User $actor): User
    {
        return DB::transaction(function () use ($target, $actor): User {
            $this->lockAdministrators();
            $target->refresh();
            if ($target->id === $actor->id) {
                throw ValidationException::withMessages(['user' => 'No puedes desactivar tu propia cuenta.']);
            }
            $wasEffective = $this->isEffectiveAdministrator($target);
            $target->update(['is_active' => false]);
            $this->protectLastAdministrator($wasEffective);
            $this->audit($actor, $target, 'USER_DEACTIVATED', ['is_active' => true], ['is_active' => false]);

            return $target;
        });
    }

    public function restorePassword(User $target, User $actor): string
    {
        $temporaryPassword = Str::password(16);
        $target->update(['password' => Hash::make($temporaryPassword)]);
        $this->audit($actor, $target, 'USER_PASSWORD_RESET', [], ['reset' => true]);

        return $temporaryPassword;
    }

    public function lockAdministrators(): void
    {
        User::query()->orderBy('id')->lockForUpdate()->get(['id']);
    }

    public function isEffectiveAdministrator(User $user): bool
    {
        return $user->is_active && $user->hasPermission('usuarios.ver') && $user->hasPermission('usuarios.cambiar_permisos');
    }

    public function protectLastAdministrator(bool $wasEffective): void
    {
        if ($wasEffective && ! User::with('profile')->where('is_active', true)->get()->contains(fn (User $user): bool => $this->isEffectiveAdministrator($user))) {
            throw ValidationException::withMessages(['user' => 'No puedes quitar el acceso al último administrador efectivo.']);
        }
    }

    private function audit(User $actor, User $target, string $action, array $before, array $after): void
    {
        AuditLog::create([
            'user_id' => $actor->id,
            'action' => $action,
            'subject_type' => User::class,
            'subject_id' => $target->id,
            'before_data' => $before,
            'after_data' => $after,
        ]);
    }
}
