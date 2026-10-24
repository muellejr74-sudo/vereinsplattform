<?php

namespace App\Modules\Kern\Http\Controllers\Verein;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Kern\Dienste\Protokoll;
use App\Modules\Kern\Http\Controllers\PasswortController;
use App\Modules\Kern\Mandanten\Mandantenkontext;
use App\Modules\Kern\Models\Mandant;
use App\Modules\Kern\Models\Rechtevergabe;
use App\Modules\Kern\Nachrichten\Einladung;
use App\Modules\Kern\Rechte\Berechtigung;
use App\Modules\Kern\Rechte\Rechtekatalog;
use App\Modules\Kern\Rechte\Rechteverwaltung;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * Personen und Rechte im aktiven Verein – für den Vereinsadmin und den Super-Admin.
 *
 * Vereinsadmin (E22, E22a, E92, E93): Personen anlegen, per exakter E-Mail-Adresse
 * hinzufügen, aus dem Verein entfernen, Einladung erneut senden und Rechte im
 * Rahmen der eigenen Rechte vergeben. Name/E-Mail ändern und Konten deaktivieren
 * bleibt dem Super-Admin vorbehalten (Konten können zu mehreren Vereinen gehören).
 */
class PersonenController extends Controller
{
    public function __construct(
        private Mandantenkontext $kontext,
        private Berechtigung $berechtigung,
        private Rechteverwaltung $rechteverwaltung,
    ) {}

    public function index(Request $request): View
    {
        $verein = $this->verein();
        abort_unless($request->user()->can('benutzer.verwalten') || $request->user()->can('rechte.vergeben'), 403);

        $personen = $verein->benutzer()->orderBy('name')->get();
        $rechte = Rechtevergabe::whereNull('vorstellung_id')->get()->groupBy('user_id');

        return view('kern::verein.personen.index', [
            'verein' => $verein,
            'personen' => $personen,
            'rechte' => $rechte,
        ]);
    }

    public function hinzufuegenFormular(): View
    {
        return view('kern::verein.personen.hinzufuegen', ['verein' => $this->verein()]);
    }

