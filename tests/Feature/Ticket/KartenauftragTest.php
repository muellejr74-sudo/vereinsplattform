<?php

namespace Tests\Feature\Ticket;

use App\Modules\Kern\Models\Mandant;
use App\Modules\Kern\Models\Veranstaltung;
use App\Modules\Kern\Models\Vorstellung;
use App\Modules\Ticket\Models\Kartenauftrag;
use App\Modules\Ticket\Models\Ticket;
use App\Modules\Ticket\Models\Verkaufsstelle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Kartenaufträge, Kontingent und Ticketnummern (E24–E31, E39, E45, E113, E116). */
class KartenauftragTest extends TicketTestCase
{
    private function tickets(): Collection
    {
        return $this->im($this->tvm, fn () => Ticket::orderBy('id')->get());
    }

    public function test_vorverkaufsstelle_bestellt_und_belegt_noch_kein_kontingent(): void
    {
        $auftrag = $this->bestellen([$this->erwachsene->id => 20, $this->kinder->id => 5]);

        $this->assertSame('angefordert', $auftrag->status);
        $this->assertSame($this->baeckerei->id, $auftrag->verkaufsstelle_id);
        $this->assertCount(0, $this->tickets(), 'Erst die Bestätigung belegt das Kontingent (E30).');
        $this->get('/tickets/auftraege')->assertOk()->assertSee('#'.$auftrag->id);
        $this->assertDatabaseHas('protokoll', ['aktion' => 'ticket.auftrag_angefordert', 'mandant_id' => $this->tvm->id]);
    }

    public function test_druckstelle_bestaetigt_mit_angepasster_menge(): void
    {
        $auftrag = $this->bestellen([$this->erwachsene->id => 20, $this->kinder->id => 5]);
        $positionen = $this->im($this->tvm, fn () => $auftrag->positionen()->orderBy('id')->get());

        $this->als($this->druckstelle())->post('/tickets/auftraege/'.$auftrag->id.'/bestaetigen', [
            'mengen' => [$positionen[0]->id => 18, $positionen[1]->id => 5],
        ])->assertSessionHasNoErrors();

        $tickets = $this->tickets();
        $this->assertCount(23, $tickets);
        $this->assertSame('TVM-26-00001', $tickets->first()->nummer);
        $this->assertSame('TVM-26-00023', $tickets->last()->nummer);
        $this->assertSame(18, $tickets->where('preis_cent', 1200)->count());
        $this->assertTrue($tickets->every(fn ($t) => $t->status === 'erzeugt' && strlen($t->pruefzeichen) === 6));
        $this->assertSame('bestaetigt', $auftrag->fresh()->status);

        $this->get('/tickets/veranstaltungen/'.$this->theater->id)->assertSee('Belegt: <strong>23</strong>', false)->assertSee('Frei: <strong>27</strong>', false);
    }

    public function test_kontingent_wird_nie_ueberschritten(): void
    {
        $druck = $this->druckstelle();
        $a = $this->bestellen([$this->erwachsene->id => 30]);
        $b = $this->bestellen([$this->erwachsene->id => 30]);

        $this->als($druck)->post('/tickets/auftraege/'.$a->id.'/bestaetigen')->assertSessionHasNoErrors();
        $this->post('/tickets/auftraege/'.$b->id.'/bestaetigen')->assertSessionHasErrors('auftrag');
        $this->assertStringContainsString('20 frei', session('errors')->first('auftrag'));

        $this->assertCount(30, $this->tickets());
        $this->assertSame('angefordert', $b->fresh()->status);

        // Mit angepasster Menge passt es genau.
        $pos = $this->im($this->tvm, fn () => $b->positionen()->first());
        $this->post('/tickets/auftraege/'.$b->id.'/bestaetigen', ['mengen' => [$pos->id => 20]])->assertSessionHasNoErrors();
        $this->assertCount(50, $this->tickets());
    }

