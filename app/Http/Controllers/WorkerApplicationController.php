<?php

namespace App\Http\Controllers;

use App\Http\Requests\WorkerApplicationRequest;
use App\Models\Profile;
use App\Models\Specialty;
use App\Models\WorkerApplication;
use App\Services\WorkerApplicationService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkerApplicationController extends Controller
{
    public function create(): View
    {
        return view('auth.register-worker', $this->formData());
    }

    public function store(WorkerApplicationRequest $request, WorkerApplicationService $applications): RedirectResponse
    {
        $applications->submit($request->validated());

        return redirect()->route('login')->with('warning', 'Solicitud registrada. La clínica revisará tus datos antes de habilitar tu cuenta.');
    }

    public function index(Request $request): View
    {
        $data = $request->validate(['status' => ['nullable', Rule::in(array_keys(WorkerApplication::STATUSES))]]);
        $query = WorkerApplication::with(['requestedProfile', 'assignedProfile', 'specialty', 'reviewer', 'history.user']);
        if ($data['status'] ?? null) {
            $query->where('status', $data['status']);
        }

        return view('admin.worker-applications', [
            'applications' => $query->latest('id')->paginate(15)->withQueryString(),
            ...$this->formData($request),
        ]);
    }

    public function review(Request $request, WorkerApplication $application, WorkerApplicationService $applications): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reaccept', 'hold', 'reject', 'reconsider'])],
            '_version' => ['required', 'string'],
            'profile_id' => ['required_if:action,approve', 'nullable', 'integer', 'exists:profiles,id'],
            'review_notes' => ['nullable', 'string', 'max:2000'],
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'last_name' => ['sometimes', 'required', 'string', 'max:100'],
            'dni' => ['sometimes', 'required', 'string'],
            'phone' => ['sometimes', 'required', 'string'],
            'email' => ['sometimes', 'required', 'string'],
            'username' => ['sometimes', 'required', 'string'],
            'specialty_id' => ['sometimes', 'nullable', 'integer'],
            'license_number' => ['sometimes', 'nullable', 'string'],
        ]);
        $applications->review($application, $data['action'], $data, $request->user(), $data['_version']);

        return back()->with('success', 'Solicitud actualizada correctamente.');
    }

    public function createInternal(Request $request): View
    {
        return view('admin.users.create', $this->formData($request));
    }

    public function storeInternal(WorkerApplicationRequest $request, WorkerApplicationService $applications): RedirectResponse
    {
        $applications->createInternal($request->validated(), $request->user());

        return redirect()->route('admin.users')->with('success', 'Trabajador creado y activo. Ya puede iniciar sesión.');
    }

    /** @return array{profiles: Collection, specialties: Collection} */
    private function formData(?Request $request = null): array
    {
        $codes = WorkerApplication::PROFILES;
        if ($request?->user()?->hasPermission('usuarios.cambiar_permisos')) {
            $codes[] = 'ADMINISTRADOR';
        }

        return [
            'profiles' => Profile::whereIn('code', $codes)->orderBy('name')->get(),
            'specialties' => Specialty::where('is_active', true)->orderBy('name')->get(),
        ];
    }
}
