<?php

namespace App\Modules\Kern\Rechte;

use App\Models\User;
use App\Modules\Kern\Mandanten\Mandantenkontext;
use App\Modules\Kern\Models\Mandant;
use App\Modules\Kern\Models\Rechtevergabe;

/**
 * Zentrale Rechteprüfung (E19, E19a, E96) – immer auf dem Server.
 *
 * Ein Recht wirkt nur, wenn
 *  1. der Verein aktiv ist,
 *  2. die Person Mitglied des Vereins ist (Super-Admin: immer),
 *  3. das zugehörige Modul für den Verein freigeschaltet ist (sonst ruht das Recht, E96),
 *  4. das Recht vergeben ist (Super-Admin: hat alle Rechte) – für alle Veranstaltungen
 *     oder für die abgefragte Vorstellung (E95).
 */
class Berechtigung
{
    /** @var array<string, list<array{recht: string, vorstellung_id: ?int}>> */
    private array $zwischenspeicher = [];

    public function __construct(private Mandantenkontext $kontext) {}

    public function darf(User $benutzer, string $recht, ?Mandant $verein = null, ?int $vorstellungId = null): bool
    {
        if (! Rechtekatalog::gibt($recht) || ! $benutzer->aktiv) {
            return false;
        }

        $verein ??= $this->kontext->mandant();
        if (! $verein || ! $verein->aktiv) {
            return false;
        }

        $modul = Rechtekatalog::modul($recht);
        if ($modul !== null && ! $verein->hatModul($modul)) {
            return false;
        }

        if ($benutzer->istSuperAdmin()) {
            return true;
        }

        if (! $benutzer->darfInVerein($verein)) {
            return false;
        }

        foreach ($this->vergaben($benutzer, $verein) as $vergabe) {
            if ($vergabe['recht'] === $recht
                && ($vergabe['vorstellung_id'] === null || $vergabe['vorstellung_id'] === $vorstellungId)) {
                return true;
            }
        }

        return false;
    }

    /** Hat die Person irgendein Recht der Vereinsverwaltung? */
    public function darfVereinVerwalten(User $benutzer, ?Mandant $verein = null): bool
    {
        foreach (Rechtekatalog::VERWALTUNG as $recht) {
            if ($this->darf($benutzer, $recht, $verein)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Vergebene Rechte (ohne Prüfung von Modul/Mitgliedschaft) – z. B. für das Rechteformular.
     *
     * @return list<string>
     */
    public function vergebeneRechte(User $benutzer, Mandant $verein): array
    {
        return array_values(array_unique(array_column(
            array_filter($this->vergaben($benutzer, $verein), fn ($v) => $v['vorstellung_id'] === null),
            'recht',
        )));
    }

    /**
     * Rechte, die diese Person in diesem Verein weitergeben darf (E19a: nur eigene; Super-Admin: alle).
     *
     * @return list<string>
     */
    public function weitergebbareRechte(User $benutzer, Mandant $verein): array
    {
        if ($benutzer->istSuperAdmin()) {
            return Rechtekatalog::alle();
        }

        if (! $this->darf($benutzer, 'rechte.vergeben', $verein)) {
            return [];
        }

        return $this->vergebeneRechte($benutzer, $verein);
    }

    public function vergessen(): void
    {
        $this->zwischenspeicher = [];
    }

    /** @return list<array{recht: string, vorstellung_id: ?int}> */
    private function vergaben(User $benutzer, Mandant $verein): array
    {
        $schluessel = $benutzer->id.'|'.$verein->id;

        return $this->zwischenspeicher[$schluessel] ??= $this->kontext->fuer($verein, fn () => Rechtevergabe::query()
            ->where('user_id', $benutzer->id)
            ->get(['recht', 'vorstellung_id'])
            ->map(fn ($v) => ['recht' => $v->recht, 'vorstellung_id' => $v->vorstellung_id === null ? null : (int) $v->vorstellung_id])
            ->all());
    }
}