    public function test_bestellformular_zeigt_mengenfelder_und_hinweis_ohne_kategorien(): void
    {
        $this->als($this->verkaeufer)->get('/tickets/auftraege/neu')->assertOk()
            ->assertSee('name="mengen['.$this->erwachsene->id.']"', false)->assertDontSee('noch keine Kartenkategorien');

        $ohne = $this->im($this->tvm, function () {
            $v = Veranstaltung::create(['name' => 'Kabarettabend']);
            Vorstellung::create(['veranstaltung_id' => $v->id, 'saal_id' => $this->vorstellung->saal_id,
                'einlass_um' => '2026-11-25 19:00', 'beginn_um' => '2026-11-25 20:00', 'kontingent' => 80]);

            return $v;
        });

        $seite = $this->get('/tickets/auftraege/neu');
        $seite->assertSee('Für „Kabarettabend“ sind noch keine Kartenkategorien', false)->assertSee('Bitte beim Verein melden.');
        $this->assertMatchesRegularExpression('/<button type="submit" class="knopf"\s+disabled/', $seite->getContent(), 'Erste Vorstellung (Kabarett) hat keine Kategorien → Anfordern gesperrt.');

        // Wer Kategorien pflegen darf, bekommt den direkten Weg dorthin.
        $this->baeckerei->benutzer()->attach($pfleger = $this->personMitRechten($this->tvm, ['ticket.kategorien']));
        $this->als($pfleger)->get('/tickets/auftraege/neu')->assertSee('/tickets/veranstaltungen/'.$ohne->id, false);
    }

    public function test_anforderung_hoechstens_so_viele_wie_frei(): void
    {
        $this->als($this->verkaeufer)->get('/tickets/auftraege/neu')->assertSee('data-frei="50"', false);
        $this->post('/tickets/auftraege', ['art' => 'karten', 'verkaufsstelle_id' => $this->baeckerei->id, 'vorstellung_id' => $this->vorstellung->id,
            'mengen' => [$this->erwachsene->id => 40, $this->kinder->id => 11]])->assertSessionHasErrors('auftrag');
        $this->assertStringContainsString('nur noch 50 Plätze frei', session('errors')->first('auftrag'));
        $this->assertSame(0, $this->im($this->tvm, fn () => Kartenauftrag::count()));

        $this->bestellen([$this->erwachsene->id => 50]);
    }

    public function test_ohne_kontingent_keine_bestaetigung(): void
    {
        $this->vorstellung->forceFill(['kontingent' => null])->save();
        $auftrag = $this->bestellen([$this->erwachsene->id => 2]);

        $this->als($this->druckstelle())->post('/tickets/auftraege/'.$auftrag->id.'/bestaetigen')->assertSessionHasErrors('auftrag');
        $this->assertCount(0, $this->tickets());
    }

    public function test_auftrag_kann_nicht_zweimal_bestaetigt_werden(): void
    {
        $auftrag = $this->bestellen([$this->erwachsene->id => 2]);
        $this->als($this->druckstelle())->post('/tickets/auftraege/'.$auftrag->id.'/bestaetigen')->assertSessionHasNoErrors();
        $this->post('/tickets/auftraege/'.$auftrag->id.'/bestaetigen')->assertSessionHasErrors('auftrag');

        $this->assertCount(2, $this->tickets());
    }

    public function test_nummern_laufen_fortlaufend_je_verein_und_beginnen_jedes_jahr_neu(): void
    {
        $druck = $this->druckstelle();
        $a = $this->bestellen([$this->erwachsene->id => 2]);
        $b = $this->bestellen([$this->kinder->id => 1]);
        $this->als($druck)->post('/tickets/auftraege/'.$a->id.'/bestaetigen');
        $this->post('/tickets/auftraege/'.$b->id.'/bestaetigen');
        $this->assertSame(['TVM-26-00001', 'TVM-26-00002', 'TVM-26-00003'], $this->tickets()->pluck('nummer')->all());

        // Neues Jahr der Erzeugung → neue Zählung (E113).
        $this->travelTo(Carbon::parse('2027-01-03 10:00'));
        $this->vorstellung->forceFill(['beginn_um' => '2027-01-20 19:30', 'einlass_um' => '2027-01-20 18:30'])->save();
        $c = $this->bestellen([$this->erwachsene->id => 1]);
        $this->als($druck)->post('/tickets/auftraege/'.$c->id.'/bestaetigen')->assertSessionHasNoErrors();
        $this->assertSame('TVM-27-00001', $this->tickets()->last()->nummer);
    }

    public function test_preisabweichung_fuer_eine_vorstellung(): void
    {
        $this->als($this->personMitRechten($this->tvm, ['ticket.kategorien']))
            ->put('/tickets/veranstaltungen/'.$this->theater->id.'/vorstellungen/'.$this->vorstellung->id.'/preise', ['preise' => [$this->erwachsene->id => '10,50']])
            ->assertSessionHasNoErrors();

        $auftrag = $this->bestellen([$this->erwachsene->id => 1]);
        $this->als($this->druckstelle())->post('/tickets/auftraege/'.$auftrag->id.'/bestaetigen');

        $this->assertSame(1050, $this->tickets()->first()->preis_cent);
    }

