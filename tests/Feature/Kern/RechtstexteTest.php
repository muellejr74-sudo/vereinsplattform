<?php

namespace Tests\Feature\Kern;

use App\Modules\Kern\Mandanten\Mandantenkontext;
use App\Modules\Kern\Models\Mandant;
use App\Modules\Kern\Models\Plattformtext;
use App\Modules\Kern\Models\Vereinstext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Pflegbare Rechtstexte (E83). */
class RechtstexteTest extends TestCase
{
    use RefreshDatabase;

    public function test_impressum_und_datenschutz_sind_auf_jeder_seite_verlinkt(): void
    {
        $this->get('/')->assertSee('href="'.route('impressum').'"', false)->assertSee('href="'.route('datenschutz').'"', false);
        $this->get('/anmelden')->assertSee('Impressum')->assertSee('Datenschutz');
    }

    public function test_leere_texte_zeigen_hinweis(): void
    {
        $this->get('/impressum')->assertOk()->assertSee('noch nicht hinterlegt');
    }

    public function test_super_admin_pflegt_plattformtexte(): void
    {
        $this->actingAs($this->superAdmin());

        $this->put('/verwaltung/rechtstexte', ['texte' => [
            'impressum' => "# Angaben\n\nMax Betreiber\n\n[Webseite](https://regional-meine-wahl.de)",
            'datenschutz' => 'Wir speichern nur das Nötigste.',
        ]])->assertRedirect();

        $this->get('/impressum')->assertOk()->assertSee('<h1>Angaben</h1>', false)->assertSee('Max Betreiber')
            ->assertSee('href="https://regional-meine-wahl.de"', false);
        $this->get('/datenschutz')->assertSee('Wir speichern nur das Nötigste.');
        $this->assertDatabaseHas('protokoll', ['aktion' => 'rechtstext.geaendert']);
    }

    public function test_eingeschleuster_html_code_wird_nicht_ausgefuehrt(): void
    {
        Plattformtext::create(['art' => 'impressum', 'inhalt' => "<script>alert('x')</script>\n\n[klick](javascript:alert(1))"]);

        $this->get('/impressum')
            ->assertDontSee("<script>alert('x')</script>", false)
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee('href="javascript:', false);
    }

    public function test_vereinsimpressum_und_datenschutz_ergaenzung(): void
    {
        $verein = Mandant::factory()->create(['name' => 'TV Musterstadt', 'kuerzel' => 'TVM']);
        $andererVerein = Mandant::factory()->create(['kuerzel' => 'XYZ']);
        Plattformtext::create(['art' => 'datenschutz', 'inhalt' => 'Zentrale Erklärung']);

        $this->actingAs($this->superAdmin());
        $this->put('/verwaltung/vereine/'.$verein->id.'/texte', ['texte' => [
            'impressum' => 'Vorstand: Erika Muster',
            'datenschutz_ergaenzung' => 'Fotos bei Aufführungen',
        ]])->assertRedirect();

        $this->get('/verein/tvm/impressum')->assertOk()->assertSee('TV Musterstadt')->assertSee('Vorstand: Erika Muster');
        $this->get('/verein/TVM/datenschutz')->assertOk()->assertSee('Zentrale Erklärung')->assertSee('Fotos bei Aufführungen');

        // Der andere Verein sieht die Texte nicht.
        $this->get('/verein/XYZ/impressum')->assertOk()->assertDontSee('Erika Muster');

        $anzahl = app(Mandantenkontext::class)->fuer($andererVerein, fn () => Vereinstext::count());
        $this->assertSame(0, $anzahl);
    }

    public function test_texte_leeren_entfernt_sie(): void
    {
        $verein = Mandant::factory()->create(['kuerzel' => 'TVM']);
        $this->actingAs($this->superAdmin());

        $this->put('/verwaltung/vereine/'.$verein->id.'/texte', ['texte' => ['impressum' => 'Text']]);
        $this->put('/verwaltung/vereine/'.$verein->id.'/texte', ['texte' => ['impressum' => '']]);

        $this->get('/verein/TVM/impressum')->assertSee('noch nicht hinterlegt');
    }

    public function test_unbekannter_oder_deaktivierter_verein_gibt_404(): void
    {
        Mandant::factory()->create(['kuerzel' => 'ALT', 'aktiv' => false]);

        $this->get('/verein/GIBTSNICHT/impressum')->assertNotFound();
        $this->get('/verein/ALT/impressum')->assertNotFound();
    }
}