    /**
     * Vorhandenes Konto über die EXAKTE E-Mail-Adresse hinzufügen (E22a) oder neues
     * Konto anlegen und einladen (E22). Andere Vereine der Person bleiben unsichtbar.
     */
    public function hinzufuegen(Request $request): RedirectResponse
    {
        $verein = $this->verein();
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $daten = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:120'],
        ], [], ['email' => 'E-Mail-Adresse', 'name' => 'Name']);

        $person = User::where('email', $daten['email'])->first();

        if ($person) {
            if ($person->istSuperAdmin()) {
                return back()->withInput()->withErrors(['email' => 'Diese Person ist Super-Administrator und hat ohnehin Zugriff auf alle Vereine.']);
            }
            if (! $this->rechteverwaltung->mitgliedschaftAnlegen($person, $verein, $request->user())) {
                return back()->withInput()->withErrors(['email' => 'Diese Person gehört bereits zu Ihrem Verein.']);
            }

            return redirect()->route('verein.personen.rechte', $person)
                ->with('hinweis', 'Das vorhandene Konto von „'.$person->name.'“ wurde Ihrem Verein hinzugefügt. Legen Sie jetzt die Rechte fest.');
        }

        if (blank($daten['name'] ?? null)) {
            return back()->withInput()->withErrors(['name' => 'Zu dieser E-Mail-Adresse gibt es noch kein Konto. Bitte geben Sie zusätzlich den Namen ein, damit ein neues Konto angelegt wird.']);
        }

        $person = DB::transaction(function () use ($daten) {
            $person = new User(['name' => $daten['name'], 'email' => $daten['email'], 'aktiv' => true]);
            $person->password = bin2hex(random_bytes(32)); // unbekannt, bis die Person ihr Passwort festlegt
            $person->save();

            return $person;
        });

        Protokoll::schreibe('benutzer.angelegt', $person, ['email' => $person->email, 'verein' => $verein->kuerzel], null, $verein->id);
        $this->rechteverwaltung->mitgliedschaftAnlegen($person, $verein, $request->user());
        $this->einladungSenden($person, $verein);

        return redirect()->route('verein.personen.rechte', $person)
            ->with('hinweis', 'Das Konto für „'.$person->name.'“ wurde angelegt und eine Einladung an '.$person->email.' gesendet. Legen Sie jetzt die Rechte fest.');
    }

    public function rechte(Request $request, User $person): View
    {
        $verein = $this->verein();
        abort_unless($request->user()->can('benutzer.verwalten') || $request->user()->can('rechte.vergeben'), 403);
        $this->pruefeMitglied($person, $verein);

        $vergeber = $request->user();

        return view('kern::verein.personen.rechte', [
            'verein' => $verein,
            'person' => $person,
            'darfRechteAendern' => $vergeber->can('rechte.vergeben') && ! ($person->is($vergeber) && ! $vergeber->istSuperAdmin()),
            'darfPersonVerwalten' => $vergeber->can('benutzer.verwalten') && ! $person->is($vergeber),
            'istSelbst' => $person->is($vergeber),
            'vorhanden' => $this->berechtigung->vergebeneRechte($person, $verein),
            'weitergebbar' => $this->berechtigung->weitergebbareRechte($vergeber, $verein),
            'gruppen' => Rechtekatalog::nachBereich(),
            'vorlage' => Rechtekatalog::vorlageVereinsadmin(),
        ]);
    }

    public function rechteSpeichern(Request $request, User $person): RedirectResponse
    {
        $verein = $this->verein();
        $this->pruefeMitglied($person, $verein);

        $daten = $request->validate([
            'rechte' => ['array'],
            'rechte.*' => ['string', 'max:60'],
            'vorlage' => ['nullable', 'in:vereinsadmin'],
        ]);

        $gewuenscht = ($daten['vorlage'] ?? null) === 'vereinsadmin'
            ? Rechtekatalog::vorlageVereinsadmin()
            : ($daten['rechte'] ?? []);

        try {
            $ergebnis = $this->rechteverwaltung->setze($request->user(), $person, $verein, $gewuenscht);
        } catch (AuthorizationException $e) {
            abort(403, $e->getMessage());
        }

        $nichtAngewendet = array_diff($gewuenscht, $this->berechtigung->weitergebbareRechte($request->user(), $verein));
        $hinweis = ($ergebnis['hinzu'] || $ergebnis['entfernt']) ? 'Die Rechte wurden gespeichert.' : 'Es gab keine Änderungen.';
        if (($daten['vorlage'] ?? null) && $nichtAngewendet) {
            $hinweis .= ' Rechte, die Sie selbst nicht haben, wurden nicht vergeben.';
        }

        return redirect()->route('verein.personen.rechte', $person)->with('hinweis', $hinweis);
    }

    public function einladungErneut(Request $request, User $person): RedirectResponse
    {
        $verein = $this->verein();
        $this->pruefeMitglied($person, $verein);
        abort_unless($person->aktiv, 422, 'Dieses Konto ist deaktiviert.');

        $this->einladungSenden($person, $verein);

        return back()->with('hinweis', 'Die Einladung wurde erneut an '.$person->email.' gesendet.');
    }

    public function entfernen(Request $request, User $person): RedirectResponse
    {
        $verein = $this->verein();
        $this->pruefeMitglied($person, $verein);
        abort_if($person->is($request->user()), 403, 'Sie können sich nicht selbst aus dem Verein entfernen.');

        $this->rechteverwaltung->mitgliedschaftEntfernen($person, $verein, $request->user());

        return redirect()->route('verein.personen.index')
            ->with('hinweis', '„'.$person->name.'“ wurde aus dem Verein entfernt. Das Konto selbst bleibt bestehen.');
    }

    // ------------------------------------------------------------------

    private function verein(): Mandant
    {
        return $this->kontext->mandant();
    }

    /** Nur Personen des eigenen Vereins – fremde Konten sind nicht erreichbar (404). */
    private function pruefeMitglied(User $person, Mandant $verein): void
    {
        abort_unless($person->vereine()->whereKey($verein->id)->exists(), 404);
    }

    private function einladungSenden(User $person, Mandant $verein): void
    {
        $token = Password::broker(PasswortController::BROKER_EINLADUNG)->createToken($person);
        $person->notify(new Einladung($token, $verein->name));
        Protokoll::schreibe('benutzer.einladung_gesendet', $person, ['email' => $person->email], null, $verein->id);
    }
}
