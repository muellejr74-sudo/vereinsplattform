<?php

namespace Tests\Feature\Kern;

use App\Models\User;
use App\Modules\Kern\Models\Mandant;
use App\Modules\Kern\Models\Protokolleintrag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Anmeldung mit E-Mail und Passwort, Sperre nach Fehlversuchen (E15, E16a). */
class AnmeldungTest extends TestCase
{
    use RefreshDatabase;

    public function test_anmeldeseite_ist_erreichbar(): void
    {
        $this->get('/anmelden')->assertOk()->assertSee('Anmelden')->assertSee('Passwort vergessen?');
    }

    public function test_es_gibt_keine_selbstregistrierung(): void
    {
        $this->get('/registrieren')->assertNotFound();
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['email' => 'x@y.de'])->assertNotFound();
    }

    public function test_anmeldung_mit_richtigen_daten(): void
    {
        $person = User::factory()->create(['email' => 'petra@beispiel.de']);

        $this->post('/anmelden', ['email' => 'Petra@Beispiel.de', 'password' => 'password'])
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($person);
        $this->assertNotNull($person->fresh()->letzte_anmeldung_am);
        $this->assertDatabaseHas('protokoll', ['aktion' => 'anmeldung.erfolgreich', 'benutzer_id' => $person->id]);
    }

    public function test_falsches_passwort_und_unbekannte_adresse_geben_dieselbe_meldung(): void
    {
        User::factory()->create(['email' => 'petra@beispiel.de']);

        $meldung = 'E-Mail-Adresse oder Passwort ist falsch.';
        $this->post('/anmelden', ['email' => 'petra@beispiel.de', 'password' => 'falsch'])->assertSessionHasErrors(['email' => $meldung]);
        $this->post('/anmelden', ['email' => 'niemand@beispiel.de', 'password' => 'falsch'])->assertSessionHasErrors(['email' => $meldung]);

        $this->assertGuest();
        $this->assertSame(2, Protokolleintrag::where('aktion', 'anmeldung.fehlgeschlagen')->count());
    }

    public function test_deaktiviertes_konto_kann_sich_nicht_anmelden(): void
    {
        User::factory()->deaktiviert()->create(['email' => 'alt@beispiel.de']);

        $this->post('/anmelden', ['email' => 'alt@beispiel.de', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_sperre_nach_fuenf_fehlversuchen(): void
    {
        User::factory()->create(['email' => 'petra@beispiel.de']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/anmelden', ['email' => 'petra@beispiel.de', 'password' => 'falsch']);
        }

        // Jetzt hilft auch das richtige Passwort nicht mehr.
        $this->post('/anmelden', ['email' => 'petra@beispiel.de', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'Zu viele Fehlversuche. Bitte in 15 Minuten erneut versuchen.']);
        $this->assertGuest();
        $this->assertDatabaseHas('protokoll', ['aktion' => 'anmeldung.gesperrt']);

        // Nach Ablauf der Sperre geht es wieder.
        $this->travel(16)->minutes();
        $this->post('/anmelden', ['email' => 'petra@beispiel.de', 'password' => 'password'])->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_kontosperre_greift_auch_bei_wechselnden_geraeten(): void
    {
        User::factory()->create(['email' => 'petra@beispiel.de']);

        for ($i = 0; $i < 10; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.'.$i])
                ->post('/anmelden', ['email' => 'petra@beispiel.de', 'password' => 'falsch']);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.99'])
            ->post('/anmelden', ['email' => 'petra@beispiel.de', 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_abmelden(): void
    {
        $person = User::factory()->create();

        $this->actingAs($person)->post('/abmelden')->assertRedirect('/anmelden');
        $this->assertGuest();
        $this->assertDatabaseHas('protokoll', ['aktion' => 'abmeldung', 'benutzer_id' => $person->id]);
    }

    public function test_geschuetzte_seiten_verlangen_anmeldung(): void
    {
        $this->get('/konto')->assertRedirect('/anmelden');
        $this->get('/verwaltung')->assertRedirect('/anmelden');
    }

    public function test_deaktivierung_wirkt_sofort_auf_laufende_sitzung(): void
    {
        $person = User::factory()->create();
        $this->actingAs($person)->get('/konto')->assertOk();

        $person->forceFill(['aktiv' => false])->save();

        $this->get('/konto')->assertRedirect('/anmelden');
        $this->assertGuest();
    }

    public function test_person_mit_einem_verein_landet_direkt_in_diesem(): void
    {
        $verein = Mandant::factory()->create(['name' => 'TV Musterstadt']);
        $person = User::factory()->create(['email' => 'petra@beispiel.de']);
        $person->vereine()->attach($verein);

        $this->post('/anmelden', ['email' => 'petra@beispiel.de', 'password' => 'password']);

        $this->assertSame($verein->id, session('mandant_id'));
    }

    public function test_person_mit_mehreren_vereinen_waehlt_und_kann_nicht_in_fremde_wechseln(): void
    {
        [$a, $b, $fremd] = Mandant::factory()->count(3)->create();
        $person = User::factory()->create();
        $person->vereine()->attach([$a->id, $b->id]);

        $this->actingAs($person)->get('/')->assertSee($a->name)->assertSee($b->name)->assertDontSee($fremd->name);

        $this->post('/verein-wechseln', ['mandant_id' => $b->id])->assertRedirect('/');
        $this->assertSame($b->id, session('mandant_id'));

        $this->post('/verein-wechseln', ['mandant_id' => $fremd->id])->assertForbidden();
        $this->assertSame($b->id, session('mandant_id'));
    }

    public function test_deaktivierter_verein_kann_nicht_gewaehlt_werden(): void
    {
        $verein = Mandant::factory()->create(['aktiv' => false]);
        $person = User::factory()->create();
        $person->vereine()->attach($verein);

        $this->actingAs($person)->post('/verein-wechseln', ['mandant_id' => $verein->id])->assertForbidden();
    }
}
