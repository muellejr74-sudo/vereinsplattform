<?php

namespace App\Modules\Kern\Http\Middleware;

use App\Modules\Kern\Mandanten\Mandantenkontext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Seiten der Vereinsverwaltung brauchen einen aktiven Verein (Auswahl auf der Startseite). */
class VereinErforderlich
{
    public function __construct(private Mandantenkontext $kontext) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->kontext->istGesetzt()) {
            return redirect()->route('startseite')->with('hinweis', 'Bitte wählen Sie zuerst den Verein aus, in dem Sie arbeiten möchten.');
        }

        return $next($request);
    }
}
