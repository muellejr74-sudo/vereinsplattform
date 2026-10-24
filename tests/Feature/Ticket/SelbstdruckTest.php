<?php

namespace Tests\Feature\Ticket;

use App\Modules\Ticket\Models\Kartenauftrag;
use App\Modules\Ticket\Models\Ticket;
use App\Modules\Ticket\Models\Verkaufsstelle;

/** Verkaufsstelle druckt selbst (E128). */
class SelbstdruckTest extends TicketTestCase
{
    private function selbstdruckEinschalten(): void
    {
        $this->als($this->personMitRechten($this->tvm, ['ticket.verkaufsstellen']))
            ->put('/tickets/verkaufsstellen/'.$this->baeckerei->id, ['name' => 'Bäckerei Müller', 'art' => 'vorverkauf', 'aktiv' => 1, 'druckt_selbst' => 1,
                'benutzer' => [$this->verkaeufer->id]])->assertSessionHasNoErrors();
        $this->assertTrue($this->baeckerei->fresh()->druckt_selbst);
    }

    public function test_ohne_haken_kein_selbstdruck(): void
    {
        $this->als($this->verkaeufer)->get('/tickets')->assertDontSee('Karten verkaufen und drucken');
        $this->get('/tickets/verkaufen')->assertForbidden();
    }

    public function test_verkaufen_und_sofort_drucken(): void
    {
        $this->selbstdruckEinschalten();
        $this->als($this->verkaeufer)->get('/tickets')->assertSee('Karten verkaufen und drucken');
        $this->get('/tickets/verkaufen')->assertOk()->assertSee('data-frei="50"', false)
            ->assertSee('data-preise=\'{"'.$this->erwachsene->id.'":1200,"'.$this->kinder->id.'":600}\'', false);

        $this->post('/tickets/verkaufen', ['verkaufsstelle_id' => $this->baeckerei->id, 'vorstellung_id' => $this->vorstellung->id,
            'mengen' => [$this->erwachsene->id => 2, $this->kinder->id => 1]])->assertSessionHasNoErrors();

        $auftrag = $this->im($this->tvm, fn () => Kartenauftrag::latest('id')->firstOrFail());
        $this->assertSame('ausgegeben', $auftrag->status);
        $tickets = $this->im($this->tvm, fn () => Ticket::orderBy('id')->get());
        $this->assertCount(3, $tickets);
        $this->assertTrue($tickets->every(fn ($t) => $t->status === 'ausgegeben' && $t->verkaufsstelle_id === $this->baeckerei->id && $t->druckanzahl === 1));

        $this->get('/tickets/verkaufen/'.$auftrag->id)->assertOk()->assertSee('3 Karten erzeugt')->assertSee('30,00 €');
        $pdf = $this->get('/tickets/verkaufen/'.$auftrag->id.'/etiketten.pdf');
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame(3, substr_count($pdf->getContent(), '/Type /Page') - substr_count($pdf->getContent(), '/Type /Pages'));

        // Kassieren im ersten Tab: Betrag des eben erzeugten Verkaufs.
        $this->get('/tickets/verkaufen/kassieren?nach='.($auftrag->id - 1).'&vorstellung='.$this->vorstellung->id)->assertOk()
            ->assertSee('Kassieren')->assertSee('30,00 €')->assertSee('data-betrag="3000"', false);
        $this->get('/tickets/verkaufen/kassieren?nach='.$auftrag->id.'&versuch=8')->assertSee('Kein neuer Verkauf');

        $this->post('/tickets/verkaufen/karten/'.$tickets[0]->id.'/nachdruck')->assertOk();
        $this->assertSame(2, $tickets[0]->fresh()->druckanzahl);
        $this->assertDatabaseHas('protokoll', ['aktion' => 'ticket.selbstdruck', 'akteur' => 'Bärbel Bäckerin']);
    }

    public function test_nicht_mehr_als_frei(): void
    {
        $this->selbstdruckEinschalten();
        $this->als($this->verkaeufer)->post('/tickets/verkaufen', ['verkaufsstelle_id' => $this->baeckerei->id, 'vorstellung_id' => $this->vorstellung->id,
            'mengen' => [$this->erwachsene->id => 51]])->assertSessionHasErrors('verkauf');
        $this->assertSame(0, $this->im($this->tvm, fn () => Ticket::count()));
    }

    public function test_andere_stellen_sehen_nichts(): void
    {
        $this->selbstdruckEinschalten();
        $this->als($this->verkaeufer)->post('/tickets/verkaufen', ['verkaufsstelle_id' => $this->baeckerei->id, 'vorstellung_id' => $this->vorstellung->id,
            'mengen' => [$this->erwachsene->id => 1]]);
        $auftrag = $this->im($this->tvm, fn () => Kartenauftrag::latest('id')->firstOrFail());
        $karte = $this->im($this->tvm, fn () => Ticket::firstOrFail());

        $anderer = $this->personMitRechten($this->tvm, []);
        $kiosk = $this->im($this->tvm, fn () => Verkaufsstelle::create(['name' => 'Kiosk', 'art' => 'vorverkauf', 'druckt_selbst' => true]));
        $kiosk->benutzer()->attach($anderer);

        $this->als($anderer)->get('/tickets/verkaufen/'.$auftrag->id)->assertNotFound();
        $this->get('/tickets/verkaufen/'.$auftrag->id.'/etiketten.pdf')->assertNotFound();
        $this->get('/tickets/verkaufen/kassieren?nach=0&versuch=8')->assertSee('Kein neuer Verkauf'); // fremder Verkauf erscheint nicht
        $this->post('/tickets/verkaufen/karten/'.$karte->id.'/nachdruck')->assertNotFound();
        $this->post('/tickets/verkaufen', ['verkaufsstelle_id' => $this->baeckerei->id, 'vorstellung_id' => $this->vorstellung->id,
            'mengen' => [$this->erwachsene->id => 1]])->assertForbidden();
    }
}
