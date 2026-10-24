<?php

namespace App\Modules\Kern\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Kern\Mandanten\Mandantenkontext;
use App\Modules\Kern\Models\Mandant;
use App\Modules\Kern\Models\Plattformtext;
use App\Modules\Kern\Models\Vereinstext;
use Illuminate\View\View;

/** Öffentliche Anzeige der Rechtstexte (E83) – ohne Anmeldung erreichbar. */
class RechtstexteController extends Controller
{
    public function impressum(): View
    {
        return view('kern::rechtstexte.anzeigen', [
            'titel' => 'Impressum',
            'inhalt' => Plattformtext::inhalt(Plattformtext::IMPRESSUM),
        ]);
    }

    public function datenschutz(): View
    {
        return view('kern::rechtstexte.anzeigen', [
            'titel' => 'Datenschutzerklärung',
            'inhalt' => Plattformtext::inhalt(Plattformtext::DATENSCHUTZ),
        ]);
    }

    public function vereinsimpressum(string $kuerzel, Mandantenkontext $kontext): View
    {
        $verein = $this->verein($kuerzel);

        return view('kern::rechtstexte.anzeigen', [
            'titel' => 'Impressum – '.$verein->name,
            'inhalt' => $kontext->fuer($verein, fn () => Vereinstext::where('art', Vereinstext::IMPRESSUM)->value('inhalt')),
        ]);
    }

    public function vereinsdatenschutz(string $kuerzel, Mandantenkontext $kontext): View
    {
        $verein = $this->verein($kuerzel);

        return view('kern::rechtstexte.anzeigen', [
            'titel' => 'Datenschutzerklärung',
            'inhalt' => Plattformtext::inhalt(Plattformtext::DATENSCHUTZ),
            'ergaenzungTitel' => 'Ergänzende Hinweise von „'.$verein->name.'“',
            'ergaenzung' => $kontext->fuer($verein, fn () => Vereinstext::where('art', Vereinstext::DATENSCHUTZ_ERGAENZUNG)->value('inhalt')),
        ]);
    }

    private function verein(string $kuerzel): Mandant
    {
        return Mandant::aktiv()->where('kuerzel', mb_strtoupper($kuerzel))->firstOrFail();
    }
}
