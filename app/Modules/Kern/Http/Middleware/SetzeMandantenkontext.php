<?php

namespace App\Modules\Kern\Http\Middleware;

use App\Modules\Kern\Mandanten\Mandantenkontext;
use App\Modules\Kern\Models\Mandant;
use App\Modules\Kern\Rechte\Berechtigung;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Setzt bei jeder Anfrage den aktiven Verein aus der Sitzung – aber nur, wenn
 * die angemeldete Person dort tatsächlich arbeiten darf (Prüfung auf dem
 * Server bei JEDER Anfrage, E19a). Deaktivierte Konten werden sofort abgemeldet.
 */
class SetzeMandantenkontext
{
    public const SITZUNG = 'mandant_id';

    public function __construct(private Mandantenkontext $kontext, private Berechtigung $berechtigung) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->kontext->leeren();
        $this->berechtigung->vergessen();
        $benutzer = Auth::user();

        if ($benutzer && ! $benutzer->aktiv) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('anmeldung')->withErrors(['email' => 'Ihr Konto ist deaktiviert.']);
        }

        if ($benutzer && $request->hasSession()) {
            $id = $request->session()->get(self::SITZUNG);
            $mandant = $id ? Mandant::find($id) : null;

            if ($mandant && $benutzer->darfInVerein($mandant)) {
                $this->kontext->setze($mandant);
            } elseif ($id) {
                $request->session()->forget(self::SITZUNG);
            }
        }

        return $next($request);
    }
}
