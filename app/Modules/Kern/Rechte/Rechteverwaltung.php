<?php

namespace App\Modules\Kern\Rechte;

use App\Models\User;
use App\Modules\Kern\Dienste\Protokoll;
use App\Modules\Kern\Mandanten\Mandantenkontext;
use App\Modules\Kern\Models\Mandant;
use App\Modules\Kern\Models\Rechtevergabe;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Rechte vergeben und entziehen mit den Schutzregeln aus E19a:
 * - nur Rechte, die man selbst hat (Super-Admin: alle),
 * - nie an sich selbst,
 * - Rechte, die man selbst nicht hat, bleiben bei der anderen Person unverändert,
 * - jede Änderung wird protokolliert.
 */
class Rechteverwaltung
{
    public function __construct(private Berechtigung $berechtigung, private Mandantenkontext $kontext) {}

    /**
     * @param  list<string>  $gewuenscht  Rechte, die die Person danach haben soll (aus dem Formular)
     * @return array{hinzu: list<string>, entfernt: list<string>}
     */
    public function setze(User $vergeber, User $person, Mandant $verein, array $gewuenscht): array
    {
        if ($person->is($vergeber) && ! $vergeber->istSuperAdmin()) {
            throw new AuthorizationException('Eigene Rechte können nicht geändert werden.');
        }
        if ($person->istSuperAdmin()) {
            throw new AuthorizationException('Der Super-Administrator hat immer alle Rechte.');
        }
        if (! $person->vereine()->whereKey($verein->id)->exists()) {
            throw new AuthorizationException('Die Person gehört nicht zu diesem Verein.');
        }

        $erlaubt = $this->berechtigung->weitergebbareRechte($vergeber, $verein);
        if ($erlaubt === []) {
            throw new AuthorizationException('Keine Berechtigung, Rechte zu vergeben.');
        }

        $gewuenscht = array_values(array_intersect(array_unique($gewuenscht), Rechtekatalog::alle()));
        $bisher = $this->berechtigung->vergebeneRechte($person, $verein);

        // Nur im Rahmen der eigenen Rechte ändern; alles andere bleibt, wie es ist.
        $hinzu = array_values(array_intersect(array_diff($gewuenscht, $bisher), $erlaubt));
        $entfernt = array_values(array_intersect(array_diff($bisher, $gewuenscht), $erlaubt));

        if ($hinzu === [] && $entfernt === []) {
            return ['hinzu' => [], 'entfernt' => []];
        }

        $this->kontext->fuer($verein, function () use ($person, $vergeber, $hinzu, $entfernt) {
            DB::transaction(function () use ($person, $vergeber, $hinzu, $entfernt) {
                foreach ($hinzu as $recht) {
                    Rechtevergabe::create(['user_id' => $person->id, 'recht' => $recht, 'vergeben_von' => $vergeber->id]);
                }
                if ($entfernt !== []) {
                    Rechtevergabe::where('user_id', $person->id)->whereNull('vorstellung_id')->whereIn('recht', $entfernt)->delete();
                }
            });
        });

        $this->berechtigung->vergessen();

        Protokoll::schreibe('rechte.geaendert', $person, [
            'person' => $person->email,
            'hinzu' => $hinzu,
            'entfernt' => $entfernt,
        ], $vergeber, $verein->id);

        return ['hinzu' => $hinzu, 'entfernt' => $entfernt];
    }

    /** Person einem Verein zuordnen (ohne Rechte). */
    public function mitgliedschaftAnlegen(User $person, Mandant $verein, ?User $durch = null): bool
    {
        if ($person->vereine()->whereKey($verein->id)->exists()) {
            return false;
        }

        $person->vereine()->attach($verein->id);
        Protokoll::schreibe('benutzer.verein_zugeordnet', $person, ['verein' => $verein->kuerzel], $durch, $verein->id);

        return true;
    }

    /** Person aus einem Verein entfernen – ihre Rechte in diesem Verein werden mit gelöscht. */
    public function mitgliedschaftEntfernen(User $person, Mandant $verein, ?User $durch = null): void
    {
        $this->kontext->fuer($verein, fn () => Rechtevergabe::where('user_id', $person->id)->delete());
        $person->vereine()->detach($verein->id);
        $this->berechtigung->vergessen();

        Protokoll::schreibe('benutzer.verein_entfernt', $person, ['verein' => $verein->kuerzel], $durch, $verein->id);
    }
}
