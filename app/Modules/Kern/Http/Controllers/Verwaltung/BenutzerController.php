<?php

namespace App\Modules\Kern\Http\Controllers\Verwaltung;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Kern\Dienste\Protokoll;
use App\Modules\Kern\Http\Controllers\PasswortController;
use App\Modules\Kern\Models\Mandant;
use App\Modules\Kern\Nachrichten\Einladung;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Benutzerverwaltung durch den Super-Admin (E8, E20).
 *
 * Neue Benutzer erhalten eine Einladungs-E-Mail und legen ihr Passwort selbst
 * fest – niemand außer der Person kennt das Passwort. Das Super-Admin-
 * Kennzeichen kann hier nicht vergeben werden (nur per Server-Befehl, E8a).
 * Rechte und Rollen folgen in Stufe 1b.
 */
class BenutzerController extends Controller
{
    public function index(Request $request): View
    {
        $suche = trim((string) $request->query('suche'));

        $benutzer = User::with('vereine')
            ->when($suche !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', '%'.$suche.'%')
                ->orWhere('email', 'like', '%'.$suche.'%')))
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        return view('kern::verwaltung.benutzer.index', ['benutzer' => $benutzer, 'suche' => $suche]);
    }

    public function create(): View
    {
        return view('kern::verwaltung.benutzer.formular', [
            'person' => new User(['aktiv' => true]),
            'vereine' => Mandant::orderBy('name')->get(),
            'zugeordnet' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $daten = $this->pruefe($request);

        $person = DB::transaction(function () use ($daten) {
            $person = new User(['name' => $daten['name'], 'email' => $daten['email'], 'aktiv' => true]);
            // Zufälliges, unbekanntes Passwort, bis die Person ihr eigenes festlegt.
            $person->password = bin2hex(random_bytes(32));
            $person->save();
            $person->vereine()->sync($daten['vereine'] ?? []);

            return $person;
        });

        Protokoll::schreibe('benutzer.angelegt', $person, [
            'email' => $person->email,
            'vereine' => $person->vereine()->pluck('kuerzel')->all(),
        ]);
        $this->einladungSenden($person);

        return redirect()->route('verwaltung.benutzer.index')
            ->with('hinweis', 'Benutzer „'.$person->name.'“ wurde angelegt. Eine Einladung zum Festlegen des Passworts wurde an '.$person->email.' gesendet.');
    }

    public function edit(User $person): View
    {
        return view('kern::verwaltung.benutzer.formular', [
            'person' => $person,
            'vereine' => Mandant::orderBy('name')->get(),
            'zugeordnet' => $person->vereine()->pluck('mandanten.id')->all(),
        ]);
    }

    public function update(Request $request, User $person): RedirectResponse
    {
        $daten = $this->pruefe($request, $person);

        DB::transaction(function () use ($person, $daten) {
            $person->fill(['name' => $daten['name'], 'email' => $daten['email']]);
            $aenderungen = $person->getDirty();
            $person->save();

            if ($aenderungen) {
                Protokoll::schreibe('benutzer.geaendert', $person, ['geaendert' => $aenderungen]);
            }

            $ergebnis = $person->vereine()->sync($daten['vereine'] ?? []);

            foreach ($ergebnis['attached'] as $id) {
                Protokoll::schreibe('benutzer.verein_zugeordnet', $person, ['verein' => Mandant::find($id)?->kuerzel], null, (int) $id);
            }
            foreach ($ergebnis['detached'] as $id) {
                Protokoll::schreibe('benutzer.verein_entfernt', $person, ['verein' => Mandant::find($id)?->kuerzel], null, (int) $id);
            }
        });

        return redirect()->route('verwaltung.benutzer.index')->with('hinweis', 'Benutzer „'.$person->name.'“ wurde gespeichert.');
    }

    public function aktivUmschalten(Request $request, User $person): RedirectResponse
    {
        if ($person->is($request->user())) {
            return back()->withErrors(['person' => 'Sie können Ihr eigenes Konto nicht deaktivieren.']);
        }

        $person->forceFill(['aktiv' => ! $person->aktiv])->save();

        if (! $person->aktiv && config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $person->id)->delete();
        }

        Protokoll::schreibe($person->aktiv ? 'benutzer.aktiviert' : 'benutzer.deaktiviert', $person);

        return back()->with('hinweis', 'Benutzer „'.$person->name.'“ ist jetzt '.($person->aktiv ? 'aktiv' : 'deaktiviert').'.');
    }

    public function einladungErneut(User $person): RedirectResponse
    {
        abort_unless($person->aktiv, 422, 'Deaktivierte Konten erhalten keine Einladung.');
        $this->einladungSenden($person);

        return back()->with('hinweis', 'Die Einladung wurde erneut an '.$person->email.' gesendet.');
    }

    private function einladungSenden(User $person): void
    {
        $token = Password::broker(PasswortController::BROKER_EINLADUNG)->createToken($person);
        $person->notify(new Einladung($token));
        Protokoll::schreibe('benutzer.einladung_gesendet', $person, ['email' => $person->email]);
    }

    /** @return array<string, mixed> */
    private function pruefe(Request $request, ?User $person = null): array
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($person?->id)],
            'vereine' => ['array'],
            'vereine.*' => ['integer', Rule::exists('mandanten', 'id')],
        ], [
            'email.unique' => 'Zu dieser E-Mail-Adresse gibt es bereits ein Konto. Bitte das vorhandene Konto bearbeiten und dort den Verein zuordnen (ein Konto pro Person).',
        ], ['name' => 'Name', 'email' => 'E-Mail-Adresse', 'vereine' => 'Vereine']);
    }
}
