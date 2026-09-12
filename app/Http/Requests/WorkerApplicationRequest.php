<?php

namespace App\Http\Requests;

use App\Http\Requests\Auth\RegistrationRequest;
use App\Models\Profile;
use App\Models\User;
use App\Models\WorkerApplication;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class WorkerApplicationRequest extends RegistrationRequest
{
    public function authorize(): bool
    {
        return ! $this->routeIs('admin.users.store') || ($this->user()?->hasPermission('usuarios.editar') ?? false);
    }

    public function prepareForValidation(): void
    {
        parent::prepareForValidation();
        if ($this->routeIs('register.doctor.store')) {
            $this->merge(['requested_profile_id' => Profile::where('code', 'DOCTOR')->value('id')]);
        }
        $this->merge(['email' => mb_strtolower(trim((string) $this->input('email'))), 'username' => trim((string) $this->input('username'))]);
    }

    public function rules(): array
    {
        $internal = $this->routeIs('admin.users.store');
        $codes = WorkerApplication::PROFILES;
        if ($internal && $this->user()->hasPermission('usuarios.cambiar_permisos')) {
            $codes[] = 'ADMINISTRADOR';
        }
        $profileField = $internal ? 'profile_id' : 'requested_profile_id';
        $profile = Profile::find($this->integer($profileField));

        return [
            ...self::identityRules($profile?->code === 'DOCTOR'),
            $profileField => ['required', 'integer', Rule::exists('profiles', 'id')->whereIn('code', $codes)],
            'password' => ['required', 'string', 'max:72', Password::min(8), 'confirmed'],
        ];
    }

    /** @return array<string, array<int, mixed>> */
    public static function identityRules(bool $doctor, ?User $user = null, ?WorkerApplication $application = null): array
    {
        $doctorId = $user?->doctorProfile?->id;

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'dni' => ['required', 'string', 'regex:/^[0-9]{8}$/', Rule::unique('users', 'dni')->ignore($user?->id), Rule::unique('doctor_profiles', 'dni')->ignore($doctorId), Rule::unique('owners', 'dni')->ignore($user?->owner?->id), Rule::unique('worker_applications', 'dni')->ignore($application?->id)],
            'phone' => ['required', 'string', 'regex:/^[0-9]{1,10}$/', 'max:10'],
            'email' => ['required', 'string', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user?->id), Rule::unique('worker_applications', 'email')->ignore($application?->id)],
            'username' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_.-]+$/', Rule::unique('users', 'username')->ignore($user?->id), Rule::unique('worker_applications', 'username')->ignore($application?->id)],
            'specialty_id' => [$doctor ? 'required' : 'exclude', 'integer', Rule::exists('specialties', 'id')->where('is_active', true)],
            'license_number' => [$doctor ? 'required' : 'exclude', 'string', 'regex:/^[0-9]+$/', 'max:5', Rule::unique('doctor_profiles', 'license_number')->ignore($doctorId), Rule::unique('worker_applications', 'license_number')->ignore($application?->id)],
        ];
    }

    public function attributes(): array
    {
        return [...parent::attributes(), 'requested_profile_id' => 'puesto solicitado', 'profile_id' => 'perfil operativo'];
    }

    public function messages(): array
    {
        return [...parent::messages(), 'requested_profile_id.exists' => 'Selecciona un puesto de trabajador disponible.', 'profile_id.exists' => 'No puedes asignar ese perfil.', 'license_number.max' => 'La colegiatura debe tener como máximo 5 dígitos.'];
    }
}
