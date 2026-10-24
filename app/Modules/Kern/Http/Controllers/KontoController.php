<?php

namespace App\Modules\Kern\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Kern\Dienste\Protokoll;
use App\Modules\Kern\Dienste\QrCode;
use App\Modules\Kern\Dienste\Totp;
use App\Modules\Kern\Dienste\ZweiFaktor;
use App\Modules\Kern\Models\VertrautesGeraet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\Rules\Password as PasswortRegel;
use Illuminate\View\View;

/**
 * "Mein Konto": Passwort ändern; für den Super-Admin außerdem die
 * Zwei-Faktor-Anmeldung einrichten und verwalten.
 */
class KontoController extends Controller
{
    private const SITZUNG_GEHEIMNIS = 'zweifaktor_einrichtung_geheimnis';

    public function anzeigen(Request $request, ZweiFaktor $zweiFaktor): View
    {
        $benutzer = $request->user();

        return view('kern::konto.anzeigen', [
            'benutzer' => $benutzer,
            'notfallcodes' => $benutzer->hatZweiFaktor() ? $zweiFaktor->anzahlNotfallcodes($benutzer) : null,
            'geraete' => VertrautesGeraet::where('user_id', $benutzer->id)->where('gueltig_bis', '>', now())->count(),
            'neueCodes' => $request->session()->get('neue_notfallcodes'),
        ]);
    }

    public function passwortAendern(Request $request): RedirectResponse
    {
        $request->validate([
            'aktuelles_passwort' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:aktuelles_passwort', PasswortRegel::defaults()],
        ], [], ['aktuelles_passwort' => 'aktuelles Passwort', 'password' => 'neues Passwort']);

        $request->user()->forceFill(['password' => $request->input('password')])->save();
        Protokoll::schreibe('passwort.geaendert', $request->user());

        return back()->with('hinweis', 'Ihr Passwort wurde geändert.');
    }

    // ------------------------------------------------------------------
    // Zwei-Faktor (nur Super-Admin)
    // ------------------------------------------------------------------

    public function zweiFaktorEinrichten(Request $request): View|RedirectResponse
    {
        $benutzer = $request->user();
        abort_unless($benutzer->istSuperAdmin(), 403);

        if ($benutzer->hatZweiFaktor()) {
            return redirect()->route('konto');
        }

        // Das neue Geheimnis bleibt bis zur Bestätigung nur in der (verschlüsselten) Sitzung.
        $geheimnis = $request->session()->get(self::SITZUNG_GEHEIMNIS) ?? Totp::neuesGeheimnis();
        $request->session()->put(self::SITZUNG_GEHEIMNIS, $geheimnis);

        $link = Totp::einrichtungsLink($geheimnis, $benutzer->email, config('app.name'));

        return view('kern::konto.zweifaktor-einrichten', [
            'qrCode' => QrCode::svg($link),
            'geheimnisLesbar' => trim(chunk_split($geheimnis, 4, ' ')),
        ]);
    }

    public function zweiFaktorBestaetigen(Request $request, ZweiFaktor $zweiFaktor): RedirectResponse
    {
        $benutzer = $request->user();
        abort_unless($benutzer->istSuperAdmin(), 403);

        if ($benutzer->hatZweiFaktor()) {
            return redirect()->route('konto');
        }

        $daten = $request->validate(['code' => ['required', 'string', 'max:10']], [], ['code' => 'Code']);
        $geheimnis = $request->session()->get(self::SITZUNG_GEHEIMNIS);

        $codes = $geheimnis ? $zweiFaktor->einrichten($benutzer, $geheimnis, $daten['code']) : null;

        if ($codes === null) {
            return back()->withErrors(['code' => 'Der Code passt nicht. Bitte prüfen Sie, ob die Uhrzeit Ihres Handys stimmt, und geben Sie den aktuellen Code ein.']);
        }

        $request->session()->forget(self::SITZUNG_GEHEIMNIS);
        Protokoll::schreibe('zweifaktor.eingerichtet', $benutzer);

        return redirect()->route('konto')
            ->with('neue_notfallcodes', $codes)
            ->with('hinweis', 'Die Zwei-Faktor-Anmeldung ist eingerichtet.');
    }

    public function notfallcodesErneuern(Request $request, ZweiFaktor $zweiFaktor): RedirectResponse
    {
        $benutzer = $request->user();
        abort_unless($benutzer->istSuperAdmin() && $benutzer->hatZweiFaktor(), 403);
        $request->validate(['aktuelles_passwort' => ['required', 'current_password']], [], ['aktuelles_passwort' => 'Passwort']);

        $codes = $zweiFaktor->erneuereNotfallcodes($benutzer);
        Protokoll::schreibe('zweifaktor.notfallcodes_neu', $benutzer);

        return redirect()->route('konto')->with('neue_notfallcodes', $codes)
            ->with('hinweis', 'Neue Notfall-Codes wurden erzeugt. Die bisherigen gelten nicht mehr.');
    }

    public function geraeteAbmelden(Request $request, ZweiFaktor $zweiFaktor): RedirectResponse
    {
        $benutzer = $request->user();
        abort_unless($benutzer->istSuperAdmin(), 403);

        $anzahl = $zweiFaktor->alleGeraeteAbmelden($benutzer);
        Protokoll::schreibe('zweifaktor.geraete_abgemeldet', $benutzer, ['anzahl' => $anzahl]);

        return redirect()->route('konto')
            ->withCookie(Cookie::forget(ZweiFaktor::GERAET_COOKIE))
            ->with('hinweis', 'Alle gemerkten Geräte wurden abgemeldet. Bei der nächsten Anmeldung wird überall wieder der Code abgefragt.');
    }
}
