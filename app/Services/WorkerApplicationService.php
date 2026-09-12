<?php

namespace App\Services;

use App\Http\Requests\Auth\RegistrationRequest;
use App\Http\Requests\WorkerApplicationRequest;
use App\Models\AuditLog;
use App\Models\DoctorProfile;
use App\Models\Profile;
use App\Models\Specialty;
use App\Models\User;
use App\Models\WorkerApplication;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class WorkerApplicationService
{
    /** @param array<string, mixed> $data */
    public function submit(array $data): WorkerApplication
    {
        return DB::transaction(function () use ($data): WorkerApplication {
            $application = WorkerApplication::create($data);
            $this->audit($application, null, 'WORKER_APPLICATION_CREATED', []);

            return $application;
        });
    }

    /** @param array<string, mixed> $data */
    public function review(WorkerApplication $application, string $action, array $data, User $actor, string $version): WorkerApplication
    {
        $permission = match ($action) {
            'approve', 'reaccept' => 'solicitudes_trabajador.aprobar',
            'hold', 'reject', 'reconsider' => 'solicitudes_trabajador.rechazar',
        };
        abort_unless($actor->hasPermission($permission), 403);

        return DB::transaction(function () use ($application, $action, $data, $actor, $version): WorkerApplication {
            $application = WorkerApplication::whereKey($application->id)->lockForUpdate()->firstOrFail();
            abort_unless(hash_equals(RecordVersion::of($application), $version), 409, 'La solicitud cambió. Recarga la página antes de revisarla.');
            $allowed = match ($action) {
                'approve' => ['PENDING', 'ON_HOLD', 'REJECTED'],
                'reaccept' => ['REJECTED'],
                'hold' => ['PENDING'],
                'reject' => ['PENDING', 'ON_HOLD'],
                'reconsider' => ['REJECTED'],
            };
            abort_unless(in_array($application->status, $allowed, true), 409, 'Esta acción no está disponible para el estado actual.');
            $before = $application->only(['status', 'requested_profile_id', 'assigned_profile_id', 'review_notes']);
            if (in_array($action, ['approve', 'reaccept'], true)) {
                $profile = Profile::findOrFail($data['profile_id'] ?? $application->assigned_profile_id ?? $application->requested_profile_id);
                abort_unless(in_array($profile->code, WorkerApplication::PROFILES, true) || ($profile->code === 'ADMINISTRADOR' && $actor->hasPermission('usuarios.cambiar_permisos')), 403);
                if ((int) $profile->id !== (int) $application->requested_profile_id) {
                    abort_unless($actor->hasPermission('usuarios.cambiar_permisos'), 403, 'Necesitas autorización para cambiar el perfil solicitado.');
                }
                $user = $application->user_id ? User::whereKey($application->user_id)->lockForUpdate()->firstOrFail() : null;
                abort_if($user?->is_active, 409, 'La cuenta vinculada ya está activa. Revísala desde Usuarios.');
                $identity = [...$application->only(['first_name', 'last_name', 'dni', 'phone', 'email', 'username', 'specialty_id', 'license_number']), ...array_intersect_key($data, array_flip(['first_name', 'last_name', 'dni', 'phone', 'email', 'username', 'specialty_id', 'license_number']))];
                $validator = new RegistrationRequest;
                $identity = Validator::make($identity, WorkerApplicationRequest::identityRules($profile->code === 'DOCTOR', $user, $application), $validator->messages(), $validator->attributes())->validate();
                $user = $this->createWorker($identity, $profile, $actor, $user, $application->password);
                $application->user()->associate($user);
                $application->assignedProfile()->associate($profile);
                $application->password = null;
            }
            $application->status = match ($action) {
                'approve', 'reaccept' => 'APPROVED', 'hold' => 'ON_HOLD', 'reject' => 'REJECTED', 'reconsider' => 'PENDING',
            };
            $application->reviewed_by = $actor->id;
            $application->reviewed_at = now();
            $application->review_notes = $data['review_notes'] ?? null;
            $application->save();
            if ($application->user_id && $action !== 'approve' && $application->user->doctorProfile) {
                $doctor = $application->user->doctorProfile;
                $doctor->approval_status = $action === 'reject' ? 'REJECTED' : 'PENDING';
                $doctor->save();
            }
            $this->audit($application, $actor, match ($action) {
                'approve', 'reaccept' => 'WORKER_APPLICATION_APPROVED', 'hold' => 'WORKER_APPLICATION_ON_HOLD',
                'reject' => 'WORKER_APPLICATION_REJECTED', 'reconsider' => 'WORKER_APPLICATION_RECONSIDERED',
            }, $before);

            return $application;
        });
    }

    /** @param array<string, mixed> $data */
    public function createInternal(array $data, User $actor): User
    {
        abort_unless($actor->hasPermission('usuarios.editar'), 403);

        return DB::transaction(function () use ($data, $actor): User {
            $profile = Profile::findOrFail($data['profile_id']);
            abort_unless(in_array($profile->code, WorkerApplication::PROFILES, true) || ($profile->code === 'ADMINISTRADOR' && $actor->hasPermission('usuarios.cambiar_permisos')), 403);

            return $this->createWorker($data, $profile, $actor, null, $data['password']);
        });
    }

    /** @param array<string, mixed> $data */
    private function createWorker(array $data, Profile $profile, User $actor, ?User $user, ?string $password): User
    {
        $user ??= new User;
        $user->fill(array_intersect_key($data, array_flip(['first_name', 'last_name', 'dni', 'phone', 'email', 'username'])));
        $user->name = $data['first_name'].' '.$data['last_name'];
        if (! $user->exists) {
            abort_unless($password, 409, 'La solicitud no tiene una credencial válida.');
            $user->password = $password;
            $user->is_active = false;
            $user->account_type = $profile->code === 'DOCTOR' ? User::ACCOUNT_TYPE_DOCTOR : User::ACCOUNT_TYPE_USER;
        }
        $user->save();
        if ($profile->code === 'DOCTOR') {
            $specialty = Specialty::whereKey($data['specialty_id'])->lockForUpdate()->firstOrFail();
            abort_unless($specialty->is_active, 409, 'La especialidad está inactiva.');
            $doctor = $user->doctorProfile()->firstOrNew();
            $doctor->fill(['dni' => $data['dni'], 'phone' => $data['phone'], 'specialty_id' => $specialty->id, 'specialty' => mb_substr($specialty->name, 0, 100), 'license_number' => $data['license_number']]);
            $doctor->approval_status = DoctorProfile::STATUS_APPROVED;
            $doctor->save();
        }
        app(UserService::class)->assignProfile($user, $profile, $actor);
        app(UserService::class)->activate($user, $actor, true);

        return $user;
    }

    /** @param array<string, mixed> $before */
    private function audit(WorkerApplication $application, ?User $actor, string $action, array $before): void
    {
        AuditLog::create([
            'user_id' => $actor?->id, 'action' => $action, 'subject_type' => WorkerApplication::class,
            'subject_id' => $application->id, 'before_data' => $before,
            'after_data' => $application->only(['status', 'requested_profile_id', 'assigned_profile_id', 'user_id', 'review_notes']),
        ]);
    }
}