    public function test_drucken_uebergeben_und_nachdruck(): void
    {
        $druck = $this->druckstelle();
        $auftrag = $this->bestellen([$this->erwachsene->id => 3]);
        $this->als($druck)->post('/tickets/auftraege/'.$auftrag->id.'/bestaetigen');

        $pdf = $this->get('/tickets/auftraege/'.$auftrag->id.'/etiketten.pdf');
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->assertSame(3, substr_count($pdf->getContent(), '/Type /Page') - substr_count($pdf->getContent(), '/Type /Pages'), 'Eine Seite je Etikett (E111).');

        // Übergeben geht erst nach dem Druck.
        $this->post('/tickets/auftraege/'.$auftrag->id.'/uebergeben')->assertSessionHasErrors('auftrag');

        $this->post('/tickets/auftraege/'.$auftrag->id.'/gedruckt')->assertSessionHasNoErrors();
        $this->assertTrue($this->tickets()->every(fn ($t) => $t->status === 'gedruckt' && $t->druckanzahl === 1));

        $ticket = $this->tickets()->first();
        $this->post('/tickets/karten/'.$ticket->id.'/nachdruck')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame(2, $ticket->fresh()->druckanzahl);
        $this->assertSame($ticket->nummer, $ticket->fresh()->nummer, 'Nachdruck mit derselben Nummer (E117).');
        $this->assertDatabaseHas('protokoll', ['aktion' => 'ticket.nachdruck']);

        $this->post('/tickets/auftraege/'.$auftrag->id.'/uebergeben')->assertSessionHasNoErrors();
        $this->assertTrue($this->tickets()->every(fn ($t) => $t->status === 'ausgegeben'));
        $this->assertSame('ausgegeben', $auftrag->fresh()->status);
    }

    public function test_vorverkaufsstelle_darf_nicht_bestaetigen_und_sieht_nur_eigene_auftraege(): void
    {
        $auftrag = $this->bestellen([$this->erwachsene->id => 2]);

        $this->als($this->verkaeufer)->post('/tickets/auftraege/'.$auftrag->id.'/bestaetigen')->assertForbidden();
        $this->get('/tickets/auftraege/'.$auftrag->id.'/etiketten.pdf')->assertForbidden();

        // Andere Vorverkaufsstelle sieht den Auftrag nicht.
        $anderer = $this->personMitRechten($this->tvm, []);
        $kiosk = $this->im($this->tvm, fn () => Verkaufsstelle::create(['name' => 'Kiosk', 'art' => 'vorverkauf']));
        $kiosk->benutzer()->attach($anderer);
        $this->als($anderer)->get('/tickets/auftraege/'.$auftrag->id)->assertNotFound();
        $this->get('/tickets/auftraege')->assertDontSee('#'.$auftrag->id);

        // Für fremde Stelle bestellen ist verboten.
        $this->post('/tickets/auftraege', ['art' => 'karten', 'verkaufsstelle_id' => $this->baeckerei->id,
            'vorstellung_id' => $this->vorstellung->id, 'mengen' => [$this->erwachsene->id => 1]])->assertForbidden();
    }

    public function test_zurueckziehen_vor_bestaetigung(): void
    {
        $auftrag = $this->bestellen([$this->erwachsene->id => 2]);

        $this->als($this->verkaeufer)->post('/tickets/auftraege/'.$auftrag->id.'/zurueckziehen')->assertSessionHasNoErrors();
        $this->assertSame('zurueckgezogen', $auftrag->fresh()->status);
    }

    public function test_ohne_ticketmodul_kein_zugang(): void
    {
        $this->tvm->setzeModule([]);

        $this->als($this->druckstelle())->get('/tickets')->assertForbidden();
        $this->get('/')->assertDontSee('>Tickets<', false);
    }

    public function test_person_ohne_ticketbezug_hat_keinen_zugang(): void
    {
        $this->als($this->personMitRechten($this->tvm, ['benutzer.verwalten']))->get('/tickets')->assertForbidden();
    }

    public function test_andere_vereine_sehen_nichts(): void
    {
        $auftrag = $this->bestellen([$this->erwachsene->id => 2]);
        $svb = Mandant::factory()->create(['kuerzel' => 'SVB']);
        $svb->setzeModule(['ticket']);
        $fremd = $this->personMitRechten($svb, ['ticket.drucken']);

        $this->als($fremd, $svb)->get('/tickets/auftraege/'.$auftrag->id)->assertNotFound();
        $this->post('/tickets/auftraege/'.$auftrag->id.'/bestaetigen')->assertNotFound();
        $this->assertSame('angefordert', $this->im($this->tvm, fn () => Kartenauftrag::find($auftrag->id))->status);
    }
}
