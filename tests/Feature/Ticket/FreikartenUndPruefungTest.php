<?php

namespace Tests\Feature\Ticket;

use App\Models\User;
use App\Modules\Ticket\Models\Kartenauftrag;
use App\Modules\Ticket\Models\Ticket;
use App\Modules\Ticket\Models\Ticketkategorie;
use App\Modules\Ticket\Models\Verkaufsstelle;

/** Freikarten-Stapel (E25, E114, E114b, E114c, E118) und Prüfseite des QR-Codes (E112). */
class FreikartenUndPruefungTest extends TicketTestCase
{
    public function test_freikarten_stapel_mit_namen_und_position(): void
    {
        $auftrag = $this->bestellen([], 'freikarten', 'Sparkasse', 10);
        $this->als($this->druckstelle())->post('/tickets/auftraege/'.$auftrag->id.'/bestaetigen')->assertSessionHasNoErrors();

        $tickets = $this->im($this->tvm, fn () => Ticket::orderBy('id')->get());
        $this->assertCount(10, $tickets);
        $this->assertTrue($tickets->every(fn ($t) => $t->ist_freikarte && $t->preis_cent === 0 && $t->name === 'Sparkasse'));
        $this->assertSame('3/10', $tickets[2]->stapelText());
        $this->assertSame(Ticketkategorie::FREIKARTE, $this->im($this->tvm, fn () => $tickets[0]->kategorie->name));

        $this->get('/tickets/veranstaltungen/'.$this->theater->id)->assertSee('davon 10 Freikarten');
    }

    public function test_freikarten_brauchen_einen_namen(): void
    {
        $this->als($this->verkaeufer)->post('/tickets/auftraege', [
            'art' => 'freikarten', 'verkaufsstelle_id' => $this->baeckerei->id, 'vorstellung_id' => $this->vorstellung->id, 'anzahl' => 2,
        ])->assertSessionHasErrors('auftrag');
    }

    public function test_verein_intern_mit_recht_reservierungen(): void
    {
        $person = $this->personMitRechten($this->tvm, ['ticket.reservierungen']);

        $this->als($person)->get('/tickets/auftraege/neu?art=freikarten')->assertOk()->assertSee('Verein (intern');
        $this->post('/tickets/auftraege', ['art' => 'freikarten', 'verkaufsstelle_id' => '', 'vorstellung_id' => $this->vorstellung->id,
            'anzahl' => 4, 'name' => 'Bürgermeister'])->assertSessionHasNoErrors();

        $auftrag = $this->im($this->tvm, fn () => Kartenauftrag::latest('id')->first());
        $this->assertNull($auftrag->verkaufsstelle_id);
        $this->get('/tickets/auftraege')->assertSee('Verein (intern)');

        // Intern geht nur für Freikarten, nicht für Verkaufskarten.
        $this->post('/tickets/auftraege', ['art' => 'karten', 'verkaufsstelle_id' => '', 'vorstellung_id' => $this->vorstellung->id,
            'mengen' => [$this->erwachsene->id => 1]])->assertForbidden();
    }

    public function test_pruefseite_zeigt_nur_gueltigkeit_ohne_namen(): void
    {
        $auftrag = $this->bestellen([], 'freikarten', 'Familie Geheim', 1);
        $this->als($this->druckstelle())->post('/tickets/auftraege/'.$auftrag->id.'/bestaetigen');
        $ticket = $this->im($this->tvm, fn () => Ticket::firstOrFail());
        $this->post('/abmelden');

        $this->assertSame(url('/k/'.$ticket->nummer.'.'.$ticket->pruefzeichen), $ticket->qrInhalt());

        $this->get('/k/'.$ticket->nummer.'.'.$ticket->pruefzeichen)->assertOk()
            ->assertSee('Gültige Eintrittskarte')->assertSee('Der Hauptmann von Köpenick')->assertDontSee('Familie Geheim');

        // Abgetippte Nummer mit falschem Prüfzeichen wird nicht anerkannt (E14d).
        $this->get('/k/'.$ticket->nummer.'.AAAAAA')->assertOk()->assertSee('nicht gültig');
        $this->get('/k/TVM-26-99999.AAAAAA')->assertOk()->assertSee('nicht gültig');

        $this->im($this->tvm, fn () => $ticket->update(['status' => 'storniert']));
        $this->get('/k/'.$ticket->nummer.'.'.$ticket->pruefzeichen)->assertSee('storniert');
    }

    public function test_kategorien_und_kontingent_pflegen(): void
    {
        $person = $this->personMitRechten($this->tvm, ['ticket.kategorien', 'ticket.kontingente']);
        $this->als($person);

        $this->post('/tickets/veranstaltungen/'.$this->theater->id.'/kategorien', ['name' => 'Ermäßigt', 'preis' => '8,50'])->assertSessionHasNoErrors();
        $this->post('/tickets/veranstaltungen/'.$this->theater->id.'/kategorien', ['name' => 'freikarte', 'preis' => '0'])->assertSessionHasErrors('name');
        $this->post('/tickets/veranstaltungen/'.$this->theater->id.'/kategorien', ['name' => 'X', 'preis' => 'zwölf'])->assertSessionHasErrors('preis');
        $this->assertSame(850, $this->im($this->tvm, fn () => Ticketkategorie::where('name', 'Ermäßigt')->value('preis_cent')));

        // Kontingent darf nicht unter die belegten Plätze fallen.
        $auftrag = $this->bestellen([$this->erwachsene->id => 10]);
        $this->als($this->druckstelle())->post('/tickets/auftraege/'.$auftrag->id.'/bestaetigen');
        $this->als($person)->put('/tickets/veranstaltungen/'.$this->theater->id.'/vorstellungen/'.$this->vorstellung->id.'/kontingent', ['kontingent' => 5])
            ->assertSessionHasErrors('kontingent');
        $this->put('/tickets/veranstaltungen/'.$this->theater->id.'/vorstellungen/'.$this->vorstellung->id.'/kontingent', ['kontingent' => 300])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('protokoll', ['aktion' => 'ticket.kontingent_geaendert']);

        // Benutzte Kategorie kann nicht gelöscht werden.
        $this->delete('/tickets/veranstaltungen/'.$this->theater->id.'/kategorien/'.$this->erwachsene->id)->assertSessionHasErrors('kategorie');
    }

    public function test_verkaufsstellen_nur_mit_vereinsmitgliedern(): void
    {
        $verwalter = $this->personMitRechten($this->tvm, ['ticket.verkaufsstellen']);
        $fremd = User::factory()->create();
        $this->als($verwalter);

        $this->post('/tickets/verkaufsstellen', ['name' => 'Abendkasse', 'art' => 'abendkasse', 'benutzer' => [$fremd->id]])->assertSessionHasErrors('benutzer.0');
        $this->post('/tickets/verkaufsstellen', ['name' => 'Abendkasse', 'art' => 'abendkasse', 'benutzer' => [$this->verkaeufer->id]])->assertSessionHasNoErrors();

        $stelle = $this->im($this->tvm, fn () => Verkaufsstelle::where('art', 'abendkasse')->firstOrFail());
        $this->assertTrue($stelle->istZugeordnet($this->verkaeufer));
    }
}
