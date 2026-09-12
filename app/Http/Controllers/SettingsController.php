<?php

namespace App\Http\Controllers;

use App\Models\ClinicSetting;
use App\Services\ConfirmedMutation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function show(): View
    {
        $settings = ClinicSetting::firstOrCreate([], [
            'name' => 'VetCare',
            'appointment_minutes' => 30,
        ]);

        return view('admin.settings', compact('settings'));
    }

    public function update(Request $request, ConfirmedMutation $mutation): JsonResponse
    {
        $settings = ClinicSetting::firstOrFail();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'appointment_minutes' => ['required', 'integer', 'in:15,20,30,45,60'],
            'facebook_url' => ['nullable', 'url:https', 'max:255', 'regex:/^https:\/\/(www\.)?facebook\.com\//i'],
            'instagram_url' => ['nullable', 'url:https', 'max:255', 'regex:/^https:\/\/(www\.)?instagram\.com\//i'],
            'tiktok_url' => ['nullable', 'url:https', 'max:255', 'regex:/^https:\/\/(www\.)?tiktok\.com\//i'],
            'whatsapp_number' => ['nullable', 'string', 'regex:/^[1-9][0-9]{7,14}$/'],
            'current_password' => ['required', 'current_password:web'],
        ]);

        return $mutation->handle(
            $request,
            $settings,
            $data,
            function ($locked, $values): ClinicSetting {
                $locked->fill($values)->save();

                return $locked;
            },
            [
                'Nombre de la clínica' => $data['name'],
                'Teléfono' => $data['phone'] ?? '—',
                'Correo' => $data['email'] ?? '—',
                'Dirección' => $data['address'] ?? '—',
                'Duración de citas (min)' => $data['appointment_minutes'],
                'Facebook' => $data['facebook_url'] ?? 'No configurado',
                'Instagram' => $data['instagram_url'] ?? 'No configurado',
                'TikTok' => $data['tiktok_url'] ?? 'No configurado',
                'WhatsApp' => $data['whatsapp_number'] ?? 'No configurado',
            ],
            true, // sensitive — requires password + audit log
        );
    }
}
