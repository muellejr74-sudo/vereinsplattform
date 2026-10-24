<?php

namespace Tests\Feature\Kern;

use App\Models\User;
use App\Modules\Kern\Models\Mandant;
use App\Modules\Kern\Nachrichten\Einladung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Vereine und Benutzer verwalten – nur der Super-Admin (E8, E19a). */
class VerwaltungTest extends TestCase
{
    use RefreshDatabase;

    public function test_normale_benutzer_haben_keinen_zugriff_auf_die_verwaltung(): void
    {
        $person = User::factory()->create();
        $verein = Mandant::factory()->create();

        $this->actingAs($person);
        foreach (['/verwaltung', '/verwaltung/vereine', '/verwaltung/benutzer', '/verwaltung/protokoll', '/verwaltung/rechtstexte',
            '/verwaltung/vereine/'.$verein->id.'/edit'] as $adresse) {
            $this->get($adresse)->assertForbidden();
        }

        $this->post('/verwaltung/vereine', ['name' => 'X', 'kuerzel' => 'XX'])->assertForbidden();
        $this->post('/verwaltung/benutzer', ['name' => 'X', 'email' => 'x@y.de'])->assertForbidden();
        $this->post('/verwaltung/vereine/'.$verein->id.'/aktiv')->assertForbidden();
        $this->assertTrue($verein->fresh()->aktiv);
    }

    public function test_verein_anlegen_bearbeiten_deaktivieren(): void
    {
        $this->actingAs($this->superAdmin());

        $this->post('/verwaltung/vereine', ['name' => 'Theaterverein Musterstadt', 'kuerzel' => 'tvm'])
            ->assertRedirect('/verwaltung/vereine');
        $verein = Mandant::where('kuerzel', 'TVM')->firstOrFail();
        $this->assertSame('verein', $verein->typ);
        $this->assertTrue($verein->aktiv);

        $this->put('/verwaltung/vereine/'.$verein->id, ['name' => 'Theaterverein Musterstadt e. V.', 'kuerzel' => 'TVM'])->assertRedirect();
        $this->assertSame('Theaterverein Musterstadt e. V.', $verein->fresh()->name);

        $this->post('/verwaltung/vereine/'.$verein->id.'/aktiv');
        $this->assertFalse($verein->fresh()->aktiv);

        $this->get('/verwaltung/vereine')->assertOk()->assertSee('Theaterverein Musterstadt e. V.')->assertSee('deaktiviert');

        foreach (['verein.angelegt', 'verein.geaendert', 'verein.deaktiviert'] as $aktion) {
            $this->assertDatabaseHas('protokoll', ['aktion' => $aktion, 'mandant_id' => $verein->id]);
        }
    }

    public function test_kuerzel_wird_geprueft(): void
    {
        $this->actingAs($this->superAdmin());
        Mandant::factory()->create(['kuerzel' => 'TVM']);

        $this->post('/verwaltung/vereine', ['name' => 'A', 'kuerzel' => 'TVM'])->assertSessionHasErrors('kuerzel');
        $this->post('/verwaltung/vereine', ['name' => 'A', 'kuerzel' => 'T'])->assertSessionHasErrors('kuerzel');
        $this->post('/verwaltung/vereine', ['name' => 'A', 'kuerzel' => 'TOOLONG'])->assertSessionHasErrors('kuerzel');
        $this->post('/verwaltung/vereine', ['name' => 'A', 'kuerzel' => 'T-M'])->assertSessionHasErrors('kuerzel');
        $this->assertSame(1, Mandant::count());
    }

