<?php

namespace App\Modules\Kern\Mandanten;

use App\Modules\Kern\Models\Mandant;
use Closure;

/**
 * Merkt sich, in welchem Verein (Mandant) die aktuelle Anfrage arbeitet.
 *
 * Grundsatz "im Zweifel nichts zeigen": Ist kein Verein gesetzt, liefern
 * mandantengebundene Abfragen KEINE Daten, und Speichern ist nicht möglich.
 * Der zentrale Filter (MandantScope) liest diesen Kontext bei jeder Abfrage.
 */
class Mandantenkontext
{
    private ?int $mandantId = null;

    public function setze(?Mandant $mandant): void
    {
        $this->mandantId = $mandant?->id;
    }

    public function id(): ?int
    {
        return $this->mandantId;
    }

    public function istGesetzt(): bool
    {
        return $this->mandantId !== null;
    }

    public function mandant(): ?Mandant
    {
        return $this->mandantId ? Mandant::find($this->mandantId) : null;
    }

    public function leeren(): void
    {
        $this->mandantId = null;
    }

    /**
     * Führt einen Arbeitsschritt im Kontext eines bestimmten Vereins aus und
     * stellt danach den vorherigen Kontext wieder her. Gedacht für den
     * Super-Admin (z. B. Vereinsimpressum eines Vereins bearbeiten) und für
     * öffentliche Seiten eines Vereins.
     *
     * @template T
     *
     * @param  Closure(): T  $schritt
     * @return T
     */
    public function fuer(Mandant $mandant, Closure $schritt): mixed
    {
        $vorher = $this->mandantId;
        $this->mandantId = $mandant->id;

        try {
            return $schritt();
        } finally {
            $this->mandantId = $vorher;
        }
    }
}
