<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stufe 3 – Ticketverwaltung A: Kategorien, Preise, Verkaufsstellen, Kartenaufträge,
 * Tickets und Nummernkreise (E24–E31, E38, E39, E45, E110–E118).
 * Beträge werden in Cent gespeichert (keine Rundungsfehler).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticketkategorien', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mandant_id')->constrained('mandanten')->cascadeOnDelete();
            $table->foreignId('veranstaltung_id')->constrained('veranstaltungen')->cascadeOnDelete();
            $table->string('name', 40);
            $table->unsignedInteger('preis_cent')->default(0);
            $table->boolean('ist_freikarte')->default(false); // E118: feste Kategorie „Freikarte“, 0 €
            $table->unsignedSmallInteger('reihenfolge')->default(0);
            $table->timestamps();
        });

        Schema::create('preisabweichungen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mandant_id')->constrained('mandanten')->cascadeOnDelete();
            $table->foreignId('vorstellung_id')->constrained('vorstellungen')->cascadeOnDelete();
            $table->foreignId('kategorie_id')->constrained('ticketkategorien')->cascadeOnDelete();
            $table->unsignedInteger('preis_cent');
            $table->timestamps();
            $table->unique(['vorstellung_id', 'kategorie_id']);
        });

        Schema::create('verkaufsstellen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mandant_id')->constrained('mandanten')->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('art', 20)->default('vorverkauf'); // vorverkauf | abendkasse (E46, E115)
            $table->boolean('aktiv')->default(true);
            $table->timestamps();
        });

        Schema::create('verkaufsstellen_benutzer', function (Blueprint $table) {
            $table->id();
            $table->foreignId('verkaufsstelle_id')->constrained('verkaufsstellen')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['verkaufsstelle_id', 'user_id']);
        });

        Schema::create('kartenauftraege', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mandant_id')->constrained('mandanten')->cascadeOnDelete();
            $table->foreignId('vorstellung_id')->constrained('vorstellungen')->cascadeOnDelete();
            $table->foreignId('verkaufsstelle_id')->nullable()->constrained('verkaufsstellen')->nullOnDelete(); // leer = Verein intern (E114c)
            $table->string('art', 20); // karten | freikarten
            $table->string('status', 20)->default('angefordert'); // angefordert | bestaetigt | gedruckt | ausgegeben | abgelehnt | zurueckgezogen
            $table->string('name', 80)->nullable(); // Empfänger bzw. Stapelname (E114)
            $table->string('bemerkung', 255)->nullable();
            $table->unsignedBigInteger('angefordert_von')->nullable();
            $table->unsignedBigInteger('bestaetigt_von')->nullable();
            $table->timestamp('bestaetigt_am')->nullable();
            $table->timestamp('gedruckt_am')->nullable();
            $table->timestamp('ausgegeben_am')->nullable();
            $table->timestamps();
            $table->index(['mandant_id', 'status']);
        });

        Schema::create('auftragspositionen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mandant_id')->constrained('mandanten')->cascadeOnDelete();
            $table->foreignId('kartenauftrag_id')->constrained('kartenauftraege')->cascadeOnDelete();
            $table->foreignId('kategorie_id')->constrained('ticketkategorien')->restrictOnDelete();
            $table->unsignedInteger('anzahl');
            $table->unsignedInteger('anzahl_bestaetigt')->nullable();
            $table->timestamps();
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mandant_id')->constrained('mandanten')->cascadeOnDelete();
            $table->string('nummer', 24)->unique();          // TVM-26-00412 (E45, E113)
            $table->string('pruefzeichen', 8);               // zufällig, nicht erratbar (E14d, E112)
            $table->foreignId('vorstellung_id')->constrained('vorstellungen')->restrictOnDelete();
            $table->foreignId('kategorie_id')->constrained('ticketkategorien')->restrictOnDelete();
            $table->unsignedInteger('preis_cent');
            $table->boolean('ist_freikarte')->default(false);
            $table->string('status', 20);                    // erzeugt | gedruckt | ausgegeben | verkauft | eingelassen | storniert (E43, E116)
            $table->string('name', 80)->nullable();
            $table->unsignedSmallInteger('stapel_position')->nullable(); // E114b „3/20“
            $table->unsignedSmallInteger('stapel_groesse')->nullable();
            $table->foreignId('kartenauftrag_id')->nullable()->constrained('kartenauftraege')->nullOnDelete();
            $table->foreignId('verkaufsstelle_id')->nullable()->constrained('verkaufsstellen')->nullOnDelete();
            $table->unsignedSmallInteger('druckanzahl')->default(0);
            $table->timestamp('gedruckt_am')->nullable();
            $table->timestamp('ausgegeben_am')->nullable();
            $table->timestamps();
            $table->index(['vorstellung_id', 'status']);
        });

        // Sichere, fortlaufende Nummern je Verein und Jahr (gesperrte Zeile bei jeder Vergabe).
        Schema::create('nummernkreise', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mandant_id')->constrained('mandanten')->cascadeOnDelete();
            $table->unsignedSmallInteger('jahr');
            $table->unsignedInteger('letzte_nummer')->default(0);
            $table->timestamps();
            $table->unique(['mandant_id', 'jahr']);
        });
    }

    public function down(): void
    {
        foreach (['nummernkreise', 'tickets', 'auftragspositionen', 'kartenauftraege', 'verkaufsstellen_benutzer', 'verkaufsstellen', 'preisabweichungen', 'ticketkategorien'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
