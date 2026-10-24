<?php

namespace Tests\Feature\Ticket;

use App\Models\User;
use App\Modules\Kern\Dienste\Stammdaten;
use App\Modules\Kern\Mandanten\Mandantenkontext;
use App\Modules\Kern\Models\Mandant;
use App\Modules\Kern\Models\Saal;
use App\Modules\Kern\Models\Veranstaltung;
use App\Modules\Kern\Models\Vorstellung;
use App\Modules\Ticket\Models\Kartenauftrag;
use App\Modules\Ticket\Models\Ticketkategorie;
use App\Modules\Ticket\Models\Verkaufsstelle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Gemeinsame Ausgangslage: Theaterverein mit Ticketmodul, Veranstaltung, Kategorien, Vorverkaufsstelle. */
abstract class TicketTestCase extends TestCase
{
    use RefreshDatabase;

    protected Mandant $tvm;

    protected Veranstaltung $theater;

    protected Vorstellung $vorstellung;

    protected Ticketkategorie $erwachsene;

    protected Ticketkategorie $kinder;

    protected Verkaufsstelle $baeckerei;

    protected User $verkaeufer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-20 10:00'));
        $this->tvm = Mandant::factory()->create(['kuerzel' => 'TVM', 'name' => 'Theaterverein']);
        $this->tvm->setzeModule(['ticket']);
        app(Stammdaten::class)->sicherstellen($this->tvm);

        $this->im($this->tvm, function () {
            $this->theater = Veranstaltung::create(['name' => 'Der Hauptmann von Köpenick', 'kurzname' => 'Hauptmann']);
            $this->vorstellung = Vorstellung::create(['veranstaltung_id' => $this->theater->id, 'saal_id' => Saal::first()->id,
                'einlass_um' => '2026-12-01 18:30', 'beginn_um' => '2026-12-01 19:30', 'kontingent' => 50]);
            $this->erwachsene = Ticketkategorie::create(['veranstaltung_id' => $this->theater->id, 'name' => 'Erwachsene', 'preis_cent' => 1200, 'reihenfolge' => 10]);
            $this->kinder = Ticketkategorie::create(['veranstaltung_id' => $this->theater->id, 'name' => 'Kinder', 'preis_cent' => 600, 'reihenfolge' => 20]);
            $this->baeckerei = Verkaufsstelle::create(['name' => 'Bäckerei Müller', 'art' => 'vorverkauf']);
        });

        $this->verkaeufer = $this->personMitRechten($this->tvm, [], ['name' => 'Bärbel Bäckerin']);
        $this->baeckerei->benutzer()->attach($this->verkaeufer);
    }

    protected function im(Mandant $verein, \Closure $schritt): mixed
    {
        return app(Mandantenkontext::class)->fuer($verein, $schritt);
    }

    protected function als(User $person, ?Mandant $verein = null): static
    {
        $this->actingAs($person)->withSession(['mandant_id' => ($verein ?? $this->tvm)->id]);

        return $this;
    }

    protected function druckstelle(): User
    {
        return $this->personMitRechten($this->tvm, ['ticket.drucken'], ['name' => 'Dora Druck']);
    }

    /** Auftrag der Bäckerei anlegen (über die Oberfläche). */
    protected function bestellen(array $mengen, string $art = 'karten', ?string $name = null, ?int $anzahl = null): Kartenauftrag
    {
        $this->als($this->verkaeufer)->post('/tickets/auftraege', array_filter([
            'art' => $art, 'verkaufsstelle_id' => $this->baeckerei->id, 'vorstellung_id' => $this->vorstellung->id,
            'mengen' => $mengen, 'name' => $name, 'anzahl' => $anzahl,
        ], fn ($w) => $w !== null))->assertSessionHasNoErrors();

        return $this->im($this->tvm, fn () => Kartenauftrag::latest('id')->firstOrFail());
    }
}
