<?php

namespace Tests\Feature\Kern;

use App\Models\User;
use App\Modules\Kern\Mandanten\Mandantenkontext;
use App\Modules\Kern\Mandanten\MandantFehlt;
use App\Modules\Kern\Models\Mandant;
use App\Modules\Kern\Models\Vereinstext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kernregel E14a: Verein A sieht nie Daten von Verein B.
 * Geprüft am mandantengebundenen Modell "Vereinstext" (gleicher Mechanismus
 * wie für alle späteren Fachtabellen).
 */
class MandantentrennungTest extends TestCase
{
    use RefreshDatabase;

    private Mandant $vereinA;

    private Mandant $vereinB;

    private Mandantenkontext $kontext;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kontext = app(Mandantenkontext::class);
        $this->vereinA = Mandant::factory()->create(['kuerzel' => 'AAA']);
        $this->vereinB = Mandant::factory()->create(['kuerzel' => 'BBB']);

        $this->kontext->fuer($this->vereinA, fn () => Vereinstext::create(['art' => 'impressum', 'inhalt' => 'Impressum A']));
        $this->kontext->fuer($this->vereinB, fn () => Vereinstext::create(['art' => 'impressum', 'inhalt' => 'Impressum B']));
    }

    public function test_verein_a_sieht_nur_eigene_daten(): void
    {
        $this->kontext->setze($this->vereinA);

        $this->assertSame(['Impressum A'], Vereinstext::pluck('inhalt')->all());
        $this->assertSame(1, Vereinstext::count());
    }

    public function test_verein_b_sieht_nur_eigene_daten(): void
    {
        $this->kontext->setze($this->vereinB);

        $this->assertSame(['Impressum B'], Vereinstext::pluck('inhalt')->all());
    }

    public function test_fremder_datensatz_ist_auch_ueber_die_kennung_nicht_erreichbar(): void
    {
        $idVonB = $this->kontext->fuer($this->vereinB, fn () => Vereinstext::first()->id);

        $this->kontext->setze($this->vereinA);

        $this->assertNull(Vereinstext::find($idVonB));
        $this->assertSame(0, Vereinstext::whereKey($idVonB)->update(['inhalt' => 'manipuliert']));
        $this->assertSame(0, Vereinstext::whereKey($idVonB)->delete());

        $this->kontext->setze($this->vereinB);
        $this->assertSame('Impressum B', Vereinstext::find($idVonB)->inhalt);
    }

    public function test_ohne_verein_werden_keine_daten_geliefert(): void
    {
        $this->kontext->leeren();

        $this->assertSame(0, Vereinstext::count());
        $this->assertNull(Vereinstext::first());
    }

    public function test_ohne_verein_kann_nichts_gespeichert_werden(): void
    {
        $this->kontext->leeren();

        $this->expectException(MandantFehlt::class);
        Vereinstext::create(['art' => 'datenschutz_ergaenzung', 'inhalt' => 'x']);
    }

    public function test_neuer_datensatz_erhaelt_automatisch_den_aktuellen_verein(): void
    {
        $text = $this->kontext->fuer($this->vereinA, fn () => Vereinstext::create(['art' => 'datenschutz_ergaenzung', 'inhalt' => 'x']));

        $this->assertSame($this->vereinA->id, (int) $text->mandant_id);
    }

    public function test_datensatz_fuer_fremden_verein_anlegen_ist_verboten(): void
    {
        $this->kontext->setze($this->vereinA);

        $this->expectException(MandantFehlt::class);
        $text = new Vereinstext(['art' => 'datenschutz_ergaenzung', 'inhalt' => 'x']);
        $text->mandant_id = $this->vereinB->id;
        $text->save();
    }

    public function test_vereinskennung_kann_nicht_nachtraeglich_geaendert_werden(): void
    {
        $this->kontext->setze($this->vereinA);
        $text = Vereinstext::first();

        $this->expectException(MandantFehlt::class);
        $text->mandant_id = $this->vereinB->id;
        $text->save();
    }

    public function test_kontext_wechsel_mit_fuer_stellt_vorherigen_verein_wieder_her(): void
    {
        $this->kontext->setze($this->vereinA);

        $inhaltB = $this->kontext->fuer($this->vereinB, fn () => Vereinstext::value('inhalt'));

        $this->assertSame('Impressum B', $inhaltB);
        $this->assertSame($this->vereinA->id, $this->kontext->id());
    }

    public function test_anfrage_setzt_nur_vereine_der_person_als_kontext(): void
    {
        $person = User::factory()->create();
        $person->vereine()->attach($this->vereinA);

        // Versuch, über eine manipulierte Sitzung in Verein B zu arbeiten:
        $this->actingAs($person)
            ->withSession(['mandant_id' => $this->vereinB->id])
            ->get('/')
            ->assertOk()
            ->assertDontSee($this->vereinB->name)
            ->assertSessionMissing('mandant_id');
    }
}
