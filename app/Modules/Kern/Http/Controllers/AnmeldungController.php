<?php

namespace App\Modules\Kern\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Kern\Dienste\Anmeldesperre;
use App\Modules\Kern\Dienste\Protokoll;
use App\Modules\Kern\Dienste\ZweiFaktor;
use App\Modules\Kern\Http\Middleware\SetzeMandantenkontext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Anmeldung mit E-Mail und Passwort (E15), beim Super-Admin zusätzlich mit
 * Zwei-Faktor-Code (E16a–E16d).
 */
class AnmeldungController extends Controller
{
    /** Wie lange der Zwischenschritt "Code eingeben" gültig bleibt. */
    private const ZWEIFAKTOR_MINUTEN = 10;

    public function formular(): View
    {
        return view('kern::anmeldung.formular');
    }

    public function anmelden(Request $request, ZweiFaktor $zweiFaktor): RedirectResponse
    {
        $daten = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ], [], ['email' => 'E-Mail-Adresse', 'password' => 'Passwort']);

        $email = mb_strtolower(trim($daten['email']));
        $sperre = new Anmeldesperre;

        if ($sekunden = $sperre->gesperrtFuer($email, $request->ip())) {
            Protokoll::schreibe('anmeldung.gesperrt', null, ['email' => $email]);

            return $this->zurueck('Zu viele Fehlversuche. Bitte in '.max(1, (int) ceil($sekunden / 60)).' Minuten erneut versuchen.');
        }

        $benutzer = User::where('email', $email)->first();

        // Auch ohne Konto wird ein Passwort geprüft, damit die Antwortzeit nichts verrät.
        $passwortStimmt = Hash::check($daten['password'], $benutzer?->password ?? Hash::make('kein-konto'));

        if (! $benutzer || ! $benutzer->aktiv || ! $passwortStimmt) {
            $sperre->fehlversuch($email, $request->ip());
            Protokoll::schreibe('anmeldung.fehlgeschlagen', null, ['email' => $email], $benutzer);

            // Immer dieselbe Meldung – verrät nicht, ob es das Konto gibt.
            return $this->zurueck('E-Mail-Adresse oder Passwort ist falsch.');
        }

        $sperre->erfolg($email, $request->ip());

        // Super-Admin mit eingerichteter Zwei-Faktor-Anmeldung auf fremdem Gerät: Code abfragen.
        if ($benutzer->istSuperAdmin() && $benutzer->hatZweiFaktor() && ! $zweiFaktor->istVertrautesGeraet($benutzer, $request)) {
            $request->session()->regenerate();
            $request->session()->put('zweifaktor', [
                'benutzer_id' => $benutzer->id,
                'bis' => now()->addMinutes(self::ZWEIFAKTOR_MINUTEN)->getTimestamp(),
            ]);

            return redirect()->route('anmeldung.zweifaktor');
        }

        return $this->abschliessen($request, $benutzer);
    }

    public function zweiFaktorFormular(Request $request): View|RedirectResponse
    {
        if (! $this->wartenderBenutzer($request)) {
            return redirect()->route('anmeldung');
        }

        return view('kern::anmeldung.zweifaktor');
    }

    public function zweiFaktorPruefen(Request $request, ZweiFaktor $zweiFaktor): RedirectResponse
    {
        $benutzer = $this->wartenderBenutzer($request);
        if (! $benutzer) {
            return redirect()->route('anmeldung')->withErrors(['email' => 'Bitte erneut anmelden.']);
        }

        $daten = $request->validate(['code' => ['required', 'string', 'max:20']], [], ['code' => 'Code']);
        $sperre = Anmeldesperre::zweiFaktor();

        if ($sekunden = $sperre->gesperrtFuer($benutzer->email, $request->ip())) {
            $request->session()->forget('zweifaktor');
            Protokoll::schreibe('anmeldung.gesperrt', $benutzer, ['schritt' => 'zwei-faktor'], $benutzer);

            return redirect()->route('anmeldung')->withErrors([
                'email' => 'Zu viele falsche Codes. Bitte in '.max(1, (int) ceil($sekunden / 60)).' Minuten erneut anmelden.',
            ]);
        }

        $art = $zweiFaktor->pruefe($benutzer, $daten['code']);

        if ($art === null) {
            $sperre->fehlversuch($benutzer->email, $request->ip());
            Protokoll::schreibe('zweifaktor.fehlgeschlagen', $benutzer, [], $benutzer);

            return back()->withErrors(['code' => 'Der Code ist nicht gültig.']);
        }

        $sperre->erfolg($benutzer->email, $request->ip());
        $request->session()->forget('zweifaktor');

        if ($art === 'notfallcode') {
            Protokoll::schreibe('zweifaktor.notfallcode_benutzt', $benutzer, [
                'verbleibend' => $zweiFaktor->anzahlNotfallcodes($benutzer->fresh()),
            ], $benutzer);
            $request->session()->flash('hinweis', 'Sie haben einen Notfall-Code benutzt. Er ist jetzt verbraucht. '
                .'Unter „Mein Konto“ können Sie neue Notfall-Codes erzeugen.');
        }

        $antwort = $this->abschliessen($request, $benutzer);

        if ($request->boolean('geraet_merken')) {
            $antwort->withCookie($zweiFaktor->merkeGeraet($benutzer, $request));
        }

        return $antwort;
    }

    public function abmelden(Request $request): RedirectResponse
    {
        if ($benutzer = $request->user()) {
            Protokoll::schreibe('abmeldung', null, [], $benutzer);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('anmeldung')->with('hinweis', 'Sie wurden abgemeldet.');
    }

    // ------------------------------------------------------------------

    private function abschliessen(Request $request, User $benutzer): RedirectResponse
    {
        Auth::login($benutzer);
        $request->session()->regenerate();

        $benutzer->forceFill(['letzte_anmeldung_am' => now()])->save();
        Protokoll::schreibe('anmeldung.erfolgreich', null, [], $benutzer);

        // Hat die Person genau einen Verein, wird er gleich ausgewählt (E21a).
        $vereine = $benutzer->waehlbareVereine();
        if (! $benutzer->istSuperAdmin() && $vereine->count() === 1) {
            $request->session()->put(SetzeMandantenkontext::SITZUNG, $vereine->first()->id);
        }

        return redirect()->intended(route('startseite'));
    }

    private function wartenderBenutzer(Request $request): ?User
    {
        $wartend = $request->session()->get('zweifaktor');

        if (! is_array($wartend) || ($wartend['bis'] ?? 0) < now()->getTimestamp()) {
            $request->session()->forget('zweifaktor');

            return null;
        }

        $benutzer = User::find($wartend['benutzer_id'] ?? 0);

        return $benutzer && $benutzer->aktiv ? $benutzer : null;
    }

    private function zurueck(string $meldung): RedirectResponse
    {
        return back()->withInput(request()->only('email'))->withErrors(['email' => $meldung]);
    }
}
