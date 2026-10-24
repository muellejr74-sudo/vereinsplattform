<?php

namespace Tests\Feature\Kern;

use App\Models\User;
use App\Modules\Kern\Dienste\Protokoll;
use App\Modules\Kern\Models\Mandant;
use App\Modules\Kern\Models\Protokolleintrag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

/** Unveränderbares Protokoll (E84), automatische Löschung nach 2 Jahren (E89). */
class ProtokollTest extends TestCase
{
    use RefreshDatabase;

    public function test_eintrag_kann_nicht_geaendert_werden(): void
    {
        $eintrag = Protokoll::schreibe('verein.angelegt');

        $this->expectException(LogicException::class);
        $eintrag->update(['aktion' => 'manipuliert']);
    }

    public function test_eintrag_kann_nicht_einzeln_geloescht_werden(): void
    {
        $eintrag = Protokoll::schreibe('verein.angelegt');

        $this->expectException(LogicException::class);
        $eintrag->delete();
    }

    public function test_eintraege_aelter_als_zwei_jahre_werden_automatisch_geloescht(): void
    {
        $this->travelTo(now()->subYears(2)->subDay());
        Protokoll::schreibe('verein.angelegt', null, ['alt' => true]);
        $this->travelBack();

        $this->travelTo(now()->subYears(2)->addDay());
        Protokoll::schreibe('verein.angelegt', null, ['knapp' => true]);
        $this->travelBack();

        Protokoll::schreibe('verein.angelegt', null, ['neu' => true]);

        $this->artisan('model:prune', ['--model' => [Protokolleintrag::class]])->assertSuccessful();

        $this->assertSame(2, Protokolleintrag::count());
        $this->assertFalse(Protokolleintrag::where('details', 'like', '%alt%')->exists());
    }

    public function test_loeschung_ist_als_taegliche_aufgabe_eingeplant(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('model:prune')->assertSuccessful();
    }

    public function test_eintrag_vermerkt_wer_was_bei_welchem_verein(): void
    {
        $person = User::factory()->create(['name' => 'Petra']);
        $verein = Mandant::factory()->create();

        $this->actingAs($person);
        $eintrag = Protokoll::schreibe('verein.geaendert', $verein, ['feld' => 'name']);

        $this->assertSame($person->id, $eintrag->benutzer_id);
        $this->assertSame('Petra', $eintrag->akteur);
        $this->assertSame($verein->id, $eintrag->mandant_id);
        $this->assertSame(['feld' => 'name'], $eintrag->fresh()->details);
    }

    public function test_protokollseite_zeigt_und_filtert_eintraege(): void
    {
        $admin = $this->superAdmin(['name' => 'Chef']);
        $verein = Mandant::factory()->create(['kuerzel' => 'TVM']);
        Protokoll::schreibe('verein.angelegt', $verein, [], $admin);
        Protokoll::schreibe('anmeldung.fehlgeschlagen', null, ['email' => 'x@y.de']);

        $zeileVerein = '<td data-titel="Vorgang">Verein angelegt</td>';
        $zeileAnmeldung = '<td data-titel="Vorgang">Anmeldung fehlgeschlagen</td>';

        $this->actingAs($admin)->get('/verwaltung/protokoll')
            ->assertOk()->assertSee($zeileVerein, false)->assertSee($zeileAnmeldung, false);

        $this->get('/verwaltung/protokoll?verein='.$verein->id)
            ->assertSee($zeileVerein, false)->assertDontSee($zeileAnmeldung, false);

        $this->get('/verwaltung/protokoll?aktion=anmeldung.fehlgeschlagen')
            ->assertSee('x@y.de')->assertDontSee($zeileVerein, false);
    }
}
