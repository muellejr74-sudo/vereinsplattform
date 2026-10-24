<?php

namespace App\Modules\Kern\Dienste;

use App\Models\User;
use App\Modules\Kern\Mandanten\Mandantenkontext;
use App\Modules\Kern\Models\Mandant;
use App\Modules\Kern\Models\Tischcode;
use App\Modules\Kern\Models\Tischzuordnung;
use App\Modules\Kern\Models\Veranstaltung;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Tische zuordnen, übernehmen und lösen (E52a, E54, E54a, E71, E74, E74a, E107).
 *
 * Die Übernahme eines noch verknüpften Tisches greift bewusst über Vereinsgrenzen
 * hinweg (der Tisch steht physisch beim neuen Verein). Dafür gibt es genau diese
 * eine Stelle; der Vorgang wird still protokolliert – ohne Namen des jeweils
 * anderen Vereins.
 */
class Tischverwaltung
{
    public function __construct(private Mandantenkontext $kontext) {}

    /**
     * @return array{zuordnung: Tischzuordnung, uebernommen: bool}
     */
    public function zuordnen(Tischcode $code, Veranstaltung $veranstaltung, ?string $tischnummer, Carbon $gueltigBis, User $durch): array
    {
        $verein = Mandant::findOrFail($veranstaltung->mandant_id);

        if (! $code->istBetreiberTisch() && $code->mandant_id !== $verein->id) {
            throw new InvalidArgumentException('Dieser Code gehört einem anderen Verein.');
        }
        if ($gueltigBis->isBefore(today())) {
            throw new InvalidArgumentException('Das Enddatum darf nicht in der Vergangenheit liegen.');
        }

        $tischnummer = $code->einheitscode ? null : (trim((string) $tischnummer) ?: null);
        if (! $code->einheitscode && $tischnummer === null) {
            throw new InvalidArgumentException('Bitte eine Tischnummer angeben.');
        }

        return DB::transaction(function () use ($code, $veranstaltung, $verein, $tischnummer, $gueltigBis, $durch) {
            // Sperre auf den Code: zwei gleichzeitige Scans desselben Tisches können sich nicht überholen.
            Tischcode::whereKey($code->id)->lockForUpdate()->first();

            $bisher = DB::table('tischzuordnungen')->where('qr_code_id', $code->id)->first();
            $uebernommen = false;

            if ($bisher && (int) $bisher->veranstaltung_id !== $veranstaltung->id) {
                DB::table('tischzuordnungen')->where('id', $bisher->id)->delete();
                $uebernommen = true;

                // E74a: still vermerken – beim abgebenden und beim übernehmenden Verein.
                Protokoll::schreibe('tisch.abgegeben', null, [
                    'code' => $code->code, 'veranstaltung_id' => (int) $bisher->veranstaltung_id, 'tischnummer' => $bisher->tischnummer,
                ], $durch, (int) $bisher->mandant_id);
                Protokoll::schreibe('tisch.uebernommen', $code, [
                    'code' => $code->code, 'von_veranstaltung_id' => (int) $bisher->veranstaltung_id, 'von_mandant_id' => (int) $bisher->mandant_id,
                ], $durch, $verein->id);
            }

            $zuordnung = $this->kontext->fuer($verein, function () use ($code, $veranstaltung, $tischnummer, $gueltigBis, $durch) {
                if ($tischnummer !== null) {
                    $belegt = Tischzuordnung::where('veranstaltung_id', $veranstaltung->id)
                        ->where('tischnummer', $tischnummer)
                        ->where('qr_code_id', '!=', $code->id)
                        ->gueltig()
                        ->exists();
                    if ($belegt) {
                        throw new InvalidArgumentException('Tischnummer '.$tischnummer.' ist in dieser Veranstaltung schon vergeben.');
                    }
                }

                return Tischzuordnung::updateOrCreate(
                    ['qr_code_id' => $code->id],
                    ['veranstaltung_id' => $veranstaltung->id, 'tischnummer' => $tischnummer,
                        'gueltig_bis' => $gueltigBis->toDateString(), 'zugeordnet_von' => $durch->id],
                );
            });

            Protokoll::schreibe('tisch.zugeordnet', $zuordnung, [
                'code' => $code->code, 'veranstaltung' => $veranstaltung->name, 'tischnummer' => $tischnummer,
                'gueltig_bis' => $gueltigBis->toDateString(),
            ], $durch);

            return ['zuordnung' => $zuordnung, 'uebernommen' => $uebernommen];
        });
    }

    public function loesen(Tischzuordnung $zuordnung, User $durch): void
    {
        Protokoll::schreibe('tisch.geloest', $zuordnung, ['tischnummer' => $zuordnung->tischnummer], $durch);
        $zuordnung->delete();
    }

    /** Abgelaufene Zuordnungen lösen (Cronjob, E71, E107). Vereinsübergreifend. */
    public function abgelaufeneLoesen(): int
    {
        return DB::table('tischzuordnungen')->whereDate('gueltig_bis', '<', today())->delete();
    }

    /**
     * Aktuelle, gültige Zuordnung eines Codes – vereinsübergreifend, nur für die Scan-Weiche
     * und die Betreiber-Übersicht.
     */
    public function aktuelleZuordnung(Tischcode $code): ?object
    {
        return DB::table('tischzuordnungen')
            ->where('qr_code_id', $code->id)
            ->whereDate('gueltig_bis', '>=', today())
            ->first();
    }
}
