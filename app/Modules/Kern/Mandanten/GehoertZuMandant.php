<?php

namespace App\Modules\Kern\Mandanten;

use App\Modules\Kern\Models\Mandant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Für alle Modelle, deren Tabelle eine Spalte "mandant_id" hat.
 *
 * - Jede Abfrage wird automatisch auf den aktuellen Verein beschränkt.
 * - Neue Datensätze erhalten automatisch die Kennung des aktuellen Vereins.
 * - Ohne gesetzten Verein kann nichts gespeichert werden.
 * - Die Vereinskennung eines Datensatzes kann nachträglich nicht geändert werden.
 *
 * Wichtig: Den Filter nie mit withoutGlobalScopes() umgehen. Für Arbeiten in
 * einem anderen Verein gibt es Mandantenkontext::fuer().
 */
trait GehoertZuMandant
{
    public static function bootGehoertZuMandant(): void
    {
        static::addGlobalScope(new MandantScope);

        static::creating(function (Model $model) {
            $aktuell = app(Mandantenkontext::class)->id();

            if ($aktuell === null) {
                throw new MandantFehlt('Speichern ohne gesetzten Verein ist nicht erlaubt ('.class_basename($model).').');
            }

            if ($model->mandant_id !== null && (int) $model->mandant_id !== $aktuell) {
                throw new MandantFehlt('Ein Datensatz kann nicht für einen fremden Verein angelegt werden.');
            }

            $model->mandant_id = $aktuell;
        });

        static::updating(function (Model $model) {
            if ($model->isDirty('mandant_id')) {
                throw new MandantFehlt('Die Vereinskennung eines Datensatzes darf nicht geändert werden.');
            }
        });
    }

    public function mandant(): BelongsTo
    {
        return $this->belongsTo(Mandant::class);
    }
}
