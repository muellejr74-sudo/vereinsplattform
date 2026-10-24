<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Benutzerkonten: Super-Admin-Kennzeichen, aktiv/deaktiviert und
 * Zwei-Faktor-Anmeldung (nur Super-Admin, E16a–E16d).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('ist_superadmin')->default(false)->after('password');
            $table->boolean('aktiv')->default(true)->after('ist_superadmin');
            $table->text('zwei_faktor_geheimnis')->nullable()->after('aktiv');          // verschlüsselt
            $table->text('zwei_faktor_notfallcodes')->nullable()->after('zwei_faktor_geheimnis'); // verschlüsselt, nur Prüfsummen
            $table->timestamp('zwei_faktor_aktiv_seit')->nullable()->after('zwei_faktor_notfallcodes');
            $table->unsignedBigInteger('zwei_faktor_letzter_schritt')->nullable()->after('zwei_faktor_aktiv_seit');
            $table->timestamp('letzte_anmeldung_am')->nullable()->after('zwei_faktor_letzter_schritt');
        });

        // Gerät 30 Tage merken (E16d) – serverseitig gespeichert, damit es widerrufbar ist.
        Schema::create('vertraute_geraete', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token_pruefsumme', 64)->unique();
            $table->string('bezeichnung')->nullable();
            $table->timestamp('gueltig_bis');
            $table->timestamps();
        });

        // Einladungslinks für neue Benutzer (länger gültig als Zurücksetzen-Links).
        Schema::create('einladung_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('einladung_tokens');
        Schema::dropIfExists('vertraute_geraete');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'ist_superadmin', 'aktiv', 'zwei_faktor_geheimnis', 'zwei_faktor_notfallcodes',
                'zwei_faktor_aktiv_seit', 'zwei_faktor_letzter_schritt', 'letzte_anmeldung_am',
            ]);
        });
    }
};
