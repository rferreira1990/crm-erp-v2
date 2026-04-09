<?php

namespace App\Http\Middleware;

use App\Support\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ResolveCurrentCompany
{
    public function __construct(private readonly CurrentCompany $currentCompany)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            $this->currentCompany->clear();

            return $next($request);
        }

        if (! $user->is_active) {
            $this->currentCompany->clear();
            Auth::logout();
            abort(403, 'A sua conta esta inativa.');
        }

        if ($user->isSuperAdmin()) {
            $this->currentCompany->clear();

            return $next($request);
        }

        if ($user->company_id === null) {
            $this->currentCompany->clear();
            abort(403, 'Utilizador sem empresa associada.');
        }

        $company = $user->company;

        if (! $company || ! $company->is_active) {
            $this->currentCompany->clear();
            Auth::logout();
            abort(403, 'A sua empresa esta inativa.');
        }

        $this->currentCompany->set($company);

        return $next($request);
    }
}
