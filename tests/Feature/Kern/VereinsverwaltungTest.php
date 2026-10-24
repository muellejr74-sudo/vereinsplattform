<?php

namespace Tests\Feature\Kern;

use App\Models\User;
use App\Modules\Kern\Dienste\Protokoll;
use App\Modules\Kern\Mandanten\Mandantenkontext;
use App\Modules\Kern\Models\Mandant;
use App\Modules\Kern\Models\Rechtevergabe;
use App\Modules\Kern\Nachrichten\Einladung;
use App\Modules\Kern\Rechte\Rechtekatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Vereinsadmin: Personen und Rechte (E19a, E22, E22a, E92, E93, E97). */
class VereinsverwaltungTest extends TestCase
{
    use RefreshDatabase;

    private Mandant $tvm;

    private Mandant $svb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tvm = Mandant::factory()->create(['name' => 'Theaterverein', 'kuerzel' => 'TVM']);
        $this->svb = Mandant::factory()->create(['name' => 'Sportverein', 'kuerzel' => 'SVB']);
    }

    private function alsAdmin(?User $admin = null): User
    {
        $admin ??= $this->vereinsadmin($this->tvm);
        $this->actingAs($admin)->withSession(['mandant_id' => $this->tvm->id]);

        return $admin;
    }

    private function rechteVon(User $person, Mandant $verein): array
    {
        return app(Mandantenkontext::class)->fuer($verein, fn () => Rechtevergabe::where('user_id', $person->id)->pluck('recht')->sort()->values()->all());
    }

    public function test_ohne_verein_wird_zur_auswahl_geleitet(): void
    {
        $admin = $this->vereinsadmin($this->tvm);
        $this->actingAs($admin)->get('/verein')->assertRedirect('/');
    }

    public function test_vereinsadmin_sieht_menue_und_nur_eigene_personen(): void
    {
        $this->personMitRechten($this->tvm, [], ['name' => 'Petra TVM']);
        $this->personMitRechten($this->svb, [], ['name' => 'Sven SVB']);
        $this->alsAdmin();

        $this->get('/')->assertSee('href="'.route('verein.uebersicht').'"', false);
        $this->get('/verein')->assertOk();
        $this->get('/verein/personen')->assertOk()->assertSee('Petra TVM')->assertDontSee('Sven SVB');
    }

    public function test_person_ohne_verwaltungsrechte_hat_keinen_zugang(): void
    {
        $person = $this->personMitRechten($this->tvm, []);
        $this->actingAs($person)->withSession(['mandant_id' => $this->tvm->id]);

        $this->get('/verein')->assertForbidden();
        $this->get('/verein/personen')->assertForbidden();
        $this->get('/verein/protokoll')->assertForbidden();
        $this->get('/verein/rechtstexte')->assertForbidden();
        $this->get('/')->assertDontSee('href="'.route('verein.uebersicht').'"', false);
    }

    public function test_vereinsadmin_hat_keinen_zugriff_auf_design_module_und_plattformverwaltung(): void
    {
        $this->alsAdmin();

        $this->get('/verwaltung')->assertForbidden();
        $this->get('/verwaltung/vereine/'.$this->tvm->id.'/design')->assertForbidden();
        $this->put('/verwaltung/vereine/'.$this->tvm->id.'/design', ['farbe_primaer' => '#ff0000', 'module' => ['ticket']])->assertForbidden();
        $this->put('/verwaltung/benutzer/1', ['name' => 'x', 'email' => 'x@y.de'])->assertForbidden();

        $this->assertNull($this->tvm->fresh()->farbe_primaer);
        $this->assertSame([], $this->tvm->fresh()->module());
    }

    public function test_vorhandenes_konto_ueber_exakte_email_hinzufuegen(): void
    {
        $petra = $this->personMitRechten($this->svb, [], ['email' => 'petra@beispiel.de', 'name' => 'Petra']);
        $this->alsAdmin();

        $this->post('/verein/personen/hinzufuegen', ['email' => 'Petra@Beispiel.de'])
            ->assertRedirect(route('verein.personen.rechte', $petra));

        $this->assertTrue($petra->vereine()->whereKey($this->tvm->id)->exists());
        $this->assertTrue($petra->vereine()->whereKey($this->svb->id)->exists(), 'Die Mitgliedschaft im anderen Verein bleibt unberührt.');
        $this->assertDatabaseHas('protokoll', ['aktion' => 'benutzer.verein_zugeordnet', 'mandant_id' => $this->tvm->id]);
    }

    public function test_neue_person_anlegen_und_einladen(): void
    {
        Notification::fake();
        $this->alsAdmin();

        // Ohne Namen kein neues Konto.
        $this->post('/verein/personen/hinzufuegen', ['email' => 'neu@beispiel.de'])->assertSessionHasErrors('name');
        $this->assertSame(0, User::where('email', 'neu@beispiel.de')->count());

        $this->post('/verein/personen/hinzufuegen', ['email' => 'neu@beispiel.de', 'name' => 'Nina Neu'])->assertRedirect();

        $neu = User::where('email', 'neu@beispiel.de')->firstOrFail();
        $this->assertFalse($neu->istSuperAdmin());
        $this->assertTrue($neu->vereine()->whereKey($this->tvm->id)->exists());
        Notification::assertSentTo($neu, Einladung::class, fn ($n) => $n->verein === 'Theaterverein');
        $this->assertSame([], $this->rechteVon($neu, $this->tvm), 'Neue Personen haben zunächst keine Rechte.');
    }

    public function test_rechte_vergeben_nur_im_rahmen_der_eigenen_rechte(): void
    {
        // Admin hat die Vorlage – ohne Drucken/Stornieren/Abschluss.
        $admin = $this->alsAdmin();
        $person = $this->personMitRechten($this->tvm, []);

        $this->put('/verein/personen/'.$person->id.'/rechte', ['rechte' => ['benutzer.verwalten', 'ticket.drucken', 'gibt.es.nicht']])
            ->assertRedirect();

        $this->assertSame(['benutzer.verwalten'], $this->rechteVon($person, $this->tvm));
        $this->assertDatabaseHas('protokoll', ['aktion' => 'rechte.geaendert', 'mandant_id' => $this->tvm->id, 'benutzer_id' => $admin->id]);
    }

    public function test_fremde_rechte_bleiben_unveraendert(): void
    {
        $this->alsAdmin();
        // Vom Super-Admin vergebenes Recht, das der Vereinsadmin selbst nicht hat:
        $person = $this->personMitRechten($this->tvm, ['ticket.drucken', 'saal.artikel']);

        $this->put('/verein/personen/'.$person->id.'/rechte', ['rechte' => []]);

        $this->assertSame(['ticket.drucken'], $this->rechteVon($person, $this->tvm));
    }

    public function test_eigene_rechte_koennen_nicht_geaendert_werden(): void
    {
        $admin = $this->alsAdmin();

        $this->put('/verein/personen/'.$admin->id.'/rechte', ['rechte' => Rechtekatalog::alle()])->assertForbidden();
        $this->put('/verein/personen/'.$admin->id.'/rechte', ['rechte' => []])->assertForbidden();

        $this->assertEqualsCanonicalizing(Rechtekatalog::vorlageVereinsadmin(), $this->rechteVon($admin, $this->tvm));
    }

    public function test_vertretung_ernennen_ist_erlaubt(): void
    {
        $this->alsAdmin();
        $person = $this->personMitRechten($this->tvm, []);

        $this->put('/verein/personen/'.$person->id.'/rechte', ['vorlage' => 'vereinsadmin'])->assertRedirect();

        $this->assertEqualsCanonicalizing(Rechtekatalog::vorlageVereinsadmin(), $this->rechteVon($person, $this->tvm));
        $this->get('/verein/personen')->assertSee('Vereinsadmin');
    }

    public function test_ohne_recht_rechte_vergeben_keine_aenderung(): void
    {
        $nurPersonen = $this->personMitRechten($this->tvm, ['benutzer.verwalten']);
        $person = $this->personMitRechten($this->tvm, []);
        $this->actingAs($nurPersonen)->withSession(['mandant_id' => $this->tvm->id]);

        $this->put('/verein/personen/'.$person->id.'/rechte', ['rechte' => ['benutzer.verwalten']])->assertForbidden();
        $this->assertSame([], $this->rechteVon($person, $this->tvm));
    }

    public function test_personen_anderer_vereine_sind_nicht_erreichbar(): void
    {
        $fremd = $this->personMitRechten($this->svb, []);
        $this->alsAdmin();

        $this->get('/verein/personen/'.$fremd->id)->assertNotFound();
        $this->put('/verein/personen/'.$fremd->id.'/rechte', ['rechte' => ['benutzer.verwalten']])->assertNotFound();
        $this->delete('/verein/personen/'.$fremd->id)->assertNotFound();
        $this->post('/verein/personen/'.$fremd->id.'/einladung')->assertNotFound();
        $this->assertSame([], $this->rechteVon($fremd, $this->svb));
    }

    public function test_person_aus_verein_entfernen_loescht_rechte_aber_nicht_das_konto(): void
    {
        $this->alsAdmin();
        $person = $this->personMitRechten($this->tvm, ['saal.artikel']);
        $person->vereine()->attach($this->svb);

        $this->delete('/verein/personen/'.$person->id)->assertRedirect('/verein/personen');

        $this->assertFalse($person->vereine()->whereKey($this->tvm->id)->exists());
        $this->assertTrue($person->vereine()->whereKey($this->svb->id)->exists());
        $this->assertSame([], $this->rechteVon($person, $this->tvm));
        $this->assertTrue($person->fresh()->aktiv);
        $this->assertDatabaseHas('protokoll', ['aktion' => 'benutzer.verein_entfernt', 'mandant_id' => $this->tvm->id]);
    }

    public function test_vereinsadmin_kann_sich_nicht_selbst_entfernen(): void
    {
        $admin = $this->alsAdmin();

        $this->delete('/verein/personen/'.$admin->id)->assertForbidden();
        $this->assertTrue($admin->vereine()->whereKey($this->tvm->id)->exists());
    }

    public function test_einladung_erneut_senden(): void
    {
        Notification::fake();
        $this->alsAdmin();
        $person = $this->personMitRechten($this->tvm, []);

        $this->post('/verein/personen/'.$person->id.'/einladung')->assertSessionHas('hinweis');
        Notification::assertSentTo($person, Einladung::class);
    }

    public function test_protokoll_zeigt_nur_den_eigenen_verein(): void
    {
        Protokoll::schreibe('verein.geaendert', $this->tvm, ['geheim' => 'tvm-eintrag']);
        Protokoll::schreibe('verein.geaendert', $this->svb, ['geheim' => 'svb-eintrag']);
        Protokoll::schreibe('anmeldung.fehlgeschlagen', null, ['email' => 'plattform-eintrag']);
        $this->alsAdmin();

        $this->get('/verein/protokoll')->assertOk()
            ->assertSee('tvm-eintrag')->assertDontSee('svb-eintrag')->assertDontSee('plattform-eintrag');
    }

    public function test_vereinsimpressum_pflegen(): void
    {
        $this->alsAdmin();

        $this->put('/verein/rechtstexte', ['texte' => ['impressum' => 'Vorstand: Erika']])->assertRedirect();
        $this->get('/verein/TVM/impressum')->assertSee('Vorstand: Erika');
        $this->get('/verein/SVB/impressum')->assertDontSee('Vorstand: Erika');
    }

    public function test_super_admin_verwaltet_personen_jedes_vereins(): void
    {
        $admin = $this->superAdmin();
        $person = $this->personMitRechten($this->svb, []);

        $this->actingAs($admin)->get('/verwaltung/vereine/'.$this->svb->id.'/personen')->assertRedirect('/verein/personen');
        $this->assertSame($this->svb->id, session('mandant_id'));

        // Super-Admin darf auch sensible Rechte vergeben.
        $this->svb->setzeModule(['ticket']);
        $this->put('/verein/personen/'.$person->id.'/rechte', ['rechte' => ['ticket.drucken', 'rechte.vergeben']])->assertRedirect();
        $this->assertSame(['rechte.vergeben', 'ticket.drucken'], $this->rechteVon($person, $this->svb));

        // Benutzerliste zeigt den Vereinsadmin (E93).
        $this->get('/verwaltung/benutzer')->assertSee('SVB (Admin)');
    }

    public function test_entfernen_ueber_globale_benutzerverwaltung_loescht_auch_rechte(): void
    {
        $person = $this->personMitRechten($this->tvm, ['saal.artikel']);
        $this->actingAs($this->superAdmin());

        $this->put('/verwaltung/benutzer/'.$person->id, ['name' => $person->name, 'email' => $person->email, 'vereine' => []])->assertRedirect();

        $this->assertSame([], $this->rechteVon($person, $this->tvm));
    }
}
