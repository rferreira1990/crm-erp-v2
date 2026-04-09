<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SuperAdminOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isSuperAdmin()) {
            abort(403, 'Area reservada a superadmins.');
        }

        if (! $user->is_active) {
            Auth::logout();
            abort(403, 'A sua conta esta inativa.');
        }

        return $next($request);
    }
}
