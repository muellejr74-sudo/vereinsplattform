<?php

namespace App\Modules\Kern\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Kern\Dienste\Protokoll;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswortRegel;
use Illuminate\View\View;

/**
 * Passwort vergessen / zurücksetzen per zeitlich begrenztem E-Mail-Link (E23, E23a)
 * sowie "Passwort festlegen" für neu eingeladene Benutzer.
 */
class PasswortController extends Controller
{
    public const BROKER_ZURUECKSETZEN = 'users';

    public const BROKER_EINLADUNG = 'einladungen';

    public function vergessenFormular(): View
    {
        return view('kern::passwort.vergessen');
    }

    public function linkSenden(Request $request): RedirectResponse
    {
        $daten = $request->validate(['email' => ['required', 'email', 'max:255']], [], ['email' => 'E-Mail-Adresse']);
        $email = mb_strtolower(trim($daten['email']));
        $benutzer = User::where('email', $email)->where('aktiv', true)->first();

        if ($benutzer) {
            // Der Broker begrenzt selbst, wie oft ein Link angefordert werden kann.
            $status = Password::broker(self::BROKER_ZURUECKSETZEN)->sendResetLink(['email' => $email]);
            if ($status === Password::RESET_LINK_SENT) {
                Protokoll::schreibe('passwort.link_angefordert', $benutzer, [], $benutzer);
            }
        }

        // Immer dieselbe Antwort – verrät nicht, ob es ein Konto gibt.
        return back()->with('hinweis', 'Falls zu dieser E-Mail-Adresse ein Konto besteht, wurde ein Link zum Zurücksetzen versendet. '
            .'Der Link ist 60 Minuten gültig.');
    }

    public function zuruecksetzenFormular(Request $request, string $token): View
    {
        return view('kern::passwort.neu', [
            'token' => $token,
            'email' => $request->query('email'),
            'aktion' => route('passwort.speichern'),
            'titel' => 'Neues Passwort festlegen',
        ]);
    }

    public function zuruecksetzen(Request $request): RedirectResponse
    {
        return $this->speichern($request, self::BROKER_ZURUECKSETZEN, 'passwort.zurueckgesetzt');
    }

    public function einladungFormular(Request $request, string $token): View
    {
        return view('kern::passwort.neu', [
            'token' => $token,
            'email' => $request->query('email'),
            'aktion' => route('einladung.speichern'),
            'titel' => 'Willkommen! Bitte legen Sie Ihr Passwort fest',
        ]);
    }

    public function einladungAnnehmen(Request $request): RedirectResponse
    {
        return $this->speichern($request, self::BROKER_EINLADUNG, 'passwort.festgelegt');
    }

    private function speichern(Request $request, string $broker, string $aktion): RedirectResponse
    {
        $daten = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswortRegel::defaults()],
        ], [], ['email' => 'E-Mail-Adresse', 'password' => 'Passwort']);

        $daten['email'] = mb_strtolower(trim($daten['email']));

        $status = Password::broker($broker)->reset(
            ['email' => $daten['email'], 'password' => $daten['password'], 'password_confirmation' => $request->input('password_confirmation'), 'token' => $daten['token']],
            function (User $benutzer, string $passwort) use ($aktion) {
                if (! $benutzer->aktiv) {
                    return;
                }

                $benutzer->forceFill([
                    'password' => Hash::make($passwort),
                    'remember_token' => Str::random(60),
                ])->save();

                // Alle anderen Sitzungen dieses Kontos beenden.
                if (config('session.driver') === 'database') {
                    DB::table(config('session.table', 'sessions'))->where('user_id', $benutzer->id)->delete();
                }

                Protokoll::schreibe($aktion, $benutzer, [], $benutzer);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))->withErrors([
                'email' => 'Der Link ist ungültig oder abgelaufen. Bitte fordern Sie einen neuen an.',
            ]);
        }

        return redirect()->route('anmeldung')->with('hinweis', 'Ihr Passwort wurde gespeichert. Sie können sich jetzt anmelden.');
    }
}
