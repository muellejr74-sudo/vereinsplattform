<?php

namespace Tests\Feature\Kern;

use App\Modules\Kern\Dienste\Vereinsdesign;
use App\Modules\Kern\Models\Mandant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Vereinsdesign (E76, E76a, E98, E99) und Modulfreischaltung. */
class VereinsdesignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_farben_und_module_speichern(): void
    {
        $verein = Mandant::factory()->create(['kuerzel' => 'TVM']);
        $this->actingAs($this->superAdmin());

        $this->put('/verwaltung/vereine/'.$verein->id.'/design', [
            'farbe_primaer' => '#8B0000', 'farbe_sekundaer' => '#F5E6C8', 'module' => ['ticket', 'saal'],
        ])->assertRedirect();

        $verein->refresh();
        $this->assertSame('#8b0000', $verein->farbe_primaer);
        $this->assertSame(['saal', 'ticket'], $verein->module());
        $this->assertDatabaseHas('protokoll', ['aktion' => 'verein.design_geaendert', 'mandant_id' => $verein->id]);
        $this->assertDatabaseHas('protokoll', ['aktion' => 'verein.module_geaendert', 'mandant_id' => $verein->id]);
    }

    public function test_ungueltige_farbe_wird_abgelehnt(): void
    {
        $verein = Mandant::factory()->create();
        $this->actingAs($this->superAdmin());

        foreach (['rot', '#12345', '#GGGGGG', 'red;}body{display:none', '#1234567'] as $falsch) {
            $this->put('/verwaltung/vereine/'.$verein->id.'/design', ['farbe_primaer' => $falsch])->assertSessionHasErrors('farbe_primaer');
        }
        $this->assertNull($verein->fresh()->farbe_primaer);
    }

    public function test_vereinsfarben_erscheinen_nur_im_aktiven_verein(): void
    {
        $tvm = Mandant::factory()->create(['farbe_primaer' => '#8b0000', 'farbe_sekundaer' => '#f5e6c8']);
        $svb = Mandant::factory()->create(['farbe_primaer' => '#004d00']);
        $person = $this->personMitRechten($tvm, []);
        $person->vereine()->attach($svb);

        $this->actingAs($person)->withSession(['mandant_id' => $tvm->id])->get('/')
            ->assertSee('--farbe-primaer: #8b0000', false)
            ->assertSee('--farbe-primaer-text: #ffffff', false)
            ->assertSee('--farbe-sekundaer-text: #1b1b18', false)
            ->assertDontSee('#004d00', false)
            ->assertSee($tvm->name);

        $this->withSession(['mandant_id' => $svb->id])->get('/')->assertSee('--farbe-primaer: #004d00', false);

        // Ohne Verein: Plattformdesign.
        $this->post('/abmelden');
        $this->get('/anmelden')->assertDontSee('--farbe-primaer:', false);
    }

    public function test_schriftfarbe_wird_automatisch_gewaehlt(): void
    {
        $this->assertSame('#ffffff', Vereinsdesign::textfarbe('#1f4e79'));
        $this->assertSame('#ffffff', Vereinsdesign::textfarbe('#000000'));
        $this->assertSame('#1b1b18', Vereinsdesign::textfarbe('#ffff00'));
        $this->assertSame('#1b1b18', Vereinsdesign::textfarbe('#f5e6c8'));
    }

    public function test_hinweis_bei_schlecht_lesbarer_farbe_ohne_sperre(): void
    {
        $verein = Mandant::factory()->create();
        $this->actingAs($this->superAdmin());

        $this->put('/verwaltung/vereine/'.$verein->id.'/design', ['farbe_primaer' => '#ffff66'])
            ->assertSessionHas('designhinweise');
        $this->assertSame('#ffff66', $verein->fresh()->farbe_primaer, 'Gespeichert wird trotzdem.');

        $this->assertSame([], Vereinsdesign::hinweise('#1f4e79', '#e8eef5'));
    }

    public function test_logo_hochladen_anzeigen_und_entfernen(): void
    {
        $verein = Mandant::factory()->create(['kuerzel' => 'TVM']);
        $this->actingAs($this->superAdmin());

        $this->put('/verwaltung/vereine/'.$verein->id.'/design', [
            'logo' => UploadedFile::fake()->image('logo.png', 400, 150),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $verein->refresh();
        $this->assertTrue($verein->hatLogo());
        Storage::disk('local')->assertExists($verein->logo_datei);
        $this->assertStringStartsWith('vereinslogos/', $verein->logo_datei);

        $antwort = $this->get('/verein/TVM/logo');
        $antwort->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');

        // Logo erscheint im Kopf, wenn der Verein aktiv ist.
        $this->withSession(['mandant_id' => $verein->id])->get('/')->assertSee($verein->logoAdresse(), false);

        $alteDatei = $verein->logo_datei;
        $this->put('/verwaltung/vereine/'.$verein->id.'/design', ['logo_entfernen' => '1'])->assertRedirect();
        $this->assertFalse($verein->fresh()->hatLogo());
        Storage::disk('local')->assertMissing($alteDatei);
        $this->get('/verein/TVM/logo')->assertNotFound();
    }

    public function test_svg_und_zu_grosse_logos_werden_abgelehnt(): void
    {
        $verein = Mandant::factory()->create();
        $this->actingAs($this->superAdmin());

        $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->put('/verwaltung/vereine/'.$verein->id.'/design', ['logo' => $svg])->assertSessionHasErrors('logo');

        $gross = UploadedFile::fake()->image('gross.jpg', 800, 800)->size(3000);
        $this->put('/verwaltung/vereine/'.$verein->id.'/design', ['logo' => $gross])->assertSessionHasErrors('logo');

        $getarnt = UploadedFile::fake()->createWithContent('logo.png', '<?php echo "boese"; ?>');
        $this->put('/verwaltung/vereine/'.$verein->id.'/design', ['logo' => $getarnt])->assertSessionHasErrors('logo');

        $this->assertFalse($verein->fresh()->hatLogo());
    }

    public function test_keine_vereinsfarben_im_programmcode(): void
    {
        // Die CSS-Datei enthält nur die Plattformfarben als Standard (E14c, E76).
        $css = file_get_contents(public_path('css/plattform.css'));
        $this->assertStringNotContainsString('#8b0000', strtolower($css));
        $this->assertStringContainsString('--farbe-primaer:', $css);
    }
}
