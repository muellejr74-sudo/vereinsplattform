<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Unveränderbares Protokoll (E84), Aufbewahrung 2 Jahre (E89).
        Schema::create('protokoll', function (Blueprint $table) {
            $table->id();
            $table->timestamp('zeitpunkt')->index();
            $table->foreignId('mandant_id')->nullable()->index(); // bewusst ohne Fremdschlüssel: Protokoll bleibt erhalten
            $table->unsignedBigInteger('benutzer_id')->nullable()->index();
            $table->string('akteur')->nullable();   // Name zum Zeitpunkt der Aktion (später auch "Bedienung 1 (Petra)")
            $table->string('aktion', 60)->index();
            $table->string('ziel_typ', 40)->nullable();
            $table->unsignedBigInteger('ziel_id')->nullable();
            $table->json('details')->nullable();
            $table->string('ip', 45)->nullable();
        });

        // Zentrale Rechtstexte des Betreibers (E83).
        Schema::create('plattformtexte', function (Blueprint $table) {
            $table->id();
            $table->string('art', 30)->unique(); // impressum | datenschutz
            $table->longText('inhalt');
            $table->timestamps();
        });

        // Rechtstexte eines Vereins (E83): Vereinsimpressum, Ergänzung zur Datenschutzerklärung.
        Schema::create('vereinstexte', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mandant_id')->constrained('mandanten')->cascadeOnDelete();
            $table->string('art', 30); // impressum | datenschutz_ergaenzung
            $table->longText('inhalt');
            $table->timestamps();
            $table->unique(['mandant_id', 'art']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vereinstexte');
        Schema::dropIfExists('plattformtexte');
        Schema::dropIfExists('protokoll');
    }
};
