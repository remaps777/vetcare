<?php

namespace App\Http\Middleware;

use App\Services\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function __construct(private PermissionService $permissions) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        abort_unless($request->user() && $this->permissions->has($request->user(), $permission), 403, 'No tienes permiso para realizar esta acción.');

        return $next($request);
    }
}
