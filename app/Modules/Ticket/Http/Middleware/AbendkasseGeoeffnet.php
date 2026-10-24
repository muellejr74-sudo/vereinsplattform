<?php

namespace App\Modules\Ticket\Http\Middleware;

use App\Modules\Kern\Models\Vorstellung;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Die Abendkasse einer Vorstellung ist nur in ihrem Zeitfenster geöffnet (E100):
 * ab 3 Stunden vor Einlass bis 3 Stunden nach dem Ende – wie die Stationen.
 */
class AbendkasseGeoeffnet
{
    public function handle(Request $request, Closure $next): Response
    {
        $vorstellung = $request->route('vorstellung');
        if ($vorstellung instanceof Vorstellung && ! $vorstellung->istImZeitfenster()) {
            $text = now()->lt($vorstellung->fensterBeginn())
                ? 'Die Abendkasse für '.$vorstellung->anzeige().' öffnet am '.$vorstellung->fensterBeginn()->format('d.m.Y').' um '.$vorstellung->fensterBeginn()->format('H:i').' Uhr.'
                : 'Die Abendkasse für '.$vorstellung->anzeige().' ist geschlossen.';

            return redirect()->route('tickets.abendkasse.index')->withErrors(['kasse' => $text]);
        }

        return $next($request);
    }
}
