<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mandanten (E66): technisch allgemein "Kunde" mit Typ – in der Oberfläche
 * "Verein" bzw. später "Privatfest". Design und Modulfreischaltung folgen in Stufe 1b.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mandanten', function (Blueprint $table) {
            $table->id();
            $table->string('typ', 20)->default('verein');
            $table->string('name');
            $table->string('kuerzel', 6)->unique(); // für Ticketnummern (E45)
            $table->boolean('aktiv')->default(true);
            $table->timestamps();
        });

        Schema::create('mitgliedschaften', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('mandant_id')->constrained('mandanten')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'mandant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mitgliedschaften');
        Schema::dropIfExists('mandanten');
    }
};
