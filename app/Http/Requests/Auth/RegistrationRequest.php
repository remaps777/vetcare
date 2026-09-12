<?php

namespace App\Http\Requests\Auth;

use App\Models\Specialty;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function prepareForValidation(): void
    {
        if ($this->routeIs('register.doctor.store') && $this->filled('specialty') && ! $this->filled('specialty_id')) {
            $specialty = Specialty::query()->where('name', trim((string) $this->input('specialty')))->where('is_active', true)->first();

            if ($specialty) {
                $this->merge(['specialty_id' => $specialty->id]);
            }
        }
    }

    public function rules(): array
    {
        $doctor = $this->routeIs('register.doctor.store');

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'dni' => ['required', 'string', 'regex:/^[0-9]{8}$/', 'unique:'.($doctor ? 'doctor_profiles' : 'owners').',dni', 'unique:users,dni', 'unique:worker_applications,dni'],
            'phone' => ['required', 'string', 'regex:/^[0-9]{1,10}$/', 'max:10'],
            'email' => ['required', 'string', 'email', 'max:150', 'unique:users,email', 'unique:worker_applications,email'],
            'username' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_.-]+$/', 'unique:users,username', 'unique:worker_applications,username'],
            'password' => ['required', 'string', 'max:72', Password::min(8), 'confirmed'],
            'address' => [$doctor ? 'exclude' : 'nullable', 'string', 'max:255'],
            'license_number' => [$doctor ? 'required' : 'exclude', 'string', 'regex:/^[0-9]{1,20}$/', 'max:20', 'unique:doctor_profiles,license_number'],
            'specialty_id' => [$doctor ? 'required' : 'exclude', 'integer', Rule::exists('specialties', 'id')->where(fn ($query) => $query->where('is_active', true))],
            'specialty' => [$doctor ? 'nullable' : 'exclude', 'string', 'max:150'],
        ];
    }

    public function attributes(): array
    {
        return ['first_name' => 'nombres', 'last_name' => 'apellidos', 'dni' => 'DNI', 'phone' => 'teléfono', 'email' => 'correo electrónico', 'username' => 'usuario', 'password' => 'contraseña', 'license_number' => 'colegiatura', 'specialty_id' => 'especialidad', 'specialty' => 'especialidad', 'address' => 'dirección'];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser texto.',
            'max' => 'El campo :attribute no debe superar :max caracteres.',
            'unique' => 'El campo :attribute ya está registrado.',
            'email.email' => 'Ingresa un correo electrónico válido.',
            'dni.regex' => 'El DNI debe tener 8 dígitos.',
            'phone.regex' => 'El teléfono solo puede contener números.',
            'license_number.regex' => 'La colegiatura solo puede contener números.',
            'username.regex' => 'El usuario solo admite letras, números, puntos, guiones y guiones bajos.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'specialty_id.required' => 'Selecciona una especialidad.',
            'specialty_id.exists' => 'La especialidad seleccionada no está disponible.',
        ];
    }
}
