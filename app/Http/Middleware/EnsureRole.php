<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        abort_unless(in_array($request->user()?->effectiveRole(), $roles, true), 403, 'No tienes permiso para acceder a esta sección.');

        return $next($request);
    }
}
