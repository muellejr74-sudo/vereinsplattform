<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stufe 1b: Rechtevergaben (E19), Modulfreischaltung pro Verein, Vereinsdesign (E76).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Recht = WAS (recht) + WO (Verein; Modul ergibt sich aus dem Recht) + WANN (optional eine Vorstellung, ab Stufe 2).
        Schema::create('rechtevergaben', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mandant_id')->constrained('mandanten')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('recht', 60);
            $table->unsignedBigInteger('vorstellung_id')->nullable(); // E95: null = alle Veranstaltungen
            $table->unsignedBigInteger('vergeben_von')->nullable();
            $table->timestamps();
            $table->index(['mandant_id', 'user_id']);
        });

        Schema::create('mandant_module', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mandant_id')->constrained('mandanten')->cascadeOnDelete();
            $table->string('modul', 30);
            $table->timestamps();
            $table->unique(['mandant_id', 'modul']);
        });

        Schema::table('mandanten', function (Blueprint $table) {
            $table->string('logo_datei')->nullable()->after('aktiv');
            $table->string('logo_typ', 30)->nullable()->after('logo_datei');
            $table->string('farbe_primaer', 7)->nullable()->after('logo_typ');
            $table->string('farbe_sekundaer', 7)->nullable()->after('farbe_primaer');
        });
    }

    public function down(): void
    {
        Schema::table('mandanten', function (Blueprint $table) {
            $table->dropColumn(['logo_datei', 'logo_typ', 'farbe_primaer', 'farbe_sekundaer']);
        });
        Schema::dropIfExists('mandant_module');
        Schema::dropIfExists('rechtevergaben');
    }
};
