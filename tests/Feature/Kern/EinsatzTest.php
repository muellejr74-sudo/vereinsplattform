<?php

namespace Tests\Feature\Kern;

use App\Modules\Kern\Dienste\Einsatz;
use App\Modules\Kern\Dienste\Stammdaten;
use App\Modules\Kern\Mandanten\Mandantenkontext;
use App\Modules\Kern\Models\Mandant;
use App\Modules\Kern\Models\Saal;
use App\Modules\Kern\Models\Station;
use App\Modules\Kern\Models\Stationsbesetzung;
use App\Modules\Kern\Models\Stationsgeraet;
use App\Modules\Kern\Models\Veranstaltung;
use App\Modules\Kern\Models\Vorstellung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Einsatz-Code und Stationen (E29, E29a, E29b, E33, E102). */
class EinsatzTest extends TestCase
{
    use RefreshDatabase;

    private Mandant $tvm;

    private Vorstellung $vorstellung;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-12-01 17:00'));
        $this->tvm = Mandant::factory()->create(['kuerzel' => 'TVM']);
        app(Stammdaten::class)->sicherstellen($this->tvm);
        $this->vorstellung = $this->im(function () {
            $v = Veranstaltung::create(['name' => 'Theater']);

            return Vorstellung::create(['veranstaltung_id' => $v->id, 'saal_id' => Saal::first()->id,
                'einlass_um' => '2026-12-01 18:30', 'beginn_um' => '2026-12-01 19:30', 'ende_um' => '2026-12-01 22:00']);
        });
    }

    private function im(\Closure $schritt): mixed
    {
        return app(Mandantenkontext::class)->fuer($this->tvm, $schritt);
    }

    private function code(): string
    {
        return $this->im(fn () => app(Einsatz::class)->codeErzeugen($this->vorstellung));
    }

    private function station(string $name): Station
    {
        return $this->im(fn () => Station::where('name', $name)->firstOrFail());
    }

    public function test_verantwortlicher_erzeugt_code_und_traegt_namen_ein(): void
    {
        $this->actingAs($this->personMitRechten($this->tvm, ['stationen.verwalten']))->withSession(['mandant_id' => $this->tvm->id]);

        $this->post('/verein/vorstellungen/'.$this->vorstellung->id.'/einsatz/code')->assertRedirect();
        $code = $this->vorstellung->fresh()->einsatz_code;
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        $b1 = $this->station('Bedienung 1');
        $this->put('/verein/vorstellungen/'.$this->vorstellung->id.'/einsatz/namen', ['namen' => [$b1->id => 'Petra']])->assertRedirect();
        $this->get('/verein/vorstellungen/'.$this->vorstellung->id.'/einsatz')->assertOk()->assertSee(substr($code, 0, 3))->assertSee('Petra');
        $this->assertDatabaseHas('protokoll', ['aktion' => 'einsatzcode.erzeugt']);
    }

    public function test_einsatz_code_auch_mit_saal_recht(): void
    {
        $this->tvm->setzeModule(['saal']);
        $this->actingAs($this->personMitRechten($this->tvm, ['saal.codes']))->withSession(['mandant_id' => $this->tvm->id]);

        $this->get('/verein/vorstellungen/'.$this->vorstellung->id.'/einsatz')->assertOk();
        $this->get('/verein/stationen')->assertForbidden();
    }

    public function test_personal_meldet_sich_mit_code_als_bedienung_1_petra_an(): void
    {
        $code = $this->code();
        $b1 = $this->station('Bedienung 1');
        $this->im(fn () => Stationsbesetzung::create(['vorstellung_id' => $this->vorstellung->id, 'station_id' => $b1->id, 'name' => 'Petra']));

        $this->post('/einsatz', ['code' => substr($code, 0, 3).' '.substr($code, 3)])->assertRedirect('/einsatz/station');
        $this->get('/einsatz/station')->assertOk()->assertSee('Bedienung 1')->assertSee('Petra');

        $antwort = $this->post('/einsatz/station', ['station_id' => $b1->id]);
        $antwort->assertRedirect('/station')->assertCookie(Einsatz::COOKIE);
        $wert = decrypt(collect($antwort->headers->getCookies())->first(fn ($c) => $c->getName() === Einsatz::COOKIE)->getValue(), false);
        $wert = substr($wert, strpos($wert, '|') + 1);

        $this->withCookie(Einsatz::COOKIE, $wert)->get('/station')->assertOk()->assertSee('Bedienung 1 (Petra)');
        $this->assertDatabaseHas('protokoll', ['aktion' => 'station.angemeldet', 'akteur' => 'Bedienung 1 (Petra)', 'mandant_id' => $this->tvm->id]);

        // Station hat keine Verwaltungsrechte (E29b).
        $this->withCookie(Einsatz::COOKIE, $wert)->get('/verein')->assertRedirect('/anmelden');
        $this->withCookie(Einsatz::COOKIE, $wert)->get('/verwaltung')->assertRedirect('/anmelden');
    }

    public function test_bedienung_nur_ein_geraet_ausschank_mehrere(): void
    {
        $code = $this->code();
        $b1 = $this->station('Bedienung 1');
        $ausschank = $this->station('Ausschank');

        $this->post('/einsatz', ['code' => $code]);
        $this->post('/einsatz/station', ['station_id' => $b1->id])->assertRedirect('/station');

        $this->flushSession();
        $this->post('/einsatz', ['code' => $code]);
        $this->get('/einsatz/station')->assertSee('belegt');
        $this->post('/einsatz/station', ['station_id' => $b1->id])->assertSessionHasErrors('station_id');

        foreach ([1, 2] as $_) {
            $this->flushSession();
            $this->post('/einsatz', ['code' => $code]);
            $this->post('/einsatz/station', ['station_id' => $ausschank->id])->assertRedirect('/station');
        }
        $this->assertSame(2, $this->im(fn () => Stationsgeraet::aktiv()->where('station_id', $ausschank->id)->count()));
    }

    public function test_code_gilt_nur_im_zeitfenster(): void
    {
        $code = $this->code();

        $this->travelTo(Carbon::parse('2026-12-01 15:00'));
        $this->post('/einsatz', ['code' => $code])->assertSessionHasErrors('code');

        $this->travelTo(Carbon::parse('2026-12-01 15:31'));
        $this->post('/einsatz', ['code' => $code])->assertRedirect('/einsatz/station');

        $this->travelTo(Carbon::parse('2026-12-02 01:01'));
        $this->flushSession();
        $this->post('/einsatz', ['code' => $code])->assertSessionHasErrors('code');
    }

    public function test_geraet_endet_mit_dem_zeitfenster(): void
    {
        $code = $this->code();
        $this->post('/einsatz', ['code' => $code]);
        $antwort = $this->post('/einsatz/station', ['station_id' => $this->station('Einlass')->id]);
        $wert = decrypt(collect($antwort->headers->getCookies())->first(fn ($c) => $c->getName() === Einsatz::COOKIE)->getValue(), false);
        $wert = substr($wert, strpos($wert, '|') + 1);

        $this->withCookie(Einsatz::COOKIE, $wert)->get('/station')->assertOk();
        $this->travelTo(Carbon::parse('2026-12-02 01:10'));
        $this->withCookie(Einsatz::COOKIE, $wert)->get('/station')->assertRedirect('/einsatz');
    }

    public function test_verantwortlicher_meldet_geraet_ab(): void
    {
        $code = $this->code();
        $this->post('/einsatz', ['code' => $code]);
        $antwort = $this->post('/einsatz/station', ['station_id' => $this->station('Bedienung 2')->id]);
        $wert = decrypt(collect($antwort->headers->getCookies())->first(fn ($c) => $c->getName() === Einsatz::COOKIE)->getValue(), false);
        $wert = substr($wert, strpos($wert, '|') + 1);
        $geraet = $this->im(fn () => Stationsgeraet::firstOrFail());

        $this->actingAs($this->personMitRechten($this->tvm, ['stationen.verwalten']))->withSession(['mandant_id' => $this->tvm->id])
            ->delete('/verein/vorstellungen/'.$this->vorstellung->id.'/einsatz/geraete/'.$geraet->id)->assertRedirect();
        $this->post('/abmelden');

        $this->withCookie(Einsatz::COOKIE, $wert)->get('/station')->assertRedirect('/einsatz');
        $this->assertDatabaseHas('protokoll', ['aktion' => 'station.abgemeldet']);
    }

    public function test_sperre_nach_fehlversuchen(): void
    {
        $code = $this->code();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/einsatz', ['code' => $code === '000000' ? '111111' : '000000']);
        }
        $this->post('/einsatz', ['code' => $code])->assertSessionHasErrors('code');

        // Ein anderes Gerät ist davon nicht betroffen.
        $this->withServerVariables(['REMOTE_ADDR' => '10.9.9.9'])->post('/einsatz', ['code' => $code])->assertRedirect('/einsatz/station');
    }

    public function test_einsatz_code_eines_inaktiven_vereins_gilt_nicht(): void
    {
        $code = $this->code();
        $this->tvm->forceFill(['aktiv' => false])->save();

        $this->post('/einsatz', ['code' => $code])->assertSessionHasErrors('code');
    }
}
