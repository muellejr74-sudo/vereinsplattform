<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stufe 4b – „Der Abschluss“: Abschluss je Vorstellung (E4, E4a, E4b, E131) und
 * Abmeldung von den Druckauftrags-E-Mails (E132).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_abschluesse', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mandant_id')->constrained('mandanten')->cascadeOnDelete();
            $table->foreignId('vorstellung_id')->unique()->constrained('vorstellungen')->cascadeOnDelete();
            $table->unsignedBigInteger('abgeschlossen_von')->nullable();
            $table->timestamp('abgeschlossen_am');
            $table->timestamps();
        });

        // Wer hier steht, bekommt keine E-Mails zu neuen Druckaufträgen (Voreinstellung: an).
        Schema::create('ticket_mail_abmeldungen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_mail_abmeldungen');
        Schema::dropIfExists('ticket_abschluesse');
    }
};
