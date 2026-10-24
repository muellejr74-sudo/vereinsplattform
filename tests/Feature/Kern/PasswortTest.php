<?php

namespace Tests\Feature\Kern;

use App\Models\User;
use App\Modules\Kern\Nachrichten\Einladung;
use App\Modules\Kern\Nachrichten\PasswortZuruecksetzen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Passwort zurücksetzen per zeitlich begrenztem Link (E23, E23a) und Einladung neuer Benutzer. */
class PasswortTest extends TestCase
{
    use RefreshDatabase;

    public function test_link_wird_per_email_versendet(): void
    {
        Notification::fake();
        $person = User::factory()->create(['email' => 'petra@beispiel.de']);

        $this->post('/passwort-vergessen', ['email' => 'Petra@Beispiel.de'])->assertSessionHas('hinweis');

        Notification::assertSentTo($person, PasswortZuruecksetzen::class);
        $this->assertDatabaseHas('protokoll', ['aktion' => 'passwort.link_angefordert']);
    }

    public function test_antwort_verraet_nicht_ob_ein_konto_existiert(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'petra@beispiel.de']);

        $mit = $this->post('/passwort-vergessen', ['email' => 'petra@beispiel.de']);
        $ohne = $this->post('/passwort-vergessen', ['email' => 'niemand@beispiel.de']);

        $this->assertSame($mit->getSession()->get('hinweis'), $ohne->getSession()->get('hinweis'));
        Notification::assertSentTimes(PasswortZuruecksetzen::class, 1);
    }

    public function test_deaktivierte_konten_erhalten_keinen_link(): void
    {
        Notification::fake();
        $person = User::factory()->deaktiviert()->create();

        $this->post('/passwort-vergessen', ['email' => $person->email]);

        Notification::assertNothingSent();
    }

    public function test_passwort_zuruecksetzen_mit_gueltigem_link(): void
    {
        Notification::fake();
        $person = User::factory()->create(['email' => 'petra@beispiel.de']);
        $this->post('/passwort-vergessen', ['email' => 'petra@beispiel.de']);

        $token = null;
        Notification::assertSentTo($person, PasswortZuruecksetzen::class, function ($nachricht) use (&$token) {
            $token = $nachricht->token;

            return true;
        });

        $this->get('/passwort-zuruecksetzen/'.$token.'?email=petra@beispiel.de')->assertOk()->assertSee('Neues Passwort');

        $this->post('/passwort-zuruecksetzen', [
            'token' => $token, 'email' => 'petra@beispiel.de',
            'password' => 'ein neues langes Passwort', 'password_confirmation' => 'ein neues langes Passwort',
        ])->assertRedirect('/anmelden');

        $this->assertTrue(Hash::check('ein neues langes Passwort', $person->fresh()->password));
        $this->assertDatabaseHas('protokoll', ['aktion' => 'passwort.zurueckgesetzt']);

        // Der Link funktioniert nur einmal.
        $this->post('/passwort-zuruecksetzen', [
            'token' => $token, 'email' => 'petra@beispiel.de',
            'password' => 'noch ein anderes Passwort', 'password_confirmation' => 'noch ein anderes Passwort',
        ])->assertSessionHasErrors('email');
    }

    public function test_link_laeuft_nach_60_minuten_ab(): void
    {
        $person = User::factory()->create(['email' => 'petra@beispiel.de']);
        $token = app('auth.password')->broker('users')->createToken($person);

        $this->travel(61)->minutes();

        $this->post('/passwort-zuruecksetzen', [
            'token' => $token, 'email' => 'petra@beispiel.de',
            'password' => 'ein neues langes Passwort', 'password_confirmation' => 'ein neues langes Passwort',
        ])->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('password', $person->fresh()->password));
    }

    public function test_zu_kurzes_passwort_wird_abgelehnt(): void
    {
        $person = User::factory()->create(['email' => 'petra@beispiel.de']);
        $token = app('auth.password')->broker('users')->createToken($person);

        $this->post('/passwort-zuruecksetzen', [
            'token' => $token, 'email' => 'petra@beispiel.de', 'password' => 'kurz', 'password_confirmation' => 'kurz',
        ])->assertSessionHasErrors('password');
    }

    public function test_einladungslink_gilt_drei_tage_und_legt_passwort_fest(): void
    {
        $person = User::factory()->create(['email' => 'neu@beispiel.de']);
        $token = app('auth.password')->broker('einladungen')->createToken($person);

        $this->travel(2)->days();
        $this->get('/einladung/'.$token.'?email=neu@beispiel.de')->assertOk()->assertSee('Passwort fest');
        $this->post('/einladung', [
            'token' => $token, 'email' => 'neu@beispiel.de',
            'password' => 'mein eigenes Passwort', 'password_confirmation' => 'mein eigenes Passwort',
        ])->assertRedirect('/anmelden');

        $this->post('/anmelden', ['email' => 'neu@beispiel.de', 'password' => 'mein eigenes Passwort'])->assertRedirect('/');
        $this->assertAuthenticatedAs($person);
    }

    public function test_einladungslink_ist_nach_drei_tagen_abgelaufen(): void
    {
        $person = User::factory()->create(['email' => 'neu@beispiel.de']);
        $token = app('auth.password')->broker('einladungen')->createToken($person);

        $this->travel(4)->days();
        $this->post('/einladung', [
            'token' => $token, 'email' => 'neu@beispiel.de',
            'password' => 'mein eigenes Passwort', 'password_confirmation' => 'mein eigenes Passwort',
        ])->assertSessionHasErrors('email');
    }

    public function test_passwort_aendern_im_konto(): void
    {
        $person = User::factory()->create();

        $this->actingAs($person)->put('/konto/passwort', [
            'aktuelles_passwort' => 'falsch', 'password' => 'ganz neues Passwort', 'password_confirmation' => 'ganz neues Passwort',
        ])->assertSessionHasErrors('aktuelles_passwort');

        $this->put('/konto/passwort', [
            'aktuelles_passwort' => 'password', 'password' => 'ganz neues Passwort', 'password_confirmation' => 'ganz neues Passwort',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('ganz neues Passwort', $person->fresh()->password));
    }

    public function test_emails_sind_deutsch(): void
    {
        $person = User::factory()->make(['name' => 'Petra', 'email' => 'petra@beispiel.de']);

        $mail = (new PasswortZuruecksetzen('abc'))->toMail($person);
        $this->assertStringContainsString('Passwort zurücksetzen', $mail->subject);
        $this->assertStringContainsString('/passwort-zuruecksetzen/abc', $mail->actionUrl);

        $mail = (new Einladung('xyz'))->toMail($person);
        $this->assertStringContainsString('/einladung/xyz', $mail->actionUrl);
        $this->assertStringContainsString('3 Tage', implode(' ', $mail->outroLines));
    }
}
