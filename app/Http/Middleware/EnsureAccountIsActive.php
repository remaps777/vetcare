<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $reason = $request->user()?->accessDeniedReason();
        if ($reason !== null) {
            if ($request->expectsJson() || ! $request->hasSession()) {
                abort(403, $reason);
            }

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', $reason);
        }

        return $next($request);
    }
}
