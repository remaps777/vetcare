<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SessionController extends Controller
{
    public function store(LoginRequest $request): RedirectResponse
    {
        $login = $request->validated('login');
        $key = 'login:'.hash('sha256', Str::lower($login).'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['login' => 'Demasiados intentos. Inténtalo nuevamente en '.RateLimiter::availableIn($key).' segundos.']);
        }

        $field = str_contains($login, '@') ? 'email' : 'username';
        $reason = null;
        $authenticated = Auth::guard('web')->attemptWhen(
            [$field => $login, 'password' => $request->validated('password')],
            function (User $user) use (&$reason): bool {
                $reason = $user->accessDeniedReason();

                return $reason === null;
            },
            $request->boolean('remember'),
        );

        if (! $authenticated) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['login' => $reason ?? 'Las credenciales no son correctas.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->session()->forget('url.intended');

        return redirect()->route($request->user()->dashboardRoute());
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Sesión cerrada correctamente.');
    }
}
