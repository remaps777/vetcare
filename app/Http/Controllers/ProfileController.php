<?php

namespace App\Http\Controllers;

use App\Models\Specialty;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user()->load(['owner', 'doctorProfile.specialty', 'profile']);

        return view('profile.show', [
            'user' => $user,
            'specialties' => Specialty::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user()->load(['owner', 'doctorProfile']);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'specialty_id' => [
                'nullable',
                'integer',
                Rule::exists('specialties', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
        ]);

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
        ]);

        if ($user->owner) {
            $firstName = $data['first_name'] ?? $user->owner->first_name;
            $lastName = $data['last_name'] ?? $user->owner->last_name;
            $user->owner->update([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'],
                'address' => $data['address'] ?? null,
            ]);
            $user->update(['name' => trim($firstName.' '.$lastName)]);
        }

        if ($user->doctorProfile) {
            $user->doctorProfile->update([
                'phone' => $data['phone'] ?? null,
                'specialty_id' => $data['specialty_id'] ?? null,
            ]);
        }

        return back()->with('success', 'Tus datos fueron actualizados correctamente.');
    }
}
