<?php

namespace Tests\Feature\Kern;

use App\Models\User;
use App\Modules\Kern\Dienste\Stammdaten;
use App\Modules\Kern\Dienste\Tischverwaltung;
use App\Modules\Kern\Mandanten\Mandantenkontext;
use App\Modules\Kern\Models\Mandant;
use App\Modules\Kern\Models\Saal;
use App\Modules\Kern\Models\Tischcode;
use App\Modules\Kern\Models\Tischzuordnung;
use App\Modules\Kern\Models\Veranstaltung;
use App\Modules\Kern\Models\Vorstellung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

/** QR-Codes, Aufkleber und Tischzuordnung (E52–E54a, E68–E74, E104–E109). */
class TischeTest extends TestCase
{
    use RefreshDatabase;

    private Mandant $tvm;

    private Mandant $svb;

    private Veranstaltung $theater;

    private Veranstaltung $sportfest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-20 10:00'));
        $this->tvm = Mandant::factory()->create(['kuerzel' => 'TVM', 'name' => 'Theaterverein']);
        $this->svb = Mandant::factory()->create(['kuerzel' => 'SVB', 'name' => 'Sportverein']);
        $this->theater = $this->veranstaltung($this->tvm, 'Theater 2026', ['2026-12-01 19:30', '2026-12-08 19:30']);
        $this->sportfest = $this->veranstaltung($this->svb, 'Sportfest', ['2026-11-21 14:00']);
    }

    private function veranstaltung(Mandant $verein, string $name, array $termine): Veranstaltung
    {
        app(Stammdaten::class)->sicherstellen($verein);

        return app(Mandantenkontext::class)->fuer($verein, function () use ($name, $termine) {
            $v = Veranstaltung::create(['name' => $name]);
            foreach ($termine as $t) {
                Vorstellung::create(['veranstaltung_id' => $v->id, 'saal_id' => Saal::first()->id, 'beginn_um' => $t]);
            }

            return $v;
        });
    }

    private function zuordner(Mandant $verein): User
    {
        $person = $this->personMitRechten($verein, ['qrcodes.verwalten', 'veranstaltungen.verwalten']);
        $this->actingAs($person)->withSession(['mandant_id' => $verein->id]);

        return $person;
    }

    private function betreiberTisch(int $nummer = 1): Tischcode
    {
        return Tischcode::create(['mandant_id' => null, 'code' => Tischcode::neuerCode(), 'laufnummer' => $nummer, 'bezeichnung' => 'Tisch '.$nummer]);
    }

    private function zuordnungen(Mandant $verein): Collection
    {
        return app(Mandantenkontext::class)->fuer($verein, fn () => Tischzuordnung::all());
    }

    public function test_super_admin_legt_betreiber_tische_an_und_druckt_pdf(): void
    {
        $this->actingAs($this->superAdmin());

        $this->post('/verwaltung/tische', ['anzahl' => 5])->assertRedirect();
        $this->assertSame(5, Tischcode::betreiber()->count());
        $this->assertSame('Tisch 5', Tischcode::betreiber()->orderByDesc('laufnummer')->first()->bezeichnung);

        $antwort = $this->get('/verwaltung/tische.pdf?spalten=3&zeilen=7&rahmen=1');
        $antwort->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $antwort->getContent());
    }

    public function test_codes_sind_kurz_und_nicht_erratbar(): void
    {
        $codes = collect(range(1, 50))->map(fn () => Tischcode::neuerCode());
        $this->assertSame(50, $codes->unique()->count());
        $codes->each(fn ($c) => $this->assertMatchesRegularExpression('/^[ABCDEFGHJKMNPQRSTUVWXYZ2-9]{10}$/', $c));

        $tisch = $this->betreiberTisch();
        $this->assertSame(url('/t/'.$tisch->code), $tisch->adresse());
    }

    public function test_verein_erzeugt_eigene_codes_und_einheitscode(): void
    {
        $this->zuordner($this->tvm);

        $this->post('/verein/tische/codes', ['anzahl' => 12])->assertRedirect();
        $this->post('/verein/tische/codes', ['anzahl' => 1, 'einheitscode' => 1])->assertRedirect();

        $codes = Tischcode::vomVerein($this->tvm->id)->orderBy('laufnummer')->get();
        $this->assertSame(13, $codes->count());
        $this->assertTrue($codes->last()->einheitscode);
        $this->assertSame(0, Tischcode::vomVerein($this->svb->id)->count());

        $pdf = $this->get('/verein/tische/codes.pdf?start=4');
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_scan_eines_angemeldeten_zuordners_fuehrt_zum_formular(): void
    {
        $tisch = $this->betreiberTisch();
        $this->zuordner($this->tvm);

        $this->get('/t/'.$tisch->code)->assertRedirect(route('verein.tische.zuordnen', ['code' => $tisch->code]));
        $this->get(route('verein.tische.zuordnen', ['code' => $tisch->code]))->assertOk()
            ->assertSee('Tisch 1')->assertSee('2026-12-09', false); // Vorschlag: Tag nach der letzten Vorstellung (E71a)

        $this->post('/verein/tische/zuordnen', [
            'code' => $tisch->code, 'veranstaltung_id' => $this->theater->id, 'tischnummer' => '7', 'gueltig_bis' => '2026-12-09',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $z = $this->zuordnungen($this->tvm)->first();
        $this->assertSame('7', $z->tischnummer);
        $this->assertSame($this->theater->id, $z->veranstaltung_id);
        $this->assertDatabaseHas('protokoll', ['aktion' => 'tisch.zugeordnet', 'mandant_id' => $this->tvm->id]);
    }

    public function test_gast_sieht_nach_scan_die_veranstaltung_bzw_die_werbeseite(): void
    {
        $tisch = $this->betreiberTisch();

        $this->get('/t/'.$tisch->code)->assertOk()->assertSee('Werbeseite des Plattformbetreibers');

        app(Tischverwaltung::class)->zuordnen($tisch, $this->theater, '7', Carbon::parse('2026-12-09'), User::factory()->create());
        $this->get('/t/'.$tisch->code)->assertOk()->assertSee('Theater 2026')->assertSee('Tisch 7');

        $this->get('/t/ZZZZZZZZZZ')->assertOk()->assertSee('nicht mehr gültig');
    }

    public function test_verknuepfter_tisch_wird_automatisch_uebernommen(): void
    {
        $tisch = $this->betreiberTisch();
        app(Tischverwaltung::class)->zuordnen($tisch, $this->theater, '7', Carbon::parse('2026-12-09'), User::factory()->create());

        $this->zuordner($this->svb);
        $this->get(route('verein.tische.zuordnen', ['code' => $tisch->code]))->assertSee('Beim Speichern wird er dort gelöst', false)
            ->assertDontSee('Theater 2026');

        $this->post('/verein/tische/zuordnen', [
            'code' => $tisch->code, 'veranstaltung_id' => $this->sportfest->id, 'tischnummer' => '1', 'gueltig_bis' => '2026-11-22',
        ])->assertSessionHasNoErrors();

        $this->assertCount(0, $this->zuordnungen($this->tvm));
        $this->assertCount(1, $this->zuordnungen($this->svb));
        $this->assertDatabaseHas('protokoll', ['aktion' => 'tisch.uebernommen', 'mandant_id' => $this->svb->id]);
        $this->assertDatabaseHas('protokoll', ['aktion' => 'tisch.abgegeben', 'mandant_id' => $this->tvm->id]);
    }

    public function test_fremde_vereins_codes_koennen_nicht_zugeordnet_werden(): void
    {
        $fremd = Tischcode::create(['mandant_id' => $this->tvm->id, 'code' => Tischcode::neuerCode(), 'laufnummer' => 1, 'bezeichnung' => 'Code 1']);
        $this->zuordner($this->svb);

        // Scan zeigt die Gästeansicht statt des Formulars.
        $this->get('/t/'.$fremd->code)->assertOk();
        $this->get(route('verein.tische.zuordnen', ['code' => $fremd->code]))->assertNotFound();
        $this->post('/verein/tische/zuordnen', [
            'code' => $fremd->code, 'veranstaltung_id' => $this->sportfest->id, 'tischnummer' => '1', 'gueltig_bis' => '2026-11-22',
        ])->assertNotFound();
        $this->delete('/verein/tische/codes/'.$fremd->id)->assertNotFound();
        $this->assertCount(0, $this->zuordnungen($this->svb));
    }

    public function test_tischnummer_pro_veranstaltung_eindeutig_und_pflicht(): void
    {
        [$a, $b] = [$this->betreiberTisch(1), $this->betreiberTisch(2)];
        $this->zuordner($this->tvm);
        $daten = ['veranstaltung_id' => $this->theater->id, 'gueltig_bis' => '2026-12-09'];

        $this->post('/verein/tische/zuordnen', $daten + ['code' => $a->code, 'tischnummer' => '5'])->assertSessionHasNoErrors();
        $this->post('/verein/tische/zuordnen', $daten + ['code' => $b->code, 'tischnummer' => '5'])->assertSessionHasErrors('tischnummer');
        $this->post('/verein/tische/zuordnen', $daten + ['code' => $b->code, 'tischnummer' => ''])->assertSessionHasErrors('tischnummer');
        $this->post('/verein/tische/zuordnen', array_merge($daten, ['code' => $b->code, 'tischnummer' => '6', 'gueltig_bis' => '2026-11-01']))->assertSessionHasErrors('gueltig_bis');
    }

    public function test_einheitscode_ohne_tischnummer(): void
    {
        $einheit = Tischcode::create(['mandant_id' => $this->tvm->id, 'code' => Tischcode::neuerCode(), 'laufnummer' => 1, 'bezeichnung' => 'Einheitscode 1', 'einheitscode' => true]);
        $this->zuordner($this->tvm);

        $this->post('/verein/tische/zuordnen', ['code' => $einheit->code, 'veranstaltung_id' => $this->theater->id, 'gueltig_bis' => '2026-12-09'])
            ->assertSessionHasNoErrors();
        $this->assertNull($this->zuordnungen($this->tvm)->first()->tischnummer);
    }

    public function test_zuordnung_laeuft_um_mitternacht_nach_dem_enddatum_ab(): void
    {
        $tisch = $this->betreiberTisch();
        app(Tischverwaltung::class)->zuordnen($tisch, $this->theater, '7', Carbon::parse('2026-12-09'), User::factory()->create());

        $this->travelTo(Carbon::parse('2026-12-09 23:59'));
        $this->get('/t/'.$tisch->code)->assertSee('Theater 2026');

        $this->travelTo(Carbon::parse('2026-12-10 00:00'));
        $this->get('/t/'.$tisch->code)->assertSee('Werbeseite des Plattformbetreibers');

        $this->assertSame(1, app(Tischverwaltung::class)->abgelaufeneLoesen());
        $this->assertSame(0, \DB::table('tischzuordnungen')->count());
    }

    public function test_nachtaufgabe_ist_eingeplant(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('tischzuordnungen-loesen')->assertSuccessful();
    }

    public function test_vereins_codes_bleiben_nach_ablauf_beim_verein(): void
    {
        $eigen = Tischcode::create(['mandant_id' => $this->tvm->id, 'code' => Tischcode::neuerCode(), 'laufnummer' => 1, 'bezeichnung' => 'Code 1']);
        app(Tischverwaltung::class)->zuordnen($eigen, $this->theater, '3', Carbon::parse('2026-11-25'), User::factory()->create());

        $this->travelTo(Carbon::parse('2026-11-27 10:00'));
        app(Tischverwaltung::class)->abgelaufeneLoesen();

        $this->assertSame($this->tvm->id, $eigen->fresh()->mandant_id);
        $this->zuordner($this->tvm);
        $this->get('/verein/tische')->assertSee('Code 1')->assertSee('frei');
    }

    public function test_alle_tische_einer_veranstaltung_verlaengern(): void
    {
        $user = User::factory()->create();
        foreach ([1, 2, 3] as $n) {
            app(Tischverwaltung::class)->zuordnen($this->betreiberTisch($n), $this->theater, (string) $n, Carbon::parse('2026-12-09'), $user);
        }
        $this->zuordner($this->tvm);

        $this->put('/verein/veranstaltungen/'.$this->theater->id.'/tische', ['gueltig_bis' => '2026-12-20'])->assertRedirect();
        $this->assertTrue($this->zuordnungen($this->tvm)->every(fn ($z) => $z->gueltig_bis->toDateString() === '2026-12-20'));
    }

    public function test_angemeldet_ohne_verein_wird_zur_auswahl_gebeten(): void
    {
        $tisch = $this->betreiberTisch();
        $person = $this->personMitRechten($this->tvm, ['qrcodes.verwalten']);
        $person->vereine()->attach($this->svb);
        $this->actingAs($person);

        $this->get('/t/'.$tisch->code)->assertOk()->assertSee('Für welchen Verein?');
        $this->get('/t/'.$tisch->code.'?gast=1')->assertSee('Werbeseite des Plattformbetreibers');
    }

    public function test_betreiber_tisch_mit_zuordnung_kann_nicht_geloescht_werden(): void
    {
        $tisch = $this->betreiberTisch();
        app(Tischverwaltung::class)->zuordnen($tisch, $this->theater, '7', Carbon::parse('2026-12-09'), User::factory()->create());
        $this->actingAs($this->superAdmin());

        $this->get('/verwaltung/tische')->assertOk()->assertSee('TVM · Theater 2026 · Tisch 7');
        $this->delete('/verwaltung/tische/'.$tisch->id)->assertSessionHasErrors('code');
        $this->assertNotNull($tisch->fresh());
    }
}
