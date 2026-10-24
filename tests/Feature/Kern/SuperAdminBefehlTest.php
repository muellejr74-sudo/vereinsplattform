<?php

namespace Tests\Feature\Kern;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Erstes Super-Admin-Konto nur per Server-Befehl (E8a). */
class SuperAdminBefehlTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_wird_angelegt(): void
    {
        $this->artisan('vp:superadmin-anlegen')
            ->expectsQuestion('Name', 'Jürgen Müller')
            ->expectsQuestion('E-Mail-Adresse', 'Admin@Beispiel.de')
            ->expectsQuestion('Passwort (wird nicht angezeigt, mindestens 12 Zeichen)', 'sehr geheimes Passwort')
            ->expectsQuestion('Passwort wiederholen', 'sehr geheimes Passwort')
            ->assertSuccessful();

        $admin = User::where('email', 'admin@beispiel.de')->firstOrFail();
        $this->assertTrue($admin->istSuperAdmin());
        $this->assertTrue(Hash::check('sehr geheimes Passwort', $admin->password));
        $this->assertFalse($admin->hatZweiFaktor());
        $this->assertDatabaseHas('protokoll', ['aktion' => 'superadmin.angelegt']);
    }

    public function test_nur_einmalig_moeglich(): void
    {
        User::factory()->superAdmin()->create();

        $this->artisan('vp:superadmin-anlegen')->assertFailed();
        $this->assertSame(1, User::where('ist_superadmin', true)->count());
    }

    public function test_zu_kurzes_oder_abweichendes_passwort_wird_abgelehnt(): void
    {
        $this->artisan('vp:superadmin-anlegen')
            ->expectsQuestion('Name', 'Admin')
            ->expectsQuestion('E-Mail-Adresse', 'admin@beispiel.de')
            ->expectsQuestion('Passwort (wird nicht angezeigt, mindestens 12 Zeichen)', 'kurz')
            ->expectsQuestion('Passwort wiederholen', 'anders')
            ->assertFailed();

        $this->assertSame(0, User::count());
    }
}
