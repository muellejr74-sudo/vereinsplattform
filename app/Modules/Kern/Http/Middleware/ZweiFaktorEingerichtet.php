<?php

namespace App\Modules\Kern\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Der Super-Admin muss die Zwei-Faktor-Anmeldung eingerichtet haben (E16a),
 * bevor er irgendetwas anderes tun kann.
 */
class ZweiFaktorEingerichtet
{
    public function handle(Request $request, Closure $next): Response
    {
        $benutzer = $request->user();

        if ($benutzer?->istSuperAdmin() && ! $benutzer->hatZweiFaktor()) {
            return redirect()->route('zweifaktor.einrichten');
        }

        return $next($request);
    }
}
