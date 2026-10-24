<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stufe 2: Gemeinsame Stammdaten (E35, E36), Stationen (E29, E34), QR-Codes und
 * Tischzuordnungen (E52–E54a, E68–E74). Alle Vereinstabellen tragen mandant_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mandanten', function (Blueprint $table) {
            $table->timestamp('stationen_angelegt_am')->nullable()->after('farbe_sekundaer');
        });

        Schema::create('orte', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mandant_id')->constrained('mandanten')->cascadeOnDelete();
            $table->string('name', 120);
            $table->timestamps();
        });

        Schema::create('saele', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mandant_id')->constrained('mandanten')->cascadeOnDelete();
            $table->foreignId('ort_id')->constrained('orte')->cascadeOnDelete();
            $table->string('name', 120);
            $table->timestamps();
        });

        Schema::create('veranstaltungen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mandant_id')->constrained('mandanten')->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('kurzname', 40)->nullable(); // für das Ticket-Etikett (E77)
            $table->text('beschreibung')->nullable();
            $table->timestamps();
        });

        Schema::create('vorstellungen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mandant_id')->constrained('mandanten')->cascadeOnDelete();
            $table->foreignId('veranstaltung_id')->constrained('veranstaltungen')->cascadeOnDelete();
            $table->foreignId('saal_id')->constrained('saele')->restrictOnDelete();
            $table->dateTime('einlass_um')->nullable();
            $table->dateTime('beginn_um');
            $table->dateTime('ende_um')->nullable();
            $table->unsignedInteger('kontingent')->nullable(); // genutzt ab Stufe 3 (E39)
            $table->string('einsatz_code', 6)->nullable()->index();
            $table->timestamps();
            $table->index(['mandant_id', 'beginn_um']);
        });

        Schema::create('stationen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mandant_id')->constrained('mandanten')->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('art', 20); // bedienung | ausschank | kueche | einlass | sonstige
            $table->boolean('mehrere_geraete')->default(false); // E33
            $table->unsignedSmallInteger('reihenfolge')->default(0);
            $table->timestamps();
        });

        Schema::create('stationsbesetzungen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mandant_id')->constrained('mandanten')->cascadeOnDelete();
            $table->foreignId('vorstellung_id')->constrained('vorstellungen')->cascadeOnDelete();
            $table->foreignId('station_id')->constrained('stationen')->cascadeOnDelete();
            $table->string('name', 60);
            $table->timestamps();
            $table->unique(['vorstellung_id', 'station_id']);
        });

        Schema::create('stationsgeraete', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mandant_id')->constrained('mandanten')->cascadeOnDelete();
            $table->foreignId('vorstellung_id')->constrained('vorstellungen')->cascadeOnDelete();
            $table->foreignId('station_id')->constrained('stationen')->cascadeOnDelete();
            $table->string('token_pruefsumme', 64)->unique();
            $table->string('geraet', 180)->nullable();
            $table->timestamp('angemeldet_am');
            $table->timestamp('zuletzt_aktiv_am')->nullable();
            $table->timestamp('abgemeldet_am')->nullable();
            $table->timestamps();
            $table->index(['vorstellung_id', 'station_id', 'abgemeldet_am']);
        });

        // QR-Codes: Betreiber-Tische (mandant_id leer) und Vereins-Codes (E52, E68).
        Schema::create('qr_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mandant_id')->nullable()->constrained('mandanten')->cascadeOnDelete();
            $table->string('code', 16)->unique();
            $table->string('bezeichnung', 60); // interne ID, z. B. "Tisch 12" – NICHT die Tischnummer (E52)
            $table->unsignedInteger('laufnummer');
            $table->boolean('einheitscode')->default(false); // E69, E108
            $table->timestamps();
            $table->index(['mandant_id', 'laufnummer']);
        });

        // Ein Code ist immer nur mit einer Veranstaltung verknüpft (E74) → unique(qr_code_id).
        Schema::create('tischzuordnungen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mandant_id')->constrained('mandanten')->cascadeOnDelete();
            $table->foreignId('qr_code_id')->unique()->constrained('qr_codes')->cascadeOnDelete();
            $table->foreignId('veranstaltung_id')->constrained('veranstaltungen')->cascadeOnDelete();
            $table->string('tischnummer', 10)->nullable(); // leer beim Einheitscode
            $table->date('gueltig_bis'); // E71
            $table->unsignedBigInteger('zugeordnet_von')->nullable();
            $table->timestamps();
            $table->index(['veranstaltung_id', 'tischnummer']);
        });
    }

    public function down(): void
    {
        Schema::table('mandanten', fn (Blueprint $table) => $table->dropColumn('stationen_angelegt_am'));
        foreach (['tischzuordnungen', 'qr_codes', 'stationsgeraete', 'stationsbesetzungen', 'stationen', 'vorstellungen', 'veranstaltungen', 'saele', 'orte'] as $tabelle) {
            Schema::dropIfExists($tabelle);
        }
    }
};
