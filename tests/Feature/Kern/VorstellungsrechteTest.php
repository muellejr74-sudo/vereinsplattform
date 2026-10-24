<?php

namespace Tests\Feature\Kern;

use App\Modules\Kern\Dienste\Stammdaten;
use App\Modules\Kern\Mandanten\Mandantenkontext;
use App\Modules\Kern\Models\Mandant;
use App\Modules\Kern\Models\Rechtevergabe;
use App\Modules\Kern\Models\Saal;
use App\Modules\Kern\Models\Veranstaltung;
use App\Modules\Kern\Models\Vorstellung;
use App\Modules\Kern\Rechte\Berechtigung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Rechte nur für einzelne Vorstellungen (E19 „Wann“, E95). */
class VorstellungsrechteTest extends TestCase
{
    use RefreshDatabase;

    private Mandant $tvm;

    private Vorstellung $erste;

    private Vorstellung $zweite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-20 10:00'));
        $this->tvm = Mandant::factory()->create(['kuerzel' => 'TVM']);
        $this->tvm->setzeModule(['ticket']);
        app(Stammdaten::class)->sicherstellen($this->tvm);
        [$this->erste, $this->zweite] = app(Mandantenkontext::class)->fuer($this->tvm, function () {
            $v = Veranstaltung::create(['name' => 'Theater']);

            return [
                Vorstellung::create(['veranstaltung_id' => $v->id, 'saal_id' => Saal::first()->id, 'beginn_um' => '2026-12-01 19:30']),
                Vorstellung::create(['veranstaltung_id' => $v->id, 'saal_id' => Saal::first()->id, 'beginn_um' => '2026-12-02 19:30']),
            ];
        });
    }

    public function test_recht_nur_fuer_eine_vorstellung_vergeben_und_entziehen(): void
    {
        $admin = $this->vereinsadmin($this->tvm);
        $person = $this->personMitRechten($this->tvm, []);
        $this->actingAs($admin)->withSession(['mandant_id' => $this->tvm->id]);

        $this->post('/verein/personen/'.$person->id.'/vorstellungsrechte', ['recht' => 'ticket.abendkasse', 'vorstellung_id' => $this->erste->id])
            ->assertRedirect();

        $berechtigung = app(Berechtigung::class);
        $berechtigung->vergessen();
        $this->assertTrue($berechtigung->darf($person, 'ticket.abendkasse', $this->tvm, $this->erste->id));
        $this->assertFalse($berechtigung->darf($person, 'ticket.abendkasse', $this->tvm, $this->zweite->id));
        $this->assertFalse($berechtigung->darf($person, 'ticket.abendkasse', $this->tvm));

        $this->get('/verein/personen/'.$person->id)->assertSee('Rechte nur für einzelne Vorstellungen')->assertSee('01.12.2026');

        $vergabe = app(Mandantenkontext::class)->fuer($this->tvm, fn () => Rechtevergabe::whereNotNull('vorstellung_id')->firstOrFail());
        $this->delete('/verein/personen/'.$person->id.'/vorstellungsrechte/'.$vergabe->id)->assertRedirect();
        $this->assertSame(0, app(Mandantenkontext::class)->fuer($this->tvm, fn () => Rechtevergabe::whereNotNull('vorstellung_id')->count()));
    }

    public function test_nur_eigene_modulrechte_und_nicht_fuer_sich_selbst(): void
    {
        $admin = $this->vereinsadmin($this->tvm); // ohne Drucken/Stornieren/Abschluss
        $person = $this->personMitRechten($this->tvm, []);
        $this->actingAs($admin)->withSession(['mandant_id' => $this->tvm->id]);

        $this->post('/verein/personen/'.$person->id.'/vorstellungsrechte', ['recht' => 'ticket.stornieren', 'vorstellung_id' => $this->erste->id])->assertForbidden();
        $this->post('/verein/personen/'.$person->id.'/vorstellungsrechte', ['recht' => 'benutzer.verwalten', 'vorstellung_id' => $this->erste->id])->assertForbidden();
        $this->post('/verein/personen/'.$admin->id.'/vorstellungsrechte', ['recht' => 'ticket.abendkasse', 'vorstellung_id' => $this->erste->id])->assertForbidden();
    }

    public function test_globale_rechte_bleiben_beim_speichern_unberuehrt(): void
    {
        $admin = $this->vereinsadmin($this->tvm);
        $person = $this->personMitRechten($this->tvm, []);
        $this->actingAs($admin)->withSession(['mandant_id' => $this->tvm->id]);
        $this->post('/verein/personen/'.$person->id.'/vorstellungsrechte', ['recht' => 'ticket.abendkasse', 'vorstellung_id' => $this->erste->id]);

        // Speichern des Hauptformulars entfernt keine vorstellungsbezogenen Rechte.
        $this->put('/verein/personen/'.$person->id.'/rechte', ['rechte' => []])->assertRedirect();

        $this->assertSame(1, app(Mandantenkontext::class)->fuer($this->tvm, fn () => Rechtevergabe::whereNotNull('vorstellung_id')->count()));
    }
}
