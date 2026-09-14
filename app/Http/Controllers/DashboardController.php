<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\DoctorProfile;
use App\Models\Owner;
use App\Models\Pet;
use App\Models\Profile;
use App\Models\Specialty;
use App\Models\User;
use App\Models\WorkerApplication;
use App\Services\RecordVersion;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function admin(Request $request): View
    {
        $canReviewWorkerApplications = $request->user()->effectiveRole() === User::ROLE_ADMIN
            || $request->user()->hasPermission('solicitudes_trabajador.ver');
        $stats = DB::query()
            ->selectSub(Owner::query()->selectRaw('COUNT(*)'), 'owners_count')
            ->selectSub(
                DoctorProfile::query()
                    ->whereHas('user', fn ($query) => $query->where('role', User::ROLE_DOCTOR))
                    ->selectRaw('COUNT(*)'),
                'doctors_count'
            )
            ->selectSub(Pet::query()->selectRaw('COUNT(*)'), 'pets_count')
            ->selectSub(
                Appointment::query()
                    ->whereIn('status', [Appointment::STATUS_PENDING, Appointment::STATUS_CONFIRMED])
                    ->where('scheduled_at', '>=', now())
                    ->selectRaw('COUNT(*)'),
                'upcoming_appointments_count'
            )
            ->first();

        return view('dashboards.admin', [
            'stats' => [
                'Propietarios' => (int) $stats->owners_count,
                'Doctores' => (int) $stats->doctors_count,
                'Mascotas' => (int) $stats->pets_count,
                'Citas próximas' => (int) $stats->upcoming_appointments_count,
            ],
            'applicationCounts' => $canReviewWorkerApplications ? WorkerApplication::selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status') : collect(),
            'currentApplications' => $canReviewWorkerApplications
                ? WorkerApplication::with(['requestedProfile', 'assignedProfile', 'specialty'])->whereIn('status', ['PENDING', 'ON_HOLD'])->latest('id')->get()
                : collect(),
            'applicationHistory' => $canReviewWorkerApplications
                ? $this->applicationHistory()
                : collect(),
            'workerProfiles' => $canReviewWorkerApplications
                ? Profile::whereIn('code', WorkerApplication::PROFILES)->orderBy('name')->get()
                : collect(),
            'workerSpecialties' => $canReviewWorkerApplications
                ? Specialty::where('is_active', true)->orderBy('name')->get()
                : collect(),
        ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function applicationHistory(): Collection
    {
        return WorkerApplication::query()
            ->with(['requestedProfile', 'history.user'])
            ->latest('id')
            ->get()
            ->flatMap(function (WorkerApplication $application): Collection {
                return $application->history->map(fn ($entry): array => [
                    'application_id' => $application->id,
                    'application_version' => RecordVersion::of($application),
                    'application_status' => $application->status,
                    'application_profile_id' => $application->assigned_profile_id ?? $application->requested_profile_id,
                    'applicant' => $application->first_name.' '.$application->last_name,
                    'username' => $application->username,
                    'profile' => $application->requestedProfile?->name ?? '—',
                    'status' => WorkerApplication::STATUSES[$entry->after_data['status'] ?? $application->status] ?? '—',
                    'date' => $entry->created_at?->format('d/m/Y H:i'),
                    'actor' => $entry->user?->name ?? ($entry->action === 'WORKER_APPLICATION_IMPORTED' ? 'Solicitud anterior' : 'Solicitante'),
                    'notes' => $entry->after_data['review_notes'] ?? '—',
                ]);
            })
            ->values();
    }

    public function doctor(Request $request): View
    {
        $user = $request->user()->load(['doctorProfile', 'profile']);
        $doctor = $user->doctorProfile;
        $appointments = $doctor->appointments()->whereIn('status', [Appointment::STATUS_PENDING, Appointment::STATUS_CONFIRMED]);

        return view('dashboards.doctor', [
            'stats' => [
                'Citas de hoy' => (clone $appointments)->whereDate('scheduled_at', today())->count(),
                'Próximas citas' => (clone $appointments)->where('scheduled_at', '>=', now())->count(),
                'Mascotas registradas' => Pet::count(),
                'Consultas recientes' => $doctor->consultations()->whereBetween('consulted_at', [now()->subDays(30), now()])->count(),
            ],
            'appointments' => (clone $appointments)->with('pet.owner', 'doctor.user')->where('scheduled_at', '>=', now())->orderBy('scheduled_at')->orderBy('id')->limit(5)->get(),
            'profileUser' => $user,
            'specialties' => Specialty::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function owner(Request $request): View
    {
        $user = $request->user()->load(['owner', 'profile']);
        $owner = $user->owner;
        $pets = Pet::where('owner_id', $owner?->id ?? 0);
        $appointments = Appointment::whereHas('pet', fn ($query) => $query->where('owner_id', $owner?->id ?? 0))
            ->whereIn('status', [Appointment::STATUS_PENDING, Appointment::STATUS_CONFIRMED])->where('scheduled_at', '>=', now());
        $consultations = Consultation::whereHas('pet', fn ($query) => $query->where('owner_id', $owner?->id ?? 0));

        return view('dashboards.owner', [
            'stats' => ['Mis mascotas' => (clone $pets)->count(), 'Mis próximas citas' => (clone $appointments)->count(), 'Consultas en mi historial' => $consultations->count()],
            'pets' => $pets->with('species', 'breed')->orderBy('name')->orderBy('id')->limit(6)->get(),
            'appointments' => $appointments->with('pet', 'doctor.user')->orderBy('scheduled_at')->orderBy('id')->limit(5)->get(),
            'profileUser' => $user,
            'specialties' => collect(),
        ]);
    }
}
