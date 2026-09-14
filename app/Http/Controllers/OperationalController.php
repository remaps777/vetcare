<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Breed;
use App\Models\Consultation;
use App\Models\DoctorProfile;
use App\Models\Owner;
use App\Models\Pet;
use App\Models\ServiceOrder;
use App\Models\Species;
use App\Models\User;
use App\Services\ConfirmedMutation;
use App\Services\ModulePage;
use App\Services\ServiceOrderService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OperationalController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $role = strtolower($request->user()->effectiveRole());
        $module = (string) $request->route('module');

        if ($role === 'owner') {
            if ($module === 'pets' && ($request->bearerToken() || $request->expectsJson())) {
                $ownerId = $request->user()->owner?->id ?? 0;
                $pets = Pet::with(['species:id,name', 'breed:id,species_id,name'])
                    ->where('owner_id', $ownerId)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->paginate(15);

                return response()->json([
                    'data' => $pets->items(),
                    'meta' => [
                        'current_page' => $pets->currentPage(),
                        'last_page' => $pets->lastPage(),
                        'per_page' => $pets->perPage(),
                        'total' => $pets->total(),
                    ],
                ]);
            }

            return $this->ownerModule($request, $module);
        }

        abort_unless(in_array($module, ['owners', 'pets', 'appointments', 'consultations'], true)
            || ($role === 'admin' && $module === 'users'), 404);

        $configuration = match ($module) {
            'owners' => [
                'title' => 'Propietarios',
                'description' => 'Consulta los propietarios registrados y sus datos de contacto.',
                'records' => Owner::with('user')->orderBy('last_name')->orderBy('first_name')->paginate(15),
                'columns' => ['dni' => 'DNI', 'full_name' => 'Nombre completo', 'phone' => 'Teléfono', 'email' => 'Correo', 'is_active' => 'Activo'],
            ],
            'pets' => [
                'title' => 'Mascotas',
                'description' => 'Consulta pacientes, propietarios y estado de sus registros.',
                'records' => Pet::with('owner', 'species', 'breed')->orderBy('name')->paginate(15),
                'columns' => ['name' => 'Mascota', 'owner.full_name' => 'Propietario', 'species.name' => 'Especie', 'breed.name' => 'Raza', 'is_active' => 'Activo'],
            ],
            'appointments' => [
                'title' => 'Citas',
                'description' => 'Agenda clínica y estado de las citas registradas.',
                'records' => ($role === 'doctor'
                    ? Appointment::where('doctor_id', $request->user()->doctorProfile?->id ?? 0)
                    : Appointment::query())->with('pet.owner', 'doctor.user', 'serviceOrder')->latest('scheduled_at')->paginate(15),
                'columns' => ['scheduled_at' => 'Fecha', 'pet.name' => 'Mascota', 'pet.owner.full_name' => 'Propietario', 'doctor.user.name' => 'Doctor', 'reason' => 'Motivo', 'status' => 'Estado'],
            ],
            'consultations' => [
                'title' => 'Consultas',
                'description' => 'Historial de consultas y diagnósticos registrados.',
                'records' => Consultation::with('pet.owner', 'doctor.user')->latest('consulted_at')->paginate(15),
                'columns' => ['consulted_at' => 'Fecha', 'pet.name' => 'Mascota', 'pet.owner.full_name' => 'Propietario', 'doctor.user.name' => 'Doctor', 'reason' => 'Motivo', 'diagnosis' => 'Diagnóstico'],
            ],
            default => [
                'title' => 'Usuarios',
                'description' => 'Usuarios del sistema y rol asignado.',
                'records' => User::with('owner', 'doctorProfile')->orderBy('name')->paginate(15),
                'columns' => ['name' => 'Nombre', 'username' => 'Usuario', 'email' => 'Correo', 'role' => 'Rol', 'is_active' => 'Activo'],
            ],
        };

        $formFields = match ($module) {
            'appointments' => $this->appointmentFormFields($request, $role),
            'consultations' => $this->consultationFormFields($request, $role),
            default => [],
        };
        $canEdit = in_array($module, ['appointments', 'consultations'], true)
            && $request->user()->hasPermission($module.'.editar');
        $canDelete = in_array($module, ['appointments', 'consultations'], true)
            && $request->user()->hasPermission($module.'.eliminar');

        return ModulePage::render(
            $configuration['title'],
            $configuration['records'],
            [],
            $configuration['columns'],
            $role.'.'.$module,
            canCreate: ($module === 'appointments' && ($role === 'doctor' || $request->user()->hasPermission('citas.crear')))
                || ($module === 'consultations' && ($role === 'doctor' || $request->user()->hasPermission('consultas.crear'))),
            canEdit: $canEdit,
            canDelete: $canDelete,
            sensitive: false,
            description: $configuration['description'],
            formFields: $formFields,
            canUpdateStatus: $module === 'appointments' && $role === 'doctor',
            canAttendOrder: $module === 'appointments' && $request->user()->hasPermission('ordenes_atencion.crear'),
            canEmergencyOrder: $module === 'appointments' && $request->user()->hasPermission('ordenes_atencion.crear'),
            emergencySpecies: $module === 'appointments'
                ? Species::where('is_active', true)->orderBy('name')->pluck('name', 'id')
                : collect(),
            serviceOrder: $request->session()->get('service_order_id')
                ? ServiceOrder::with(['owner', 'pet', 'doctor.user', 'appointment'])->find($request->session()->get('service_order_id'))
                : null,
        );
    }

    public function storeAppointment(Request $request, ConfirmedMutation $mutation): JsonResponse
    {
        abort_unless($request->user()->isDoctor() || $request->user()->hasPermission('citas.crear'), 403);
        $data = $request->validate([
            'owner_id' => ['required', 'integer', 'exists:owners,id'],
            'pet_id' => ['required', 'integer', 'exists:pets,id'],
            'doctor_id' => ['required', 'integer', 'exists:doctor_profiles,id'],
            'scheduled_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:now'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
            'observations' => ['nullable', 'string', 'max:2000'],
            'status' => ['prohibited'],
        ]);

        $doctorId = $request->user()->isDoctor()
            ? $request->user()->doctorProfile?->id
            : $data['doctor_id'];
        abort_unless($doctorId, 403);
        $data['doctor_id'] = $doctorId;
        $pet = Pet::whereKey($data['pet_id'])->where('owner_id', $data['owner_id'])->where('is_active', true)->firstOrFail();
        $owner = Owner::with('user')->findOrFail($data['owner_id']);
        $doctor = DoctorProfile::whereKey($data['doctor_id'])->where('approval_status', DoctorProfile::STATUS_APPROVED)
            ->whereHas('user', fn ($query) => $query->where('is_active', true))->firstOrFail();
        $data['status'] = Appointment::STATUS_PENDING;

        return DB::transaction(fn (): JsonResponse => $mutation->handle(
            $request,
            null,
            $data,
            fn ($locked, $values): Appointment => Appointment::create($values),
            ['Propietario' => $owner->user->username, 'Mascota' => $pet->name, 'Doctor' => $doctor->user->name, 'Fecha' => $data['scheduled_at'], 'Motivo' => $data['reason']],
        ));
    }

    public function updateAppointmentStatus(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isDoctor(), 403);
        $doctorId = $request->user()->doctorProfile?->id;
        $appointment = Appointment::whereKey($request->route('record'))->where('doctor_id', $doctorId)->firstOrFail();
        $data = $request->validate(['status' => ['required', Rule::in(Appointment::STATUSES)]]);
        $appointment->update(['status' => $data['status']]);

        return back()->with('success', 'El estado de la cita fue actualizado.');
    }

    public function updateAppointment(Request $request, ConfirmedMutation $mutation): JsonResponse
    {
        abort_unless($request->user()->hasPermission('citas.editar'), 403);
        $appointment = Appointment::findOrFail($request->route('record'));
        $data = $request->validate([
            'owner_id' => ['required', 'integer', 'exists:owners,id'],
            'pet_id' => ['required', 'integer', 'exists:pets,id'],
            'doctor_id' => ['required', 'integer', 'exists:doctor_profiles,id'],
            'scheduled_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
            'observations' => ['nullable', 'string', 'max:2000'],
            'status' => ['prohibited'],
        ]);
        $pet = Pet::whereKey($data['pet_id'])->where('owner_id', $data['owner_id'])->where('is_active', true)->firstOrFail();
        $doctor = DoctorProfile::whereKey($data['doctor_id'])->where('approval_status', DoctorProfile::STATUS_APPROVED)
            ->whereHas('user', fn ($query) => $query->where('is_active', true))->firstOrFail();
        unset($data['owner_id']);

        return $mutation->handle($request, $appointment, $data, fn ($locked, $values): Appointment => tap($locked, fn (Appointment $record) => $record->update($values)), [
            'Mascota' => $pet->name,
            'Doctor' => $doctor->user->name,
            'Fecha' => $data['scheduled_at'],
            'Motivo' => $data['reason'],
        ]);
    }

    public function destroyAppointment(Request $request, ConfirmedMutation $mutation): JsonResponse
    {
        abort_unless($request->user()->hasPermission('citas.eliminar'), 403);
        $appointment = Appointment::findOrFail($request->route('record'));
        abort_if($appointment->consultation()->exists() || $appointment->serviceOrder()->exists(), 409, 'No se puede eliminar una cita con atención relacionada.');

        return $mutation->handle($request, $appointment, ['id' => $appointment->id], function ($locked): Appointment {
            $locked->delete();

            return $locked;
        }, ['Acción' => 'Eliminar cita', 'ID' => $appointment->id]);
    }

    public function updateConsultation(Request $request, ConfirmedMutation $mutation): JsonResponse
    {
        abort_unless($request->user()->hasPermission('consultas.editar'), 403);
        $consultation = Consultation::findOrFail($request->route('record'));
        $data = $request->validate([
            'pet_id' => ['required', 'integer', 'exists:pets,id'],
            'consulted_at' => ['required', 'date_format:Y-m-d\TH:i', 'before_or_equal:now'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
            'diagnosis' => ['required', 'string', 'min:3', 'max:5000'],
            'treatment' => ['nullable', 'string', 'max:5000'],
            'weight' => ['nullable', 'numeric', 'between:0,9999.99'],
            'temperature' => ['nullable', 'numeric', 'between:0,99.9'],
            'observations' => ['nullable', 'string', 'max:5000'],
        ]);
        $pet = Pet::with('owner')->whereKey($data['pet_id'])->where('is_active', true)->firstOrFail();

        return $mutation->handle($request, $consultation, $data, fn ($locked, $values): Consultation => tap($locked, fn (Consultation $record) => $record->update($values)), [
            'Mascota' => $pet->name,
            'Propietario' => $pet->owner?->full_name ?? 'Sin propietario',
            'Fecha' => $data['consulted_at'],
        ]);
    }

    public function destroyConsultation(Request $request, ConfirmedMutation $mutation): JsonResponse
    {
        abort_unless($request->user()->hasPermission('consultas.eliminar'), 403);
        $consultation = Consultation::findOrFail($request->route('record'));

        return $mutation->handle($request, $consultation, ['id' => $consultation->id], function ($locked): Consultation {
            $locked->delete();

            return $locked;
        }, ['Acción' => 'Eliminar consulta', 'ID' => $consultation->id]);
    }

    public function attendAppointment(Request $request, Appointment $appointment, ServiceOrderService $orders): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('ordenes_atencion.crear'), 403);

        $order = $orders->getOrCreateForAppointment($appointment, $request->user());

        return back()->with('service_order_id', $order->id);
    }

    public function createEmergencyOrder(Request $request, ServiceOrderService $orders): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('ordenes_atencion.crear'), 403);
        $data = $request->validate([
            'dni' => ['required', 'string', 'max:30'],
            'first_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30'],
            'pet_name' => ['required', 'string', 'max:100'],
            'species_id' => ['required', 'integer', Rule::exists('species', 'id')->where('is_active', true)],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ]);
        $order = $orders->createEmergencyOrder($data, $request->user());

        return back()->with('service_order_id', $order->id);
    }

    public function storeConsultation(Request $request, ConfirmedMutation $mutation): JsonResponse
    {
        abort_unless($request->user()->isDoctor() || $request->user()->hasPermission('consultas.crear'), 403);
        $data = $request->validate([
            'pet_id' => ['required', 'integer', 'exists:pets,id'],
            'doctor_id' => ['nullable', 'integer', 'exists:doctor_profiles,id'],
            'consulted_at' => ['required', 'date_format:Y-m-d\TH:i', 'before_or_equal:now'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
            'diagnosis' => ['required', 'string', 'min:3', 'max:5000'],
            'treatment' => ['nullable', 'string', 'max:5000'],
            'weight' => ['nullable', 'numeric', 'between:0,9999.99'],
            'temperature' => ['nullable', 'numeric', 'between:0,99.9'],
            'observations' => ['nullable', 'string', 'max:5000'],
        ]);
        $doctorId = $request->user()->isDoctor()
            ? $request->user()->doctorProfile?->id
            : ($data['doctor_id'] ?? null);
        abort_unless($doctorId, 403);
        $pet = Pet::with('owner')->whereKey($data['pet_id'])->where('is_active', true)->firstOrFail();
        $data['doctor_id'] = $doctorId;

        return $mutation->handle($request, null, $data, fn ($locked, $values): Consultation => Consultation::create($values), [
            'Mascota' => $pet->name,
            'Propietario' => $pet->owner?->full_name ?? 'Sin propietario',
            'Fecha' => $data['consulted_at'],
            'Motivo' => $data['reason'],
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function appointmentFormFields(Request $request, string $role): array
    {
        $doctorQuery = DoctorProfile::with('user')
            ->where('approval_status', DoctorProfile::STATUS_APPROVED)
            ->whereHas('user', fn ($query) => $query->where('is_active', true));

        if ($role === 'doctor') {
            $doctorQuery->whereKey($request->user()->doctorProfile?->id ?? 0);
        }

        return [
            'owner_id' => [
                'label' => 'Buscar propietario',
                'required' => true,
                'type' => 'autocomplete',
                'options' => Owner::with('user')->where('is_active', true)->orderBy('last_name')->orderBy('first_name')->get()
                    ->mapWithKeys(fn (Owner $owner): array => [$owner->id => $owner->user->username.' · '.$owner->full_name])->all(),
                'help' => 'Escribe y selecciona un propietario registrado.',
            ],
            'pet_id' => [
                'label' => 'Mascota',
                'required' => true,
                'depends_on' => 'owner_id',
                'options' => Pet::where('is_active', true)->with('owner.user')->orderBy('name')->get()
                    ->mapWithKeys(fn (Pet $pet): array => [$pet->id => $pet->owner?->user?->username.' · '.$pet->name])->all(),
                'option_meta' => Pet::where('is_active', true)->pluck('owner_id', 'id')->all(),
            ],
            'doctor_id' => [
                'label' => 'Médico veterinario',
                'required' => true,
                'disabled' => $role === 'doctor',
                'default' => $role === 'doctor' ? $request->user()->doctorProfile?->id : '',
                'help' => $role === 'doctor' ? 'Se asigna automáticamente a tu cuenta.' : null,
                'options' => $doctorQuery->get()->mapWithKeys(fn (DoctorProfile $doctor): array => [$doctor->id => $doctor->user->name])->all(),
            ],
            'scheduled_at' => ['label' => 'Fecha y hora', 'required' => true, 'type' => 'datetime-local'],
            'reason' => ['label' => 'Motivo', 'required' => true],
            'observations' => ['label' => 'Observaciones', 'type' => 'textarea'],
        ];
    }

    private function consultationFormFields(Request $request, string $role): array
    {
        return [
            'pet_id' => [
                'label' => 'Buscar mascota y propietario',
                'required' => true,
                'type' => 'autocomplete',
                'options' => Pet::with('owner.user')->where('is_active', true)->orderBy('name')->get()
                    ->mapWithKeys(fn (Pet $pet): array => [$pet->id => $pet->owner?->full_name.' · '.$pet->name.' ('.$pet->owner?->user?->username.')'])->all(),
                'help' => 'Selecciona la mascota para mostrar su propietario.',
            ],
            'doctor_id' => [
                'label' => 'Médico veterinario',
                'required' => true,
                'disabled' => $role === 'doctor',
                'default' => $role === 'doctor' ? $request->user()->doctorProfile?->id : '',
                'options' => DoctorProfile::with('user')
                    ->where('approval_status', DoctorProfile::STATUS_APPROVED)
                    ->whereHas('user', fn ($query) => $query->where('is_active', true))
                    ->get()->mapWithKeys(fn (DoctorProfile $doctor): array => [$doctor->id => $doctor->user->name])->all(),
            ],
            'consulted_at' => ['label' => 'Fecha y hora de atención', 'required' => true, 'type' => 'datetime-local', 'default' => now()->format('Y-m-d\TH:i')],
            'reason' => ['label' => 'Motivo de consulta', 'required' => true],
            'diagnosis' => ['label' => 'Diagnóstico', 'required' => true, 'type' => 'textarea'],
            'treatment' => ['label' => 'Tratamiento', 'type' => 'textarea'],
            'weight' => ['label' => 'Peso (kg)', 'type' => 'number', 'step' => '0.01'],
            'temperature' => ['label' => 'Temperatura (°C)', 'type' => 'number', 'step' => '0.1'],
            'observations' => ['label' => 'Observaciones', 'type' => 'textarea'],
        ];
    }

    public function savePet(Request $request, ConfirmedMutation $mutation): JsonResponse
    {
        abort_unless($request->user()->isOwner(), 403);
        $ownerId = $request->user()->owner?->id ?? 0;
        $record = $request->route('record')
            ? Pet::whereKey($request->route('record'))->where('owner_id', $ownerId)->firstOrFail()
            : null;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'species_id' => ['required', 'integer', Rule::exists('species', 'id')->where('is_active', true)],
            'breed_id' => ['required', 'integer', Rule::exists('breeds', 'id')->where('is_active', true)],
            'sex' => ['required', Rule::in(Pet::SEXES)],
            'age_years' => ['nullable', 'integer', 'between:0,50'],
            'weight' => ['nullable', 'numeric', 'between:0,9999.99'],
            'color' => ['nullable', 'string', 'max:50'],
            'observations' => ['nullable', 'string', 'max:2000'],
            'owner_id' => ['prohibited'],
        ]);
        $breed = Breed::whereKey($data['breed_id'])->where('species_id', $data['species_id'])->where('is_active', true)->firstOrFail();
        if (filled($data['age_years'] ?? null)) {
            $data['birth_date'] = now()->subYears((int) $data['age_years'])->startOfMonth()->toDateString();
        }
        unset($data['age_years']);

        return $mutation->handle($request, $record, $data, function ($locked, $values) use ($ownerId): Pet {
            $pet = $locked ?? new Pet;
            $pet->owner_id = $ownerId;
            $pet->fill($values)->save();

            return $pet;
        }, ['Mascota' => $data['name'], 'Especie' => Species::findOrFail($data['species_id'])->name, 'Raza' => $breed->name]);
    }

    public function destroyPet(Request $request, ConfirmedMutation $mutation): JsonResponse
    {
        abort_unless($request->user()->isOwner(), 403);
        $ownerId = $request->user()->owner?->id ?? 0;
        $record = Pet::whereKey($request->route('record'))->where('owner_id', $ownerId)->firstOrFail();
        abort_if($record->appointments()->exists() || $record->consultations()->exists(), 409, 'No puedes eliminar una mascota con citas o consultas relacionadas. Puedes desactivarla desde editar.');

        return $mutation->handle($request, $record, ['id' => $record->id], function ($locked): Pet {
            $locked->delete();

            return $locked;
        }, ['Mascota' => $record->name, 'Acción' => 'Eliminar']);
    }

    private function ownerModule(Request $request, string $module): View
    {
        $ownerId = $request->user()->owner?->id ?? 0;

        if ($module === 'pets') {
            return ModulePage::render('Mis mascotas', Pet::with('species', 'breed')->where('owner_id', $ownerId)->orderBy('name')->paginate(15), [], [
                'name' => 'Mascota',
                'species.name' => 'Especie',
                'breed.name' => 'Raza',
                'sex' => 'Sexo',
            ], 'owner.pets', canCreate: true, canEdit: true, sensitive: false, description: 'Administra únicamente las mascotas asociadas a tu cuenta.', formFields: [
                'name' => ['label' => 'Nombre', 'required' => true],
                'species_id' => ['label' => 'Especie', 'required' => true, 'options' => Species::where('is_active', true)->orderBy('name')->pluck('name', 'id')->all()],
                'breed_id' => [
                    'label' => 'Raza',
                    'required' => true,
                    'depends_on' => 'species_id',
                    'options' => Breed::where('is_active', true)->with('species')->orderBy('name')->get()->mapWithKeys(fn ($breed) => [$breed->id => $breed->name])->all(),
                    'option_meta' => Breed::where('is_active', true)->pluck('species_id', 'id')->all(),
                ],
                'sex' => ['label' => 'Sexo', 'required' => true, 'options' => [Pet::SEX_MACHO => 'Macho', Pet::SEX_HEMBRA => 'Hembra']],
                'age_years' => ['label' => 'Edad (años)', 'type' => 'number', 'min' => 0, 'max' => 50, 'step' => '1', 'help' => 'Indica la edad aproximada de la mascota.'],
                'weight' => ['label' => 'Peso (kg)', 'type' => 'number', 'step' => '0.01'],
                'color' => ['label' => 'Color'],
                'observations' => ['label' => 'Observaciones', 'type' => 'textarea'],
            ], canDelete: true);
        }

        if ($module === 'appointments') {
            return ModulePage::render('Mis citas', Appointment::with('pet', 'doctor.user')
                ->whereHas('pet', fn ($query) => $query->where('owner_id', $ownerId))
                ->latest('scheduled_at')->paginate(15), [], [
                    'scheduled_at' => 'Fecha',
                    'pet.name' => 'Mascota',
                    'doctor.user.name' => 'Doctor',
                    'reason' => 'Motivo',
                    'status' => 'Estado',
                ], 'owner.appointments', canCreate: false, canEdit: false, sensitive: false, description: 'Consulta las citas de tus mascotas. La agenda es gestionada por el administrador o el médico veterinario.');
        }

        abort_unless($module === 'history', 404);

        return ModulePage::render('Historial clínico', Consultation::with('pet', 'doctor.user')
            ->whereHas('pet', fn ($query) => $query->where('owner_id', $ownerId))
            ->latest('consulted_at')->paginate(15), [], [
                'consulted_at' => 'Fecha',
                'pet.name' => 'Mascota',
                'doctor.user.name' => 'Doctor',
                'reason' => 'Motivo',
                'diagnosis' => 'Diagnóstico',
            ], 'owner.history', canCreate: false, canEdit: false, sensitive: false, description: 'Consultas de tus mascotas.');
    }
}
