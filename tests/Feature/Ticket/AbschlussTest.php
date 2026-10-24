<?php

namespace Tests\Feature\Ticket;

use App\Models\User;
use App\Modules\Ticket\Dienste\Auswertung;
use App\Modules\Ticket\Models\Kartenauftrag;
use App\Modules\Ticket\Models\Reservierung;
use App\Modules\Ticket\Models\Ticket;
use App\Modules\Ticket\Nachrichten\NeuerDruckauftrag;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/** Stufe 4b: Abschluss, Nachbuchen, Auswertung, Berichte, E-Mail an die Druckstelle (E4, E79, E129–E132). */
class AbschlussTest extends TicketTestCase
{
    private User $chef;

    protected function setUp(): void
    {
        parent::setUp();
        $this->chef = $this->personMitRechten($this->tvm, ['ticket.drucken', 'ticket.reservierungen', 'ticket.abendkasse', 'ticket.stornieren',
            'ticket.zahlungseingang', 'ticket.auswertungen', 'ticket.abschluss', 'ticket.kontingente'], ['name' => 'Chefin']);
    }

    private function tickets(): Collection
    {
        return $this->im($this->tvm, fn () => Ticket::orderBy('id')->get());
    }

    /** Bäckerei: 10 Karten ausgegeben, davon 2 zurück (storniert). */
    private function vorverkauf(): void
    {
        $a = $this->bestellen([$this->erwachsene->id => 10]);
        $this->als($this->chef)->post('/tickets/auftraege/'.$a->id.'/bestaetigen');
        $this->post('/tickets/auftraege/'.$a->id.'/gedruckt');
        $this->post('/tickets/auftraege/'.$a->id.'/uebergeben');
        $karten = $this->tickets();
        foreach ($karten->take(2) as $k) {
            $this->postJson('/tickets/stornieren/erfassen', ['code' => $k->nummer]);
        }
        $this->post('/tickets/stornieren/ausfuehren')->assertSessionHasNoErrors();
    }

    private function reservieren(array $daten): Reservierung
    {
        $this->als($this->chef)->post('/tickets/reservierungen', array_merge([
            'verkaufsstelle_id' => '', 'vorstellung_id' => $this->vorstellung->id, 'mengen' => [$this->erwachsene->id => 1],
        ], $daten))->assertSessionHasNoErrors();

        return $this->im($this->tvm, fn () => Reservierung::latest('id')->firstOrFail());
    }

    public function test_email_an_druckstelle_bei_neuen_druckauftraegen(): void
    {
        Notification::fake();
        $druck = $this->personMitRechten($this->tvm, ['ticket.drucken'], ['name' => 'Dora Druck']);
        $abgemeldet = $this->personMitRechten($this->tvm, ['ticket.drucken'], ['name' => 'Otto Ohne']);
        $this->als($abgemeldet)->put('/konto/druckauftrag-mails', ['druckauftraege' => 0])->assertSessionHasNoErrors();
        $this->get('/konto')->assertSee('E-Mail bei neuen Druckaufträgen');

        $this->bestellen([$this->erwachsene->id => 3]);
        Notification::assertSentTo($druck, NeuerDruckauftrag::class, fn ($n) => $n->anzahl === 3 && str_contains($n->fuer, 'Bäckerei'));
        Notification::assertNotSentTo($abgemeldet, NeuerDruckauftrag::class);
        Notification::assertNotSentTo($this->verkaeufer, NeuerDruckauftrag::class);

        // Gedruckte Reservierung: ebenfalls eine Meldung, ohne Namen oder Telefon in der E-Mail.
        $this->reservieren(['art' => 'gedruckt', 'name' => 'Familie Geheim', 'telefon' => '0711 4711']);
        Notification::assertSentTo($druck, NeuerDruckauftrag::class, function ($n) use ($druck) {
            $text = implode(' ', $n->toMail($druck)->introLines);

            return $n->art === 'Reservierung (Abendkasse)' && ! str_contains($text, 'Geheim') && ! str_contains($text, '4711');
        });
        Notification::assertSentToTimes($druck, NeuerDruckauftrag::class, 2);
    }

