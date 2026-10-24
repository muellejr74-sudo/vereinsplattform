<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stufe 4a – Ticketverwaltung „Der Abend“: Reservierungen (inkl. Vorkasse), Abendkasse,
 * Stornieren, Einlass (E40–E46a, E114a, E119–E127).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Ticket-Einstellungen je Verein (E40a, E121, E126, E127)
        Schema::create('ticket_einstellungen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mandant_id')->unique()->constrained('mandanten')->cascadeOnDelete();
            $table->string('reservierung_standard', 20)->default('gedruckt'); // gedruckt | digital
            $table->smallInteger('nicht_abgeholt_minuten')->default(0);       // relativ zum Beginn
            $table->unsignedSmallInteger('zahlungsfrist_tage')->default(7);
            $table->string('kontoinhaber', 80)->nullable();
            $table->string('iban', 34)->nullable();
            $table->timestamps();
        });

        Schema::create('reservierungen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mandant_id')->constrained('mandanten')->cascadeOnDelete();
            $table->unsignedInteger('nummer');                       // je Verein fortlaufend: R-0042
            $table->foreignId('vorstellung_id')->constrained('vorstellungen')->restrictOnDelete();
            $table->foreignId('verkaufsstelle_id')->nullable()->constrained('verkaufsstellen')->nullOnDelete(); // angenommen von; leer = Verein
            $table->string('art', 20);                               // gedruckt | digital | vorkasse
            $table->string('status', 20)->default('offen');          // offen | bezahlt | versendet | abgeholt | storniert
            $table->string('name', 80);
            $table->string('telefon', 40)->nullable();               // E120
            $table->string('strasse', 120)->nullable();              // E124 (nur Vorkasse)
            $table->string('plz', 10)->nullable();
            $table->string('ort', 80)->nullable();
            $table->string('bemerkung', 255)->nullable();
            $table->date('zahlungsfrist')->nullable();               // E126
            $table->string('zahlungsart', 20)->nullable();           // bar | karte | ueberweisung
            $table->foreignId('kartenauftrag_id')->nullable()->constrained('kartenauftraege')->nullOnDelete();
            $table->unsignedBigInteger('angelegt_von')->nullable();
            $table->unsignedBigInteger('bezahlt_von')->nullable();
            $table->timestamp('bezahlt_am')->nullable();
            $table->timestamp('versendet_am')->nullable();
            $table->timestamp('abgeholt_am')->nullable();
            $table->unsignedBigInteger('storniert_von')->nullable();
            $table->timestamp('storniert_am')->nullable();
            $table->timestamps();
            $table->unique(['mandant_id', 'nummer']);
            $table->index(['vorstellung_id', 'status']);
        });

        Schema::create('reservierungspositionen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mandant_id')->constrained('mandanten')->cascadeOnDelete();
            $table->foreignId('reservierung_id')->constrained('reservierungen')->cascadeOnDelete();
            $table->foreignId('kategorie_id')->constrained('ticketkategorien')->restrictOnDelete();
            $table->unsignedInteger('anzahl');
            $table->unsignedInteger('preis_cent');                   // Preis zum Zeitpunkt der Reservierung
            $table->unsignedInteger('anzahl_abgeholt')->nullable();  // E114a Teilabholung
            $table->timestamps();
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('reservierung_id')->nullable()->after('kartenauftrag_id')->constrained('reservierungen')->nullOnDelete();
            $table->string('zahlungsart', 20)->nullable()->after('preis_cent'); // bar | karte | ueberweisung (E42, E124)
            $table->timestamp('verkauft_am')->nullable();
            $table->unsignedBigInteger('verkauft_von')->nullable();
            $table->string('verkauf_ueber', 20)->nullable();         // abendkasse | vorkasse | reservierung
            $table->timestamp('eingelassen_am')->nullable();
            $table->string('eingelassen_durch', 120)->nullable();    // Station, z. B. „Einlass (Petra)“
            $table->timestamp('storniert_am')->nullable();
            $table->unsignedBigInteger('storniert_von')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reservierung_id');
            $table->dropColumn(['zahlungsart', 'verkauft_am', 'verkauft_von', 'verkauf_ueber', 'eingelassen_am', 'eingelassen_durch', 'storniert_am', 'storniert_von']);
        });
        Schema::dropIfExists('reservierungspositionen');
        Schema::dropIfExists('reservierungen');
        Schema::dropIfExists('ticket_einstellungen');
    }
};
