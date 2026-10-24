<?php

namespace App\Modules\Kern\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Bereich nur für den Super-Administrator. */
class NurSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->istSuperAdmin(), 403, 'Dieser Bereich ist dem Super-Administrator vorbehalten.');

        return $next($request);
    }
}
