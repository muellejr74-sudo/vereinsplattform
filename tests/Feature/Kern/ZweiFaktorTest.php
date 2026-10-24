<?php

namespace Tests\Feature\Kern;

use App\Models\User;
use App\Modules\Kern\Dienste\Totp;
use App\Modules\Kern\Dienste\ZweiFaktor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Zwei-Faktor-Anmeldung nur für den Super-Admin (E16a–E16d). */
class ZweiFaktorTest extends TestCase
{
    use RefreshDatabase;

    public function test_normale_benutzer_brauchen_keinen_code(): void
    {
        User::factory()->create(['email' => 'petra@beispiel.de']);

        $this->post('/anmelden', ['email' => 'petra@beispiel.de', 'password' => 'password'])->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_normale_benutzer_koennen_zwei_faktor_nicht_einrichten(): void
    {
        $this->actingAs(User::factory()->create())->get('/konto/zwei-faktor/einrichten')->assertForbidden();
    }

    public function test_super_admin_ohne_einrichtung_muss_zuerst_einrichten(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get('/verwaltung')->assertRedirect('/konto/zwei-faktor/einrichten');
        $this->get('/konto')->assertRedirect('/konto/zwei-faktor/einrichten');
        $this->get('/')->assertRedirect('/konto/zwei-faktor/einrichten');
    }

    public function test_einrichtung_mit_qr_code_und_notfallcodes(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get('/konto/zwei-faktor/einrichten')
            ->assertOk()->assertSee('<svg', false)->assertSee('Authenticator');

        $geheimnis = session('zweifaktor_einrichtung_geheimnis');
        $this->assertNotEmpty($geheimnis);

        // Falscher Code wird abgelehnt.
        $this->post('/konto/zwei-faktor/einrichten', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertFalse($admin->fresh()->hatZweiFaktor());

        $antwort = $this->post('/konto/zwei-faktor/einrichten', ['code' => Totp::code($geheimnis, Totp::zeitschritt())]);
        $antwort->assertRedirect('/konto');

        $admin->refresh();
        $neueCodes = session('neue_notfallcodes');
        $this->assertTrue($admin->hatZweiFaktor());
        $this->assertCount(8, $neueCodes);
        $this->assertCount(8, $admin->zwei_faktor_notfallcodes);
        // Notfall-Codes werden nur als Prüfsumme gespeichert, nie im Klartext.
        $this->assertNotContains($neueCodes[0], $admin->zwei_faktor_notfallcodes);
        $this->assertDatabaseHas('protokoll', ['aktion' => 'zweifaktor.eingerichtet']);

        // Geheimnis liegt verschlüsselt in der Datenbank.
        $roh = \DB::table('users')->where('id', $admin->id)->value('zwei_faktor_geheimnis');
        $this->assertStringNotContainsString($geheimnis, $roh);

        $this->get('/konto')->assertOk()->assertSee($neueCodes[0]);
    }

    public function test_anmeldung_super_admin_verlangt_code(): void
    {
        $admin = $this->superAdmin(['email' => 'admin@beispiel.de']);

        $this->post('/anmelden', ['email' => 'admin@beispiel.de', 'password' => 'password'])
            ->assertRedirect('/anmelden/code');
        $this->assertGuest();

        // Ohne Code kein Zugriff.
        $this->get('/verwaltung')->assertRedirect('/anmelden');

        // Nach dem Code geht es zur ursprünglich gewünschten Seite.
        $this->post('/anmelden/code', ['code' => $this->aktuellerCode()])->assertRedirect('/verwaltung');
        $this->assertAuthenticatedAs($admin);
        $this->get('/verwaltung')->assertOk();
    }

    public function test_falscher_code_und_sperre(): void
    {
        $this->superAdmin(['email' => 'admin@beispiel.de']);
        $this->post('/anmelden', ['email' => 'admin@beispiel.de', 'password' => 'password']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/anmelden/code', ['code' => '000000']);
        }
        $this->assertGuest();

        $this->post('/anmelden/code', ['code' => $this->aktuellerCode()])->assertRedirect('/anmelden');
        $this->assertGuest();
        $this->assertDatabaseHas('protokoll', ['aktion' => 'zweifaktor.fehlgeschlagen']);
    }

    public function test_code_kann_nicht_zweimal_benutzt_werden(): void
    {
        $admin = $this->superAdmin(['email' => 'admin@beispiel.de']);
        $code = $this->aktuellerCode();

        $this->post('/anmelden', ['email' => 'admin@beispiel.de', 'password' => 'password']);
        $this->post('/anmelden/code', ['code' => $code]);
        $this->assertAuthenticatedAs($admin);
        $this->post('/abmelden');

        $this->post('/anmelden', ['email' => 'admin@beispiel.de', 'password' => 'password']);
        $this->post('/anmelden/code', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_zwischenschritt_laeuft_nach_zehn_minuten_ab(): void
    {
        $this->superAdmin(['email' => 'admin@beispiel.de']);
        $this->post('/anmelden', ['email' => 'admin@beispiel.de', 'password' => 'password']);

        $this->travel(11)->minutes();

        $this->post('/anmelden/code', ['code' => $this->aktuellerCode()])->assertRedirect('/anmelden');
        $this->assertGuest();
    }

    public function test_notfallcode_funktioniert_genau_einmal(): void
    {
        $admin = $this->superAdmin(['email' => 'admin@beispiel.de']);
        $codes = app(ZweiFaktor::class)->erneuereNotfallcodes($admin);

        $this->post('/anmelden', ['email' => 'admin@beispiel.de', 'password' => 'password']);
        $this->post('/anmelden/code', ['code' => strtolower($codes[0])])->assertRedirect('/');
        $this->assertAuthenticatedAs($admin);
        $this->assertCount(7, $admin->fresh()->zwei_faktor_notfallcodes);
        $this->assertDatabaseHas('protokoll', ['aktion' => 'zweifaktor.notfallcode_benutzt']);
        $this->post('/abmelden');

        $this->post('/anmelden', ['email' => 'admin@beispiel.de', 'password' => 'password']);
        $this->post('/anmelden/code', ['code' => $codes[0]])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_geraet_30_tage_merken(): void
    {
        $admin = $this->superAdmin(['email' => 'admin@beispiel.de']);

        $this->post('/anmelden', ['email' => 'admin@beispiel.de', 'password' => 'password']);
        $antwort = $this->post('/anmelden/code', ['code' => $this->aktuellerCode(), 'geraet_merken' => '1']);
        $antwort->assertCookie(ZweiFaktor::GERAET_COOKIE);
        $cookie = collect($antwort->headers->getCookies())->first(fn ($c) => $c->getName() === ZweiFaktor::GERAET_COOKIE);
        $this->assertTrue($cookie->isHttpOnly());
        $wert = decrypt($cookie->getValue(), false);
        $wert = substr($wert, strpos($wert, '|') + 1); // Laravel-Präfix entfernen
        $this->assertStringStartsWith($admin->id.'|', $wert);
        $this->post('/abmelden');

        // Auf dem gemerkten Gerät: kein Code nötig.
        $this->withCookie(ZweiFaktor::GERAET_COOKIE, $wert)
            ->post('/anmelden', ['email' => 'admin@beispiel.de', 'password' => 'password'])
            ->assertRedirect('/');
        $this->assertAuthenticatedAs($admin);
        $this->post('/abmelden');

        // Nach 31 Tagen wieder Code nötig.
        $this->travel(31)->days();
        $this->withCookie(ZweiFaktor::GERAET_COOKIE, $wert)
            ->post('/anmelden', ['email' => 'admin@beispiel.de', 'password' => 'password'])
            ->assertRedirect('/anmelden/code');
    }

    public function test_fremdes_geraet_ohne_cookie_braucht_code(): void
    {
        $this->superAdmin(['email' => 'admin@beispiel.de']);

        $this->withCookie(ZweiFaktor::GERAET_COOKIE, '1|erfunden')
            ->post('/anmelden', ['email' => 'admin@beispiel.de', 'password' => 'password'])
            ->assertRedirect('/anmelden/code');
    }

    public function test_alle_geraete_abmelden(): void
    {
        $admin = $this->superAdmin();
        app(ZweiFaktor::class)->merkeGeraet($admin, request());

        $this->actingAs($admin)->post('/konto/zwei-faktor/geraete-abmelden')->assertRedirect('/konto');
        $this->assertDatabaseCount('vertraute_geraete', 0);
    }

    public function test_neue_notfallcodes_nur_mit_passwort(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->post('/konto/zwei-faktor/notfallcodes', ['aktuelles_passwort' => 'falsch'])
            ->assertSessionHasErrors('aktuelles_passwort');
        $this->post('/konto/zwei-faktor/notfallcodes', ['aktuelles_passwort' => 'password'])->assertRedirect('/konto');
        $this->assertCount(8, $admin->fresh()->zwei_faktor_notfallcodes);
    }

    public function test_server_befehl_setzt_zwei_faktor_zurueck(): void
    {
        $admin = $this->superAdmin(['email' => 'admin@beispiel.de']);
        app(ZweiFaktor::class)->merkeGeraet($admin, request());

        $this->artisan('vp:zweifaktor-zuruecksetzen', ['email' => 'admin@beispiel.de'])
            ->expectsConfirmation('Zwei-Faktor-Anmeldung für '.$admin->name.' (admin@beispiel.de) wirklich zurücksetzen?', 'yes')
            ->assertSuccessful();

        $this->assertFalse($admin->fresh()->hatZweiFaktor());
        $this->assertDatabaseCount('vertraute_geraete', 0);
        $this->assertDatabaseHas('protokoll', ['aktion' => 'zweifaktor.zurueckgesetzt']);

        // Nächste Anmeldung führt zur Neueinrichtung.
        $this->post('/anmelden', ['email' => 'admin@beispiel.de', 'password' => 'password'])->assertRedirect('/');
        $this->get('/verwaltung')->assertRedirect('/konto/zwei-faktor/einrichten');
    }
}
