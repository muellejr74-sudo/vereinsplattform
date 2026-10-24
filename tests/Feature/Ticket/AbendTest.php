<?php

namespace Tests\Feature\Ticket;

use App\Models\User;
use App\Modules\Kern\Dienste\Einsatz;
use App\Modules\Kern\Models\Station;
use App\Modules\Kern\Models\Vorstellung;
use App\Modules\Ticket\Dienste\Kontingent;
use App\Modules\Ticket\Models\Kartenauftrag;
use App\Modules\Ticket\Models\Reservierung;
use App\Modules\Ticket\Models\Ticket;
use App\Modules\Ticket\Models\Verkaufsstelle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Der Abend: Abendkasse, Stornieren, Einlass (E1, E41–E46a, E114a, E119, E121–E123). */
class AbendTest extends TicketTestCase
{
    private User $kasse;

    private User $buero;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kasse = $this->personMitRechten($this->tvm, ['ticket.abendkasse'], ['name' => 'Anna Abendkasse']);
        $this->buero = $this->personMitRechten($this->tvm, ['ticket.reservierungen', 'ticket.drucken']);
    }

    private function reservieren(array $mengen, string $art, string $name): Reservierung
    {
        $this->als($this->buero)->post('/tickets/reservierungen', [
            'verkaufsstelle_id' => '', 'vorstellung_id' => $this->vorstellung->id, 'art' => $art, 'mengen' => $mengen, 'name' => $name,
        ])->assertSessionHasNoErrors();

        return $this->im($this->tvm, fn () => Reservierung::latest('id')->firstOrFail());
    }

    private function zahlen(): array
    {
        return $this->im($this->tvm, fn () => app(Kontingent::class)->uebersicht($this->vorstellung->fresh()));
    }

    private function tickets(): Collection
    {
        return $this->im($this->tvm, fn () => Ticket::orderBy('id')->get());
    }

    private function amAbend(): void
    {
        $this->travelTo(Carbon::parse('2026-12-01 18:45'));
    }

    public function test_abendkasse_braucht_das_recht(): void
    {
        $this->als($this->verkaeufer)->get('/tickets/abendkasse')->assertForbidden();
        $this->als($this->kasse)->get('/tickets/abendkasse')->assertOk()->assertSee('Der Hauptmann von Köpenick');
    }

    public function test_abendkasse_nur_im_zeitfenster_der_vorstellung(): void
    {
        // 20.11., Vorstellung am 01.12. (Einlass 18:30): noch geschlossen.
        $this->als($this->kasse)->get('/tickets/abendkasse')->assertOk()->assertSee('Gerade ist keine Abendkasse geöffnet')->assertSee('öffnet 01.12. um 15:30 Uhr');
        $basis = '/tickets/abendkasse/'.$this->vorstellung->id;
        $this->get($basis)->assertRedirect('/tickets/abendkasse');
        $this->get($basis)->assertSessionHasErrors('kasse');
        $this->post($basis.'/verkauf', ['mengen' => [$this->erwachsene->id => 1], 'zahlungsart' => 'bar'])->assertRedirect('/tickets/abendkasse');
        $this->assertCount(0, $this->tickets());

        $this->travelTo(Carbon::parse('2026-12-01 15:30'));
        $this->get($basis)->assertOk()->assertSee('Karten direkt verkaufen');

        $this->travelTo(Carbon::parse('2026-12-02 04:00')); // ohne Endzeit: Fenster endet um 4:00 Uhr
        $this->get($basis)->assertRedirect('/tickets/abendkasse');
    }

    public function test_spontanverkauf_ohne_etikett_gilt_als_eingelassen(): void
    {
        $this->amAbend();
        $this->als($this->kasse)->get('/tickets/abendkasse')->assertRedirect('/tickets/abendkasse/'.$this->vorstellung->id);
        $this->get('/tickets/abendkasse/'.$this->vorstellung->id.'/verkauf')->assertOk()->assertSee('Bar bezahlt');
        $this->from('/tickets/abendkasse/'.$this->vorstellung->id.'/verkauf')->post('/tickets/abendkasse/'.$this->vorstellung->id.'/verkauf', ['mengen' => [$this->erwachsene->id => 2, $this->kinder->id => 1], 'zahlungsart' => 'bar'])
            ->assertSessionHasNoErrors();
        $this->assertStringContainsString('30,00 € bar', session('hinweis'));

        $t = $this->tickets();
        $this->assertCount(3, $t);
        $this->assertTrue($t->every(fn ($x) => $x->status === 'eingelassen' && $x->zahlungsart === 'bar' && $x->verkauf_ueber === 'abendkasse'));
        $this->assertSame(3, $this->zahlen()['eingelassen']);

        $this->post('/tickets/abendkasse/'.$this->vorstellung->id.'/verkauf', ['mengen' => [$this->erwachsene->id => 48], 'zahlungsart' => 'karte'])
            ->assertSessionHasErrors('kasse');
        $this->post('/tickets/abendkasse/'.$this->vorstellung->id.'/verkauf', ['mengen' => [$this->erwachsene->id => 1]])->assertSessionHasErrors('zahlungsart');
    }

    public function test_reservierungen_ausgeben_auch_teilweise(): void
    {
        $meier = $this->reservieren([$this->erwachsene->id => 4], 'digital', 'Meier');
        $lk = $this->reservieren([$this->erwachsene->id => 15, $this->kinder->id => 5], 'gedruckt', 'Liederkranz');
        $this->post('/tickets/auftraege/'.$lk->kartenauftrag_id.'/gedruckt');
        $this->amAbend();

        // Meier (digital) holt ab und zahlt bar → Karten entstehen, sofort eingelassen (E123).
        $this->als($this->kasse)->get('/tickets/abendkasse/'.$this->vorstellung->id)->assertSee('Karten direkt verkaufen')->assertSee('2 offen');
        $this->get('/tickets/abendkasse/'.$this->vorstellung->id.'/reservierungen?suche=meie')->assertSee('Meier')->assertDontSee('Liederkranz');
        $this->get('/tickets/abendkasse/'.$this->vorstellung->id.'/reservierungen/'.$meier->id)->assertOk()->assertSee('48,00 €');
        $this->post('/tickets/abendkasse/'.$this->vorstellung->id.'/reservierungen/'.$meier->id, ['zahlungsart' => 'bar'])
            ->assertSessionHasNoErrors()->assertRedirect('/tickets/abendkasse/'.$this->vorstellung->id); // zurück ins Menü
        $meierKarten = $this->im($this->tvm, fn () => Ticket::where('reservierung_id', $meier->id)->get());
        $this->assertCount(4, $meierKarten);
        $this->assertTrue($meierKarten->every(fn ($t) => $t->status === 'eingelassen' && $t->zahlungsart === 'bar'));

        // Liederkranz (gedruckt): nur 17 von 20 kommen, Kartenzahlung (E114a).
        $pos = $this->im($this->tvm, fn () => $lk->positionen()->orderBy('id')->get());
        $this->post('/tickets/abendkasse/'.$this->vorstellung->id.'/reservierungen/'.$lk->id, [
            'mengen' => [$pos[0]->id => 13, $pos[1]->id => 4], 'zahlungsart' => 'karte',
        ])->assertSessionHasNoErrors();
        $this->assertStringContainsString('17 Karten ausgegeben · 180,00 € mit Karte', session('hinweis'));
        $lkKarten = $this->im($this->tvm, fn () => Ticket::where('reservierung_id', $lk->id)->get());
        $this->assertSame(17, $lkKarten->where('status', 'verkauft')->count());
        $this->assertSame(3, $lkKarten->where('status', 'storniert')->count());
        $this->assertSame(['abgeholt', 'karte'], [$lk->fresh()->status, $lk->fresh()->zahlungsart]);

        $z = $this->zahlen();
        $this->assertSame(21, $z['belegt']);
        $this->assertSame(29, $z['frei']);
        $this->assertDatabaseHas('protokoll', ['aktion' => 'ticket.reservierung_abgeholt', 'akteur' => 'Anna Abendkasse']);
    }

    public function test_nicht_abgeholte_erst_ab_beginn_dann_mit_einem_klick_stornieren(): void
    {
        $schulz = $this->reservieren([$this->erwachsene->id => 2], 'digital', 'Schulz');
        $lk = $this->reservieren([$this->erwachsene->id => 3], 'gedruckt', 'Bleibt offen');

        $this->travelTo(Carbon::parse('2026-12-01 19:00'));
        $this->als($this->kasse)->get('/tickets/abendkasse/'.$this->vorstellung->id)->assertSee('ab 19:30 Uhr');
        $this->get('/tickets/abendkasse/'.$this->vorstellung->id.'/nicht-abgeholt')->assertSee('erscheint ab <strong>19:30 Uhr</strong>', false);
        $this->post('/tickets/abendkasse/'.$this->vorstellung->id.'/nicht-abgeholt', ['ids' => [$schulz->id]])->assertSessionHasErrors('kasse');
        $this->assertSame('offen', $schulz->fresh()->status);

        $this->travelTo(Carbon::parse('2026-12-01 19:31'));
        $this->get('/tickets/abendkasse/'.$this->vorstellung->id.'/nicht-abgeholt')->assertSee('Schulz – 2 Karten');
        $this->post('/tickets/abendkasse/'.$this->vorstellung->id.'/nicht-abgeholt', ['ids' => [$schulz->id]])->assertSessionHasNoErrors();

        $this->assertSame('storniert', $schulz->fresh()->status);
        $this->assertSame('offen', $lk->fresh()->status, 'Einzelne können offen bleiben (E41).');
        $this->assertSame(3, $this->zahlen()['belegt']);
    }

    public function test_verkauf_aus_dem_gedruckten_abendkassen_bestand(): void
    {
        $abendkasse = $this->im($this->tvm, fn () => Verkaufsstelle::create(['name' => 'Abendkasse', 'art' => 'abendkasse']));
        $abendkasse->benutzer()->attach($this->kasse);
        $this->als($this->kasse)->post('/tickets/auftraege', ['art' => 'karten', 'verkaufsstelle_id' => $abendkasse->id,
            'vorstellung_id' => $this->vorstellung->id, 'mengen' => [$this->erwachsene->id => 5]])->assertSessionHasNoErrors();
        $auftrag = $this->im($this->tvm, fn () => Kartenauftrag::latest('id')->first());
        $this->als($this->buero)->post('/tickets/auftraege/'.$auftrag->id.'/bestaetigen');
        $this->post('/tickets/auftraege/'.$auftrag->id.'/gedruckt');
        $this->post('/tickets/auftraege/'.$auftrag->id.'/uebergeben');
        $karte = $this->tickets()->first();

        // Eine Vorverkaufskarte gehört nicht zum Abendkassen-Bestand.
        $vvk = $this->bestellen([$this->erwachsene->id => 1]);
        $this->als($this->buero)->post('/tickets/auftraege/'.$vvk->id.'/bestaetigen');
        $vvkKarte = $this->tickets()->last();

        $this->amAbend();
        $basis = '/tickets/abendkasse/'.$this->vorstellung->id;
        $this->als($this->kasse)->post($basis.'/bestand', ['code' => $vvkKarte->qrInhalt()])->assertSessionHasErrors('kasse');
        $this->post($basis.'/bestand', ['code' => $karte->qrInhalt()])->assertRedirect($basis.'/bestand/'.$karte->id);
        $this->get($basis.'/bestand/'.$karte->id)->assertSee('12,00 €');
        $this->post($basis.'/bestand/'.$karte->id, ['zahlungsart' => 'bar'])->assertSessionHasNoErrors();

        $karte->refresh();
        $this->assertSame(['verkauft', 'bar'], [$karte->status, $karte->zahlungsart]);
        $this->post($basis.'/bestand', ['code' => $karte->nummer])->assertSessionHasErrors('kasse'); // schon verkauft
    }

    public function test_freikarten_stapel_an_der_abendkasse_ausgeben(): void
    {
        $this->als($this->buero)->post('/tickets/auftraege', ['art' => 'freikarten', 'verkaufsstelle_id' => '', 'vorstellung_id' => $this->vorstellung->id,
            'anzahl' => 5, 'name' => 'Sparkasse'])->assertSessionHasNoErrors();
        $a = $this->im($this->tvm, fn () => Kartenauftrag::latest('id')->first());
        $this->post('/tickets/auftraege/'.$a->id.'/bestaetigen');
        $this->post('/tickets/auftraege/'.$a->id.'/gedruckt');

        $this->amAbend();
        $this->als($this->kasse)->get('/tickets/abendkasse/'.$this->vorstellung->id)->assertSee('Freikarten ausgeben');
        $this->get('/tickets/abendkasse/'.$this->vorstellung->id.'/freikarten')->assertSee('Sparkasse');
        $this->post('/tickets/abendkasse/'.$this->vorstellung->id.'/freikarten/'.$a->id, ['anzahl' => 3])->assertSessionHasNoErrors();

        $t = $this->tickets();
        $this->assertSame(3, $t->where('status', 'ausgegeben')->count());
        $this->assertSame(2, $t->where('status', 'storniert')->count());
    }

    public function test_storno_sammeln_per_scan_und_ausfuehren(): void
    {
        $auftrag = $this->bestellen([$this->erwachsene->id => 20]);
        $this->als($this->buero)->post('/tickets/auftraege/'.$auftrag->id.'/bestaetigen');
        $this->post('/tickets/auftraege/'.$auftrag->id.'/gedruckt');
        $this->post('/tickets/auftraege/'.$auftrag->id.'/uebergeben');
        $karten = $this->tickets();

        $this->als($this->buero)->get('/tickets/stornieren')->assertForbidden();

        $storno = $this->personMitRechten($this->tvm, ['ticket.stornieren'], ['name' => 'Stefan Storno']);
        $this->als($storno)->get('/tickets/stornieren')->assertOk();
        foreach ($karten->take(5) as $k) {
            $this->postJson('/tickets/stornieren/erfassen', ['code' => $k->qrInhalt()])->assertOk()->assertJson(['farbe' => 'gruen']);
        }
        $this->postJson('/tickets/stornieren/erfassen', ['code' => $karten[0]->qrInhalt()])->assertJson(['farbe' => 'gelb']);
        $this->postJson('/tickets/stornieren/erfassen', ['code' => $karten[0]->nummer.'.AAAAAA'])->assertJson(['farbe' => 'rot']);
        $this->postJson('/tickets/stornieren/erfassen', ['code' => $karten[6]->nummer])->assertJson(['farbe' => 'gruen']); // eingetippt ohne Prüfzeichen
        $this->post('/tickets/stornieren/entfernen/'.$karten[6]->id);

        $this->assertSame(20, $this->zahlen()['belegt']);
        $this->post('/tickets/stornieren/ausfuehren')->assertSessionHasNoErrors();
        $this->assertStringContainsString('5 Karten storniert', session('hinweis'));
        $this->assertSame(5, $this->tickets()->where('status', 'storniert')->count());
        $this->assertSame(15, $this->zahlen()['belegt']);
        $this->assertDatabaseHas('protokoll', ['aktion' => 'ticket.storniert', 'akteur' => 'Stefan Storno']);

        // Stornierte Karte erneut: rot.
        $this->postJson('/tickets/stornieren/erfassen', ['code' => $karten[0]->qrInhalt()])->assertJson(['farbe' => 'rot', 'text' => 'bereits storniert']);
    }

    public function test_eingelassene_karten_sind_nicht_stornierbar(): void
    {
        $this->amAbend();
        $this->als($this->kasse)->post('/tickets/abendkasse/'.$this->vorstellung->id.'/verkauf', ['mengen' => [$this->erwachsene->id => 1], 'zahlungsart' => 'bar']);
        $karte = $this->tickets()->first();

        $this->als($this->personMitRechten($this->tvm, ['ticket.stornieren']))
            ->postJson('/tickets/stornieren/erfassen', ['code' => $karte->nummer])->assertJson(['farbe' => 'rot']);
        $this->assertSame('eingelassen', $karte->fresh()->status);
    }

    public function test_einlass_station_gruen_gelb_rot(): void
    {
        $auftrag = $this->bestellen([$this->erwachsene->id => 3]);
        $this->als($this->buero)->post('/tickets/auftraege/'.$auftrag->id.'/bestaetigen');
        $this->post('/tickets/auftraege/'.$auftrag->id.'/gedruckt');
        $this->post('/tickets/auftraege/'.$auftrag->id.'/uebergeben');
        [$a, $b, $c] = $this->tickets()->all();
        $this->im($this->tvm, fn () => $c->update(['status' => 'storniert']));
        $admin = $this->vereinsadmin($this->tvm);

        // Einlass-Gerät über den Einsatz-Code anmelden (E29).
        $this->amAbend();
        $code = $this->im($this->tvm, fn () => app(Einsatz::class)->codeErzeugen($this->vorstellung));
        $this->post('/abmelden');
        $this->post('/einsatz', ['code' => $code]);
        $einlass = $this->im($this->tvm, fn () => Station::where('art', 'einlass')->firstOrFail());
        $antwort = $this->post('/einsatz/station', ['station_id' => $einlass->id]);
        $wert = decrypt(collect($antwort->headers->getCookies())->first(fn ($k) => $k->getName() === Einsatz::COOKIE)->getValue(), false);
        $wert = substr($wert, strpos($wert, '|') + 1);

        $this->withCookie(Einsatz::COOKIE, $wert)->get('/station')->assertRedirect('/station/einlass');
        $this->withCookie(Einsatz::COOKIE, $wert)->get('/station/einlass')->assertOk()->assertSee('Eingelassen');

        $scan = fn (string $inhalt) => $this->withCredentials()->withCookie(Einsatz::COOKIE, $wert)->postJson('/station/einlass', ['code' => $inhalt]);
        $scan($a->qrInhalt())->assertJson(['farbe' => 'gruen', 'zaehler' => ['eingelassen' => 1, 'belegt' => 2]]);
        $scan($a->qrInhalt())->assertJson(['farbe' => 'gelb', 'titel' => 'Schon eingelassen'])->assertJsonFragment(['text' => $a->nummer.' · Erwachsene – eingelassen um 18:45 Uhr.']);
        $scan($c->qrInhalt())->assertJson(['farbe' => 'rot', 'titel' => 'Storniert']);
        $scan($b->nummer)->assertJson(['farbe' => 'rot', 'titel' => 'Ungültig']); // ohne Prüfzeichen nie (E14d)
        $scan('https://example.org/k/'.$b->nummer.'.AAAAAA')->assertJson(['farbe' => 'rot']);

        $this->assertSame('eingelassen', $a->fresh()->status);
        $this->assertStringStartsWith('Einlass', $a->fresh()->eingelassen_durch);

        // Karte einer anderen Vorstellung.
        $andere = $this->im($this->tvm, fn () => Vorstellung::create(['veranstaltung_id' => $this->theater->id, 'saal_id' => $this->vorstellung->saal_id,
            'einlass_um' => '2026-12-02 18:30', 'beginn_um' => '2026-12-02 19:30', 'kontingent' => 10]));
        $this->im($this->tvm, fn () => $b->update(['vorstellung_id' => $andere->id]));
        $scan($b->qrInhalt())->assertJson(['farbe' => 'rot', 'titel' => 'Andere Vorstellung']);
        $this->assertNotNull($admin);
    }

    public function test_einlass_nur_fuer_station_einlass(): void
    {
        $this->amAbend();
        $code = $this->im($this->tvm, fn () => app(Einsatz::class)->codeErzeugen($this->vorstellung));
        $this->post('/einsatz', ['code' => $code]);
        $bedienung = $this->im($this->tvm, fn () => Station::where('art', 'bedienung')->firstOrFail());
        $antwort = $this->post('/einsatz/station', ['station_id' => $bedienung->id]);
        $wert = decrypt(collect($antwort->headers->getCookies())->first(fn ($k) => $k->getName() === Einsatz::COOKIE)->getValue(), false);
        $wert = substr($wert, strpos($wert, '|') + 1);

        $this->withCookie(Einsatz::COOKIE, $wert)->get('/station/einlass')->assertRedirect('/station');
        $this->withCredentials()->withCookie(Einsatz::COOKIE, $wert)->postJson('/station/einlass', ['code' => 'x'])->assertForbidden();
        $this->postJson('/station/einlass', ['code' => 'x'])->assertForbidden();
    }
}
