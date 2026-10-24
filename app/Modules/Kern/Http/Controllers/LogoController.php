<?php

namespace App\Modules\Kern\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Kern\Models\Mandant;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Liefert das Vereinslogo aus. Die Datei liegt außerhalb des öffentlichen Ordners
 * (Architektur Abschnitt 13: Uploads nicht in ausführbaren Ordnern).
 */
class LogoController extends Controller
{
    public function anzeigen(string $kuerzel): Response
    {
        $verein = Mandant::where('kuerzel', mb_strtoupper($kuerzel))->where('aktiv', true)->firstOrFail();
        abort_unless($verein->hatLogo() && Storage::disk('local')->exists($verein->logo_datei), 404);

        return response(Storage::disk('local')->get($verein->logo_datei), 200, [
            'Content-Type' => $verein->logo_typ,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=604800',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }
}
