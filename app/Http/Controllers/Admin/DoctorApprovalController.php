<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DoctorProfile;
use App\Models\User;
use App\Models\WorkerApplication;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DoctorApprovalController extends Controller
{
    public function index(): View
    {
        return view('admin.doctors.pending', [
            'doctors' => DoctorProfile::with('user')
                ->where('approval_status', DoctorProfile::STATUS_PENDING)
                ->whereHas('user', fn ($query) => $query->where('role', User::ROLE_DOCTOR))
                ->orderBy('created_at')->orderBy('id')->paginate(15),
        ]);
    }

    public function approve(Request $request, DoctorProfile $doctor): RedirectResponse
    {
        $this->decide($request, $doctor, true);

        return redirect()->route('admin.doctors.pending')->with('success', 'Doctor aprobado. Su cuenta ya está activa.');
    }

    public function reject(Request $request, DoctorProfile $doctor): RedirectResponse
    {
        $this->decide($request, $doctor, false);

        return redirect()->route('admin.doctors.pending')->with('success', 'Solicitud rechazada. La cuenta permanece inactiva.');
    }

    private function decide(Request $request, DoctorProfile $doctor, bool $approve): void
    {
        abort_unless($request->user()->hasPermission($approve ? 'solicitudes_trabajador.aprobar' : 'solicitudes_trabajador.rechazar'), 403);
        abort_if(WorkerApplication::where('user_id', $doctor->user_id)->exists(), 409, 'Revisa esta solicitud desde Solicitudes de trabajadores para conservar su historial.');
        DB::transaction(function () use ($request, $doctor, $approve): void {
            $profile = DoctorProfile::whereKey($doctor->id)->lockForUpdate()->firstOrFail();
            abort_if($profile->user_id === $request->user()->id, 403);
            abort_unless($profile->user?->isDoctor(), 404);
            abort_unless($profile->isPending(), 409, 'Esta solicitud ya fue revisada.');

            if ($approve) {
                $profile->approve();
            } else {
                $profile->reject();
            }
        });
    }
}