    public function test_benutzer_anlegen_sendet_einladung_und_ordnet_vereine_zu(): void
    {
        Notification::fake();
        $this->actingAs($this->superAdmin());
        $verein = Mandant::factory()->create();

        $this->post('/verwaltung/benutzer', [
            'name' => 'Petra Muster', 'email' => 'Petra@Beispiel.de', 'vereine' => [$verein->id],
            'ist_superadmin' => '1', // darf über die Webseite nicht gesetzt werden
        ])->assertRedirect('/verwaltung/benutzer');

        $person = User::where('email', 'petra@beispiel.de')->firstOrFail();
        $this->assertFalse($person->istSuperAdmin());
        $this->assertTrue($person->vereine->contains($verein));
        Notification::assertSentTo($person, Einladung::class);
        $this->assertDatabaseHas('protokoll', ['aktion' => 'benutzer.angelegt']);
        $this->assertDatabaseHas('protokoll', ['aktion' => 'benutzer.einladung_gesendet']);

        // Niemand kennt das Startpasswort: Anmeldung ohne Einladung nicht möglich.
        $this->post('/abmelden');
        $this->post('/anmelden', ['email' => 'petra@beispiel.de', 'password' => 'password'])->assertSessionHasErrors('email');
    }

    public function test_ein_konto_pro_person(): void
    {
        $this->actingAs($this->superAdmin());
        User::factory()->create(['email' => 'petra@beispiel.de']);

        $this->post('/verwaltung/benutzer', ['name' => 'Petra', 'email' => 'PETRA@beispiel.de'])->assertSessionHasErrors('email');
        $this->assertSame(1, User::where('email', 'petra@beispiel.de')->count());
    }

    public function test_vereinszuordnung_aendern_wird_protokolliert(): void
    {
        $this->actingAs($this->superAdmin());
        [$a, $b] = Mandant::factory()->count(2)->create();
        $person = User::factory()->create();
        $person->vereine()->attach($a);

        $this->put('/verwaltung/benutzer/'.$person->id, ['name' => $person->name, 'email' => $person->email, 'vereine' => [$b->id]])
            ->assertRedirect('/verwaltung/benutzer');

        $this->assertSame([$b->id], $person->vereine()->pluck('mandanten.id')->all());
        $this->assertDatabaseHas('protokoll', ['aktion' => 'benutzer.verein_zugeordnet', 'mandant_id' => $b->id]);
        $this->assertDatabaseHas('protokoll', ['aktion' => 'benutzer.verein_entfernt', 'mandant_id' => $a->id]);
    }

    public function test_super_admin_kann_sich_nicht_selbst_deaktivieren(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->post('/verwaltung/benutzer/'.$admin->id.'/aktiv')->assertSessionHasErrors('person');
        $this->assertTrue($admin->fresh()->aktiv);
    }

    public function test_benutzer_deaktivieren_und_einladung_erneut_senden(): void
    {
        Notification::fake();
        $this->actingAs($this->superAdmin());
        $person = User::factory()->create();

        $this->post('/verwaltung/benutzer/'.$person->id.'/einladung')->assertSessionHas('hinweis');
        Notification::assertSentTo($person, Einladung::class);

        $this->post('/verwaltung/benutzer/'.$person->id.'/aktiv');
        $this->assertFalse($person->fresh()->aktiv);
        $this->assertDatabaseHas('protokoll', ['aktion' => 'benutzer.deaktiviert']);
    }

    public function test_verwaltungsseiten_werden_angezeigt(): void
    {
        $this->actingAs($this->superAdmin());
        $verein = Mandant::factory()->create();
        $person = User::factory()->create();

        foreach (['/verwaltung', '/verwaltung/vereine', '/verwaltung/vereine/create', '/verwaltung/vereine/'.$verein->id.'/edit',
            '/verwaltung/vereine/'.$verein->id.'/texte', '/verwaltung/benutzer', '/verwaltung/benutzer/create',
            '/verwaltung/benutzer/'.$person->id.'/edit', '/verwaltung/protokoll', '/verwaltung/rechtstexte', '/konto', '/'] as $adresse) {
            $this->get($adresse)->assertOk();
        }
    }

    public function test_super_admin_kann_in_jeden_aktiven_verein_wechseln(): void
    {
        $this->actingAs($this->superAdmin());
        $verein = Mandant::factory()->create();

        $this->post('/verein-wechseln', ['mandant_id' => $verein->id])->assertRedirect('/');
        $this->assertSame($verein->id, session('mandant_id'));

        $this->post('/verein-wechseln', ['mandant_id' => ''])->assertRedirect('/');
        $this->assertNull(session('mandant_id'));
    }
}
