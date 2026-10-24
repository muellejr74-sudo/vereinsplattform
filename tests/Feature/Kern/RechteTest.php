<?php

namespace Tests\Feature\Kern;

use App\Models\User;
use App\Modules\Kern\Mandanten\Mandantenkontext;
use App\Modules\Kern\Models\Mandant;
use App\Modules\Kern\Models\Rechtevergabe;
use App\Modules\Kern\Rechte\Berechtigung;
use App\Modules\Kern\Rechte\Rechtekatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Rechteprüfung (E19), Module (E96), Katalog (E73, E94). */
class RechteTest extends TestCase
{
    use RefreshDatabase;

    private function darf(User $person, string $recht, Mandant $verein): bool
    {
        $berechtigung = app(Berechtigung::class);
        $berechtigung->vergessen();

        return $berechtigung->darf($person, $recht, $verein);
    }

    public function test_recht_wirkt_nur_im_eigenen_verein(): void
    {
        [$a, $b] = Mandant::factory()->count(2)->create();
        $person = $this->personMitRechten($a, ['benutzer.verwalten']);

        $this->assertTrue($this->darf($person, 'benutzer.verwalten', $a));
        $this->assertFalse($this->darf($person, 'rechte.vergeben', $a));
        $this->assertFalse($this->darf($person, 'benutzer.verwalten', $b));
    }

    public function test_ohne_mitgliedschaft_kein_recht(): void
    {
        $verein = Mandant::factory()->create();
        $person = $this->personMitRechten($verein, ['benutzer.verwalten']);
        $person->vereine()->detach($verein->id);

        $this->assertFalse($this->darf($person, 'benutzer.verwalten', $verein));
    }

    public function test_deaktivierter_verein_oder_deaktiviertes_konto_hat_keine_rechte(): void
    {
        $verein = Mandant::factory()->create();
        $person = $this->personMitRechten($verein, ['benutzer.verwalten']);

        $verein->forceFill(['aktiv' => false])->save();
        $this->assertFalse($this->darf($person, 'benutzer.verwalten', $verein->fresh()));

        $verein->forceFill(['aktiv' => true])->save();
        $person->forceFill(['aktiv' => false])->save();
        $this->assertFalse($this->darf($person->fresh(), 'benutzer.verwalten', $verein->fresh()));
    }

    public function test_rechte_ruhen_bei_ausgeschaltetem_modul(): void
    {
        $verein = Mandant::factory()->create();
        $person = $this->personMitRechten($verein, ['ticket.abendkasse', 'benutzer.verwalten']);

        $this->assertFalse($this->darf($person, 'ticket.abendkasse', $verein));
        $this->assertTrue($this->darf($person, 'benutzer.verwalten', $verein), 'Kern-Rechte hängen an keinem Modul.');

        $verein->setzeModule(['ticket']);
        $this->assertTrue($this->darf($person, 'ticket.abendkasse', $verein->fresh()));

        $verein->setzeModule([]);
        $this->assertFalse($this->darf($person, 'ticket.abendkasse', $verein->fresh()));
        $this->assertDatabaseHas('rechtevergaben', ['user_id' => $person->id, 'recht' => 'ticket.abendkasse']);

        $verein->setzeModule(['ticket']);
        $this->assertTrue($this->darf($person, 'ticket.abendkasse', $verein->fresh()), 'Nach dem Wiedereinschalten gilt alles wie vorher.');
    }

    public function test_super_admin_hat_alle_rechte_aber_module_gelten_auch_fuer_ihn(): void
    {
        $verein = Mandant::factory()->create();
        $admin = $this->superAdmin();

        $this->assertTrue($this->darf($admin, 'rechte.vergeben', $verein));
        $this->assertFalse($this->darf($admin, 'ticket.drucken', $verein));
        $verein->setzeModule(['ticket']);
        $this->assertTrue($this->darf($admin, 'ticket.drucken', $verein->fresh()));
    }

    public function test_unbekannte_rechte_werden_nie_gewaehrt(): void
    {
        $verein = Mandant::factory()->create();
        $admin = $this->superAdmin();

        $this->assertFalse($this->darf($admin, 'gibt.es.nicht', $verein));
    }

    public function test_vorstellungsbezogenes_recht_als_grundlage(): void
    {
        $verein = Mandant::factory()->create();
        $verein->setzeModule(['ticket']);
        $person = User::factory()->create();
        $person->vereine()->attach($verein);
        app(Mandantenkontext::class)->fuer($verein, fn () => Rechtevergabe::create([
            'user_id' => $person->id, 'recht' => 'ticket.abendkasse', 'vorstellung_id' => 7,
        ]));

        $berechtigung = app(Berechtigung::class);
        $this->assertTrue($berechtigung->darf($person, 'ticket.abendkasse', $verein, 7));
        $this->assertFalse($berechtigung->darf($person, 'ticket.abendkasse', $verein, 8));
        $this->assertFalse($berechtigung->darf($person, 'ticket.abendkasse', $verein));
    }

    public function test_katalog_und_vorlage(): void
    {
        $vorlage = Rechtekatalog::vorlageVereinsadmin();

        foreach (['benutzer.verwalten', 'rechte.vergeben', 'ticket.kategorien', 'saal.artikel', 'gaesteportal.inhalte', 'verein.impressum'] as $recht) {
            $this->assertContains($recht, $vorlage);
        }
        // Sensible Ticketrechte standardmäßig aus (E73).
        foreach (['ticket.drucken', 'ticket.stornieren', 'ticket.abschluss'] as $recht) {
            $this->assertNotContains($recht, $vorlage);
            $this->assertTrue(Rechtekatalog::gibt($recht));
        }
        // Nie delegierbar: gar nicht im Katalog.
        foreach (['design', 'module', 'abschluss.rueckgaengig', 'verein.anlegen', 'werbung.zentral'] as $recht) {
            $this->assertFalse(Rechtekatalog::gibt($recht));
        }
        $this->assertTrue(Rechtekatalog::istVerfuegbar('benutzer.verwalten'));
        $this->assertTrue(Rechtekatalog::istVerfuegbar('ticket.drucken'));
        $this->assertFalse(Rechtekatalog::istVerfuegbar('saal.artikel'));
    }
}