    public function test_nachbuchen_erst_ab_beginn_mit_verkauf_und_freikarten(): void
    {
        $basis = '/tickets/vorstellungen/'.$this->vorstellung->id;
        $this->als($this->chef)->post($basis.'/nachbuchen/verkauf', ['mengen' => [$this->erwachsene->id => 1], 'zahlungsart' => 'bar'])->assertSessionHasErrors('nachbuchung');

        $this->travelTo(Carbon::parse('2026-12-03 10:00'));
        $this->get($basis.'/nachbuchen')->assertOk()->assertSee('Verkäufe nachtragen');
        $this->post($basis.'/nachbuchen/verkauf', ['mengen' => [$this->erwachsene->id => 2, $this->kinder->id => 1], 'zahlungsart' => 'bar'])->assertSessionHasNoErrors();
        $this->post($basis.'/nachbuchen/freikarten', ['anzahl' => 2, 'name' => 'Sponsor'])->assertSessionHasNoErrors();

        $t = $this->tickets();
        $this->assertCount(5, $t);
        $this->assertTrue($t->every(fn ($x) => $x->status === 'eingelassen' && $x->verkauf_ueber === 'nachbuchung'));
        $this->assertSame(2, $t->where('ist_freikarte', true)->where('name', 'Sponsor')->count());
        $this->assertDatabaseHas('protokoll', ['aktion' => 'ticket.nachbuchung', 'akteur' => 'Chefin']);

        $this->als($this->verkaeufer)->get($basis.'/nachbuchen')->assertForbidden();
    }

    public function test_auswertung_einnahmen_und_abrechnung_je_stelle(): void
    {
        $this->vorverkauf();                                                                  // 8 verkauft über Bäckerei = 96 €
        $vorkasse = $this->reservieren(['art' => 'vorkasse', 'name' => 'Huber', 'strasse' => 'Weg 1', 'plz' => '12345', 'ort' => 'Dorf',
            'zahlungsfrist' => today()->addDays(7)->toDateString()]);
        $this->post('/tickets/reservierungen/'.$vorkasse->id.'/bezahlt');                     // 12 € Überweisung
        $digital = $this->reservieren(['art' => 'digital', 'name' => 'Meier']);

        $this->travelTo(Carbon::parse('2026-12-01 18:45'));
        $abend = '/tickets/abendkasse/'.$this->vorstellung->id;
        $this->post($abend.'/verkauf', ['mengen' => [$this->erwachsene->id => 2], 'zahlungsart' => 'bar']);     // 24 € bar
        $this->post($abend.'/reservierungen/'.$digital->id, ['zahlungsart' => 'karte']);                      // 12 € Karte
        $this->travelTo(Carbon::parse('2026-12-02 10:00'));
        $this->post('/tickets/vorstellungen/'.$this->vorstellung->id.'/nachbuchen/verkauf', ['mengen' => [$this->kinder->id => 1], 'zahlungsart' => 'bar']); // 6 € bar

        $z = $this->im($this->tvm, fn () => app(Auswertung::class)->zahlen($this->vorstellung->fresh()));
        $this->assertSame(3000, $z['zahlungsarten']['bar']['betrag']);
        $this->assertSame(1200, $z['zahlungsarten']['karte']['betrag']);
        $this->assertSame(1200, $z['zahlungsarten']['ueberweisung']['betrag']);
        $this->assertSame(9600, $z['zahlungsarten']['stellen']['betrag']);
        $this->assertSame(15000, $z['einnahmen']);
        $this->assertSame(8, $z['wege']['vorverkauf']);
        $this->assertSame(2, $z['storniert']);

        $a = $this->im($this->tvm, fn () => app(Auswertung::class)->abrechnung($this->vorstellung->fresh()))->first();
        $this->assertSame([10, 2, 8, 9600], [$a['ausgegeben'], $a['zurueck'], $a['verkauft'], $a['betrag']]);

        $basis = '/tickets/vorstellungen/'.$this->vorstellung->id;
        $this->get($basis.'/auswertung')->assertOk()->assertSee('150,00 €')->assertSee('Bäckerei Müller')->assertSee('96,00 €');
        $this->assertStringStartsWith('%PDF', $this->get($basis.'/abschlussbericht.pdf')->assertOk()->getContent());
        $this->assertStringStartsWith('%PDF', $this->get($basis.'/abrechnung/'.$this->baeckerei->id.'.pdf')->assertOk()->getContent());
        $csv = $this->get($basis.'/export/karten.csv')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->getContent();
        $this->assertStringStartsWith("\xEF\xBB\xBFNummer;Kategorie;Preis", $csv);
        $this->assertStringContainsString('Bäckerei Müller', $this->get($basis.'/export/abrechnung.csv')->getContent());

        $this->als($this->verkaeufer)->get($basis.'/auswertung')->assertForbidden();
    }

