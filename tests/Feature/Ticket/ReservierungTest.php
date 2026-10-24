<?php

namespace Tests\Feature\Ticket;

use App\Models\User;
use App\Modules\Ticket\Dienste\Kontingent;
use App\Modules\Ticket\Models\Reservierung;
use App\Modules\Ticket\Models\Ticket;
use App\Modules\Ticket\Models\Ticketeinstellung;
use Illuminate\Support\Collection;

/** Reservierungen gedruckt, digital und mit Vorkasse (E40, E43, E114a, E120, E123–E127). */
class ReservierungTest extends TicketTestCase
{
    private User $buero;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buero = $this->personMitRechten($this->tvm, ['ticket.reservierungen'], ['name' => 'Rita Reservierung']);
    }

    private function reservieren(array $mengen, string $art, array $extra = [], ?User $als = null): Reservierung
    {
        $this->als($als ?? $this->buero)->post('/tickets/reservierungen', array_merge([
            'verkaufsstelle_id' => '', 'vorstellung_id' => $this->vorstellung->id, 'art' => $art, 'mengen' => $mengen, 'name' => 'Familie Meier',
        ], $extra))->assertSessionHasNoErrors();

        return $this->im($this->tvm, fn () => Reservierung::latest('id')->firstOrFail());
    }

    private function tickets(): Collection
    {
        return $this->im($this->tvm, fn () => Ticket::orderBy('id')->get());
    }

    private function frei(): int
    {
        return app(Kontingent::class)->frei($this->vorstellung->fresh());
    }

    public function test_gedruckte_reservierung_erzeugt_sofort_karten_und_druckauftrag(): void
    {
        $r = $this->reservieren([$this->erwachsene->id => 15, $this->kinder->id => 5], 'gedruckt', ['name' => 'Liederkranz', 'telefon' => '0711 123456']);

        $this->assertSame('R-0001', $r->kennung());
        $tickets = $this->tickets();
        $this->assertCount(20, $tickets);
        $this->assertTrue($tickets->every(fn ($t) => $t->status === 'erzeugt' && $t->name === 'Liederkranz' && $t->reservierung_id === $r->id));
        $this->assertSame('20/20', $tickets->last()->stapelText());
        $this->im($this->tvm, fn () => $this->assertSame(30, $this->frei()));

        // Druckstelle: ohne Bestätigungsschritt direkt drucken, danach „an der Abendkasse hinterlegt“.
        $auftrag = $r->kartenauftrag;
        $this->assertSame(['reservierung', 'bestaetigt'], [$auftrag->art, $auftrag->status]);
        $this->als($this->druckstelle())->get('/tickets/auftraege/'.$auftrag->id.'/etiketten.pdf')->assertOk();
        $this->post('/tickets/auftraege/'.$auftrag->id.'/gedruckt')->assertSessionHasNoErrors();
        $this->get('/tickets/auftraege/'.$auftrag->id)->assertSee('an der Abendkasse hinterlegt');
        $this->post('/tickets/auftraege/'.$auftrag->id.'/uebergeben')->assertSessionHasErrors('auftrag');
    }

    public function test_digitale_reservierung_belegt_kontingent_ohne_karten(): void
    {
        $r = $this->reservieren([$this->erwachsene->id => 4], 'digital');

        $this->assertCount(0, $this->tickets());
        $this->im($this->tvm, fn () => $this->assertSame(46, $this->frei()));
        $this->als($this->druckstelle())->get('/tickets/veranstaltungen/'.$this->theater->id)->assertSee('Belegt: <strong>4</strong>', false);
        $this->assertSame('reserviert – Abholung an der Abendkasse', $this->im($this->tvm, fn () => $r->fresh()->statusText()));
    }

    public function test_reservierung_nur_im_rahmen_des_kontingents(): void
    {
        $this->reservieren([$this->erwachsene->id => 45], 'digital');

        $this->als($this->buero)->post('/tickets/reservierungen', [
            'verkaufsstelle_id' => '', 'vorstellung_id' => $this->vorstellung->id, 'art' => 'gedruckt', 'mengen' => [$this->erwachsene->id => 6], 'name' => 'Zu viele',
        ])->assertSessionHasErrors('reservierung');
        $this->assertStringContainsString('5 frei', session('errors')->first('reservierung'));
    }

    public function test_vorkasse_braucht_anschrift_und_zeigt_betrag_zweck_und_iban(): void
    {
        $this->als($this->buero)->post('/tickets/reservierungen', [
            'verkaufsstelle_id' => '', 'vorstellung_id' => $this->vorstellung->id, 'art' => 'vorkasse', 'mengen' => [$this->erwachsene->id => 3], 'name' => 'Meier',
            'zahlungsfrist' => today()->addDays(7)->toDateString(),
        ])->assertSessionHasErrors('reservierung');

        $this->im($this->tvm, fn () => Ticketeinstellung::create(['kontoinhaber' => 'Theaterverein', 'iban' => 'DE89370400440532013000']));
        $r = $this->reservieren([$this->erwachsene->id => 2, $this->kinder->id => 1], 'vorkasse', [
            'name' => 'Meier', 'strasse' => 'Hauptstraße 1', 'plz' => '70173', 'ort' => 'Stuttgart', 'zahlungsfrist' => today()->addDays(7)->toDateString(),
        ]);

        $this->assertCount(0, $this->tickets(), 'Vor dem Geldeingang werden keine Karten erzeugt (E124).');
        $this->get('/tickets/reservierungen/'.$r->id)->assertOk()
            ->assertSee('30,00 €')->assertSee('TVM R-0001 Meier')->assertSee('DE89 3704 0044 0532 0130 00')
            ->assertSee('Zahlung offen')->assertDontSee('Bezahlt, zum Versand freigeben');
    }

    public function test_zahlungseingang_nur_mit_eigenem_recht_dann_druck_mit_adressaufkleber_und_versand(): void
    {
        $r = $this->reservieren([$this->erwachsene->id => 3], 'vorkasse', [
            'name' => 'Meier', 'strasse' => 'Hauptstraße 1', 'plz' => '70173', 'ort' => 'Stuttgart', 'zahlungsfrist' => today()->addDays(7)->toDateString(),
        ]);

        $this->als($this->buero)->post('/tickets/reservierungen/'.$r->id.'/bezahlt')->assertForbidden();
        $this->als($this->druckstelle())->post('/tickets/reservierungen/'.$r->id.'/bezahlt')->assertForbidden();

        $kassierer = $this->personMitRechten($this->tvm, ['ticket.zahlungseingang'], ['name' => 'Karl Kasse']);
        $this->als($kassierer)->get('/tickets/reservierungen/'.$r->id)->assertSee('Bezahlt, zum Versand freigeben');
        $this->post('/tickets/reservierungen/'.$r->id.'/bezahlt')->assertSessionHasNoErrors();
        $this->post('/tickets/reservierungen/'.$r->id.'/bezahlt')->assertSessionHasErrors('reservierung');

        $tickets = $this->tickets();
        $this->assertCount(3, $tickets);
        $this->assertTrue($tickets->every(fn ($t) => $t->zahlungsart === 'ueberweisung' && $t->status === 'erzeugt' && $t->verkauf_ueber === 'vorkasse'));
        $this->assertDatabaseHas('protokoll', ['aktion' => 'ticket.zahlung_eingegangen', 'akteur' => 'Karl Kasse']);
        $this->im($this->tvm, fn () => $this->assertSame(47, $this->frei()));

        // Druckstelle: Adressaufkleber + 3 Karten = 4 Etiketten.
        $auftrag = $r->fresh()->kartenauftrag;
        $this->assertSame('versand', $auftrag->art);
        $pdf = $this->als($this->druckstelle())->get('/tickets/auftraege/'.$auftrag->id.'/etiketten.pdf')->getContent();
        $this->assertSame(4, substr_count($pdf, '/Type /Page') - substr_count($pdf, '/Type /Pages'));

        $this->post('/tickets/auftraege/'.$auftrag->id.'/uebergeben')->assertSessionHasErrors('auftrag'); // erst drucken
        $this->post('/tickets/auftraege/'.$auftrag->id.'/gedruckt');
        $this->post('/tickets/auftraege/'.$auftrag->id.'/uebergeben')->assertSessionHasNoErrors();

        $this->assertSame('versendet', $r->fresh()->status);
        $this->assertTrue($this->tickets()->every(fn ($t) => $t->status === 'verkauft'));
    }

    public function test_ueberfaellige_vorkasse_stornieren_oder_auf_abholung_umstellen(): void
    {
        $daten = ['strasse' => 'Weg 2', 'plz' => '12345', 'ort' => 'Dorf', 'zahlungsfrist' => today()->addDays(3)->toDateString()];
        $a = $this->reservieren([$this->erwachsene->id => 2], 'vorkasse', $daten + ['name' => 'Säumig']);
        $b = $this->reservieren([$this->erwachsene->id => 3], 'vorkasse', $daten + ['name' => 'Spätzahler']);

        $this->travel(5)->days();
        $this->get('/tickets/reservierungen?status=ueberfaellig')->assertSee('Säumig')->assertSee('Spätzahler');
        $this->get('/tickets/reservierungen/'.$a->id)->assertSee('überfällig');

        $this->post('/tickets/reservierungen/'.$b->id.'/abholung')->assertSessionHasNoErrors();
        $this->assertSame('digital', $b->fresh()->art);

        $this->post('/tickets/reservierungen/ueberfaellig/stornieren', ['ids' => [$a->id]])->assertSessionHasNoErrors();
        $this->assertSame('storniert', $a->fresh()->status);
        $this->im($this->tvm, fn () => $this->assertSame(47, $this->frei()));
    }

    public function test_vorverkaufsstelle_reserviert_fuer_sich_und_sieht_nur_eigene(): void
    {
        $eigene = $this->reservieren([$this->erwachsene->id => 2], 'digital', ['verkaufsstelle_id' => $this->baeckerei->id, 'name' => 'Kunde Bäckerei'], $this->verkaeufer);
        $this->assertSame($this->baeckerei->id, $eigene->verkaufsstelle_id);
        $fremd = $this->reservieren([$this->erwachsene->id => 1], 'digital', ['name' => 'Vereinskunde']);

        $this->als($this->verkaeufer)->get('/tickets/reservierungen')->assertSee('Kunde Bäckerei')->assertDontSee('Vereinskunde');
        $this->get('/tickets/reservierungen/'.$fremd->id)->assertNotFound();
        $this->post('/tickets/reservierungen', ['verkaufsstelle_id' => '', 'vorstellung_id' => $this->vorstellung->id, 'art' => 'digital',
            'mengen' => [$this->erwachsene->id => 1], 'name' => 'X'])->assertForbidden();
        $this->post('/tickets/reservierungen/'.$eigene->id.'/stornieren')->assertSessionHasNoErrors();
    }

    public function test_telefon_und_anschrift_nie_auf_der_pruefseite(): void
    {
        $r = $this->reservieren([$this->erwachsene->id => 1], 'gedruckt', ['name' => 'Meier', 'telefon' => '0711 999888']);
        $t = $this->im($this->tvm, fn () => Ticket::firstOrFail());
        $this->post('/abmelden');

        $this->get('/k/'.$t->nummer.'.'.$t->pruefzeichen)->assertOk()->assertDontSee('999888')->assertDontSee('Meier');
        $this->assertNotNull($r);
    }

    public function test_einstellungen_und_bankverbindung_mit_getrennten_rechten(): void
    {
        $this->als($this->buero)->put('/tickets/einstellungen', ['reservierung_standard' => 'digital', 'nicht_abgeholt_minuten' => -15, 'zahlungsfrist_tage' => 10])
            ->assertSessionHasNoErrors();
        $this->put('/tickets/einstellungen/bank', ['iban' => 'DE89370400440532013000'])->assertForbidden();
        $this->get('/tickets/reservierungen/neu')->assertSee('value="digital" checked', false)->assertSee(today()->addDays(10)->toDateString());

        $kassierer = $this->personMitRechten($this->tvm, ['ticket.zahlungseingang']);
        $this->als($kassierer)->put('/tickets/einstellungen/bank', ['iban' => 'DE89 3704 0044 0532 0130 01'])->assertSessionHasErrors('iban');
        $this->put('/tickets/einstellungen/bank', ['kontoinhaber' => 'TV', 'iban' => 'de89 3704 0044 0532 0130 00'])->assertSessionHasNoErrors();
        $this->assertSame('DE89370400440532013000', $this->im($this->tvm, fn () => Ticketeinstellung::first()->iban));
        $this->assertDatabaseHas('protokoll', ['aktion' => 'ticket.bankverbindung_geaendert']);
    }
}
