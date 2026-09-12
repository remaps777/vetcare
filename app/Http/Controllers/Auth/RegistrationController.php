<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegistrationRequest;
use App\Models\AuditLog;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class RegistrationController extends Controller
{
    public function store(RegistrationRequest $request): RedirectResponse
    {
        $data = $request->validated();
        DB::transaction(function () use ($data): void {
            $user = new User([
                'name' => $data['first_name'].' '.$data['last_name'],
                'first_name' => $data['first_name'], 'last_name' => $data['last_name'],
                'dni' => $data['dni'], 'phone' => $data['phone'],
                'username' => $data['username'], 'email' => $data['email'], 'password' => $data['password'],
            ]);
            $user->role = User::ROLE_OWNER;
            $user->account_type = User::ACCOUNT_TYPE_USER;
            $user->profile_id = Profile::where('code', 'PROPIETARIO')->firstOrFail()->id;
            $user->is_active = true;
            $user->save();
            $user->owner()->create([
                'first_name' => $data['first_name'], 'last_name' => $data['last_name'], 'dni' => $data['dni'],
                'phone' => $data['phone'], 'email' => $data['email'], 'address' => $data['address'] ?? null, 'is_active' => true,
            ]);
            AuditLog::create(['user_id' => $user->id, 'action' => 'USER_ACTIVATED', 'subject_type' => User::class, 'subject_id' => $user->id, 'before_data' => [], 'after_data' => ['is_active' => true, 'profile_id' => $user->profile_id]]);
        });

        return redirect()->route('login')->with('success', 'Tu cuenta de propietario está lista. Ya puedes iniciar sesión.');
    }
}