    public function test_abschluss_mit_vorab_pruefung_sperre_und_rueckgaengig(): void
    {
        $this->vorverkauf();
        $offen = $this->reservieren(['art' => 'digital', 'name' => 'Schulz']);
        $this->bestellen([$this->kinder->id => 2]);                                         // angefordert, nie bestätigt
        $frei = $this->als($this->chef)->post('/tickets/auftraege', ['art' => 'freikarten', 'verkaufsstelle_id' => '',
            'vorstellung_id' => $this->vorstellung->id, 'anzahl' => 3, 'name' => 'Ehrengast']);
        $fk = $this->im($this->tvm, fn () => Kartenauftrag::latest('id')->first());
        $this->post('/tickets/auftraege/'.$fk->id.'/bestaetigen');                          // gedruckt, nie übergeben

        $this->travelTo(Carbon::parse('2026-12-15 10:00'));
        $basis = '/tickets/vorstellungen/'.$this->vorstellung->id;
        $this->get('/tickets')->assertSee('Abschluss offen')->assertSee('vor 14 Tagen');
        $this->get($basis.'/abschluss')->assertOk()->assertSee('Schulz')->assertSee('Nicht bestätigte Kartenaufträge')->assertSee('Nicht ausgegebene Karten (3)');

        $this->post($basis.'/abschluss')->assertSessionHasErrors('abschluss');
        foreach (['reservierungen', 'auftraege', 'karten'] as $art) {
            $this->post($basis.'/abschluss/bereinigen', ['art' => $art])->assertSessionHasNoErrors();
        }
        $this->assertSame('storniert', $offen->fresh()->status);
        $this->post($basis.'/abschluss')->assertSessionHasNoErrors();

        // E4: ausgegebene Vorverkaufskarten gelten jetzt als verkauft.
        $this->assertSame(8, $this->tickets()->where('status', 'verkauft')->where('verkauf_ueber', 'abschluss')->count());
        $this->assertDatabaseHas('protokoll', ['aktion' => 'ticket.abschluss', 'akteur' => 'Chefin']);
        $this->get('/tickets')->assertDontSee('Abschluss offen');

        // E4b: danach gesperrt.
        $karte = $this->tickets()->firstWhere('status', 'verkauft');
        $this->postJson('/tickets/stornieren/erfassen', ['code' => $karte->nummer])->assertJson(['farbe' => 'rot']);
        $this->post($basis.'/nachbuchen/verkauf', ['mengen' => [$this->erwachsene->id => 1], 'zahlungsart' => 'bar'])->assertSessionHasErrors('nachbuchung');
        $this->put('/tickets/veranstaltungen/'.$this->theater->id.'/vorstellungen/'.$this->vorstellung->id.'/kontingent', ['kontingent' => 60])->assertSessionHasErrors('kontingent');
        $this->post($basis.'/abschluss')->assertSessionHasErrors('abschluss');

        // E4a: Rückgängig nur Super-Admin mit Grund.
        $this->post($basis.'/abschluss/rueckgaengig', ['grund' => 'Tippfehler'])->assertForbidden();
        $admin = $this->superAdmin(['name' => 'Super']);
        $this->als($admin)->post($basis.'/abschluss/rueckgaengig', ['grund' => 'x'])->assertSessionHasErrors('grund');
        $this->post($basis.'/abschluss/rueckgaengig', ['grund' => 'Nachträgliche Rückgabe der Bäckerei'])->assertSessionHasNoErrors();
        $this->assertSame(8, $this->tickets()->where('status', 'ausgegeben')->count());
        $this->assertDatabaseHas('protokoll', ['aktion' => 'ticket.abschluss_rueckgaengig', 'akteur' => 'Super']);
        $this->assertNotNull($frei);
    }

    public function test_notfallliste_ohne_telefonnummern(): void
    {
        $this->reservieren(['art' => 'digital', 'name' => 'Meier', 'telefon' => '0711 998877']);
        $kasse = $this->personMitRechten($this->tvm, ['ticket.abendkasse']);
        $pdf = $this->als($kasse)->get('/tickets/vorstellungen/'.$this->vorstellung->id.'/notfallliste.pdf')->assertOk()->getContent();
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringNotContainsString('998877', $pdf);

        $this->als($this->verkaeufer)->get('/tickets/vorstellungen/'.$this->vorstellung->id.'/notfallliste.pdf')->assertForbidden();
    }
}
