<?php

namespace Tests\Feature\Kern;

use App\Modules\Kern\Mandanten\Mandantenkontext;
use App\Modules\Kern\Models\Mandant;
use App\Modules\Kern\Models\Ort;
use App\Modules\Kern\Models\Saal;
use App\Modules\Kern\Models\Station;
use App\Modules\Kern\Models\Veranstaltung;
use App\Modules\Kern\Models\Vorstellung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Orte, Säle, Veranstaltungen, Vorstellungen und Zeitfenster (E35, E36, E100, E101, E103). */
class StammdatenTest extends TestCase
{
    use RefreshDatabase;

    private Mandant $tvm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tvm = Mandant::factory()->create(['kuerzel' => 'TVM']);
    }

    private function im(Mandant $verein, \Closure $schritt): mixed
    {
        return app(Mandantenkontext::class)->fuer($verein, $schritt);
    }

    private function alsVerwalter(array $rechte = ['veranstaltungen.verwalten', 'orte.verwalten', 'stationen.verwalten']): void
    {
        $this->actingAs($this->personMitRechten($this->tvm, $rechte))->withSession(['mandant_id' => $this->tvm->id]);
    }

    public function test_ort_saal_und_standard_stationen_werden_einmalig_angelegt(): void
    {
        $this->alsVerwalter();
        $this->get('/verein/stationen')->assertOk()->assertSee('Bedienung 1')->assertSee('Einlass');

        $this->im($this->tvm, function () {
            $this->assertSame(1, Saal::count());
            $this->assertSame(8, Station::count());
            $this->assertTrue(Station::where('name', 'Ausschank')->first()->mehrere_geraete);
            $this->assertFalse(Station::where('name', 'Bedienung 1')->first()->mehrere_geraete);
            Station::query()->delete();
        });

        // Gelöschte Standard-Stationen kommen nicht wieder.
        $this->get('/verein/stationen')->assertOk();
        $this->assertSame(0, $this->im($this->tvm, fn () => Station::count()));
    }

    public function test_veranstaltung_mit_drei_vorstellungen(): void
    {
        $this->alsVerwalter();

        $this->post('/verein/veranstaltungen', ['name' => 'Theater 2026', 'kurzname' => 'Theater'])->assertRedirect();
        $veranstaltung = $this->im($this->tvm, fn () => Veranstaltung::firstOrFail());

        foreach (['2026-12-01', '2026-12-02', '2026-12-08'] as $datum) {
            $this->post('/verein/veranstaltungen/'.$veranstaltung->id.'/vorstellungen', [
                'datum' => $datum, 'einlass' => '18:30', 'beginn' => '19:30', 'ende' => '22:30', 'kontingent' => 300,
            ])->assertRedirect()->assertSessionHasNoErrors();
        }

        $this->get('/verein/veranstaltungen/'.$veranstaltung->id)->assertOk()->assertSee('01.12.2026')->assertSee('08.12.2026');
        $this->assertSame(3, $this->im($this->tvm, fn () => Vorstellung::count()));
        $this->assertDatabaseHas('protokoll', ['aktion' => 'vorstellung.angelegt', 'mandant_id' => $this->tvm->id]);
    }

    public function test_ende_nach_mitternacht_und_einlass_vor_beginn(): void
    {
        $this->alsVerwalter();
        $v = $this->im($this->tvm, fn () => Veranstaltung::create(['name' => 'Ball']));

        $this->post('/verein/veranstaltungen/'.$v->id.'/vorstellungen', ['datum' => '2026-11-14', 'beginn' => '20:00', 'ende' => '02:00'])->assertSessionHasNoErrors();
        $vorstellung = $this->im($this->tvm, fn () => Vorstellung::firstOrFail());
        $this->assertSame('2026-11-15 02:00', $vorstellung->ende_um->format('Y-m-d H:i'));

        $this->post('/verein/veranstaltungen/'.$v->id.'/vorstellungen', ['datum' => '2026-11-14', 'einlass' => '21:00', 'beginn' => '20:00'])->assertSessionHasErrors('einlass');
    }

    public function test_zeitfenster_nach_e100(): void
    {
        $vorstellung = new Vorstellung([
            'einlass_um' => Carbon::parse('2026-12-01 18:30'),
            'beginn_um' => Carbon::parse('2026-12-01 19:30'),
            'ende_um' => Carbon::parse('2026-12-01 22:30'),
        ]);

        $this->assertSame('2026-12-01 15:30', $vorstellung->fensterBeginn()->format('Y-m-d H:i'));
        $this->assertSame('2026-12-02 01:30', $vorstellung->fensterEnde()->format('Y-m-d H:i'));
        $this->assertFalse($vorstellung->istImZeitfenster(Carbon::parse('2026-12-01 15:29')));
        $this->assertTrue($vorstellung->istImZeitfenster(Carbon::parse('2026-12-01 15:30')));
        $this->assertTrue($vorstellung->istImZeitfenster(Carbon::parse('2026-12-02 01:29')));
        $this->assertFalse($vorstellung->istImZeitfenster(Carbon::parse('2026-12-02 01:30')));

        // Ohne Ende: bis 4:00 Uhr am Folgetag; ohne Einlass: ab 3 Stunden vor Beginn.
        $ohne = new Vorstellung(['beginn_um' => Carbon::parse('2026-12-01 19:30')]);
        $this->assertSame('2026-12-01 16:30', $ohne->fensterBeginn()->format('Y-m-d H:i'));
        $this->assertSame('2026-12-02 04:00', $ohne->fensterEnde()->format('Y-m-d H:i'));
    }

    public function test_laufende_vorstellung_einer_veranstaltung(): void
    {
        $this->travelTo(Carbon::parse('2026-12-01 20:00'));
        [$veranstaltung, $heute] = $this->im($this->tvm, function () {
            $saal = Saal::create(['ort_id' => Ort::create(['name' => 'Halle'])->id, 'name' => 'Saal']);
            $v = Veranstaltung::create(['name' => 'Theater']);
            Vorstellung::create(['veranstaltung_id' => $v->id, 'saal_id' => $saal->id, 'beginn_um' => '2026-11-30 19:30']);
            $heute = Vorstellung::create(['veranstaltung_id' => $v->id, 'saal_id' => $saal->id, 'beginn_um' => '2026-12-01 19:30', 'ende_um' => '2026-12-01 22:00']);

            return [$v, $heute];
        });

        $this->assertSame($heute->id, $this->im($this->tvm, fn () => $veranstaltung->laufendeVorstellung()?->id));

        $this->travelTo(Carbon::parse('2026-12-03 12:00'));
        $this->assertNull($this->im($this->tvm, fn () => $veranstaltung->laufendeVorstellung()));
    }

    public function test_saal_auswahl_erst_bei_mehreren_saelen(): void
    {
        $this->alsVerwalter();
        $v = $this->im($this->tvm, fn () => Veranstaltung::create(['name' => 'Theater']));

        $this->get('/verein/veranstaltungen/'.$v->id.'/vorstellungen/neu')->assertOk()->assertDontSee('name="saal_id"', false);

        $ort = $this->im($this->tvm, fn () => Ort::first());
        $this->post('/verein/saele', ['name' => 'Nebenraum', 'ort_id' => $ort->id])->assertSessionHasNoErrors();
        $this->get('/verein/veranstaltungen/'.$v->id.'/vorstellungen/neu')->assertSee('name="saal_id"', false);
    }

    public function test_ohne_recht_kein_zugriff_und_fremde_veranstaltungen_unsichtbar(): void
    {
        $svb = Mandant::factory()->create(['kuerzel' => 'SVB']);
        $fremd = $this->im($svb, fn () => Veranstaltung::create(['name' => 'Sportlerball']));

        $this->alsVerwalter(['qrcodes.verwalten']);
        $this->get('/verein/veranstaltungen')->assertForbidden();

        $this->alsVerwalter();
        $this->get('/verein/veranstaltungen')->assertOk()->assertDontSee('Sportlerball');
        $this->get('/verein/veranstaltungen/'.$fremd->id)->assertNotFound();
        $this->delete('/verein/veranstaltungen/'.$fremd->id)->assertNotFound();
        $this->assertSame(1, $this->im($svb, fn () => Veranstaltung::count()));
    }
}
