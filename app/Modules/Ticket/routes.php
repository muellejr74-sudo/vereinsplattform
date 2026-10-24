<?php

use App\Modules\Ticket\Http\Controllers\AuftraegeController;
use App\Modules\Ticket\Http\Controllers\PruefenController;
use App\Modules\Ticket\Http\Controllers\UebersichtController;
use App\Modules\Ticket\Http\Controllers\VeranstaltungTicketsController;
use App\Modules\Ticket\Http\Controllers\VerkaufsstellenController;
use Illuminate\Support\Facades\Route;

/*
| Adressen der Ticketverwaltung (Stufe 3).
*/

// Öffentliche Prüfseite des Ticket-QR-Codes (E112)
Route::get('/k/{code}', PruefenController::class)->where('code', '[A-Za-z0-9.\-]{10,40}')->middleware('throttle:60,1')->name('ticket.pruefen');

Route::middleware(['auth', 'zweifaktor', 'verein', 'ticketmodul'])->prefix('tickets')->name('tickets.')->group(function () {
    Route::get('/', UebersichtController::class)->name('uebersicht');

    // Kategorien, Preise, Kontingente (E38, E39)
    Route::get('veranstaltungen/{veranstaltung}', [VeranstaltungTicketsController::class, 'show'])->whereNumber('veranstaltung')->name('veranstaltung');
    Route::middleware('can:ticket.kategorien')->group(function () {
        Route::post('veranstaltungen/{veranstaltung}/kategorien', [VeranstaltungTicketsController::class, 'kategorieSpeichern'])->whereNumber('veranstaltung')->name('kategorien.neu');
        Route::put('veranstaltungen/{veranstaltung}/kategorien/{kategorie}', [VeranstaltungTicketsController::class, 'kategorieSpeichern'])->whereNumber(['veranstaltung', 'kategorie'])->name('kategorien.speichern');
        Route::delete('veranstaltungen/{veranstaltung}/kategorien/{kategorie}', [VeranstaltungTicketsController::class, 'kategorieLoeschen'])->whereNumber(['veranstaltung', 'kategorie'])->name('kategorien.loeschen');
        Route::put('veranstaltungen/{veranstaltung}/vorstellungen/{vorstellung}/preise', [VeranstaltungTicketsController::class, 'abweichungenSpeichern'])->whereNumber(['veranstaltung', 'vorstellung'])->name('preise');
    });
    Route::put('veranstaltungen/{veranstaltung}/vorstellungen/{vorstellung}/kontingent', [VeranstaltungTicketsController::class, 'kontingentSpeichern'])
        ->whereNumber(['veranstaltung', 'vorstellung'])->middleware('can:ticket.kontingente')->name('kontingent');

    // Verkaufsstellen (E24, E46, E115)
    Route::middleware('can:ticket.verkaufsstellen')->group(function () {
        Route::get('verkaufsstellen', [VerkaufsstellenController::class, 'index'])->name('verkaufsstellen');
        Route::post('verkaufsstellen', [VerkaufsstellenController::class, 'speichern'])->name('verkaufsstellen.neu');
        Route::put('verkaufsstellen/{stelle}', [VerkaufsstellenController::class, 'speichern'])->whereNumber('stelle')->name('verkaufsstellen.speichern');
    });

    // Kartenaufträge und Freikarten-Stapel
    Route::get('auftraege', [AuftraegeController::class, 'index'])->name('auftraege.index');
    Route::get('auftraege/neu', [AuftraegeController::class, 'create'])->name('auftraege.create');
    Route::post('auftraege', [AuftraegeController::class, 'store'])->name('auftraege.store');
    Route::get('auftraege/{auftrag}', [AuftraegeController::class, 'show'])->whereNumber('auftrag')->name('auftraege.show');
    Route::post('auftraege/{auftrag}/zurueckziehen', [AuftraegeController::class, 'zurueckziehen'])->whereNumber('auftrag')->name('auftraege.zurueckziehen');

    // Druckstelle (E26, E31)
    Route::middleware('can:ticket.drucken')->group(function () {
        Route::post('auftraege/{auftrag}/bestaetigen', [AuftraegeController::class, 'bestaetigen'])->whereNumber('auftrag')->name('auftraege.bestaetigen');
        Route::post('auftraege/{auftrag}/ablehnen', [AuftraegeController::class, 'ablehnen'])->whereNumber('auftrag')->name('auftraege.ablehnen');
        Route::get('auftraege/{auftrag}/etiketten.pdf', [AuftraegeController::class, 'etiketten'])->whereNumber('auftrag')->name('auftraege.etiketten');
        Route::post('auftraege/{auftrag}/gedruckt', [AuftraegeController::class, 'gedruckt'])->whereNumber('auftrag')->name('auftraege.gedruckt');
        Route::post('auftraege/{auftrag}/uebergeben', [AuftraegeController::class, 'uebergeben'])->whereNumber('auftrag')->name('auftraege.uebergeben');
        Route::post('karten/{ticket}/nachdruck', [AuftraegeController::class, 'nachdruck'])->whereNumber('ticket')->name('nachdruck');
    });
});
