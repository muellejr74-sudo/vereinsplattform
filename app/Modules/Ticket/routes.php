<?php

use App\Modules\Ticket\Http\Controllers\AbendkasseController;
use App\Modules\Ticket\Http\Controllers\AuftraegeController;
use App\Modules\Ticket\Http\Controllers\EinlassController;
use App\Modules\Ticket\Http\Controllers\EinstellungenController;
use App\Modules\Ticket\Http\Controllers\PruefenController;
use App\Modules\Ticket\Http\Controllers\ReservierungenController;
use App\Modules\Ticket\Http\Controllers\SelbstdruckController;
use App\Modules\Ticket\Http\Controllers\StornoController;
use App\Modules\Ticket\Http\Controllers\UebersichtController;
use App\Modules\Ticket\Http\Controllers\VeranstaltungTicketsController;
use App\Modules\Ticket\Http\Controllers\VerkaufsstellenController;
use Illuminate\Support\Facades\Route;

/*
| Adressen der Ticketverwaltung (Stufe 3 und 4a).
*/

// Einlass-Station ohne Benutzerkonto, angemeldet über den Einsatz-Code (E29, E119)
Route::get('/station/einlass', [EinlassController::class, 'show'])->name('station.einlass');
Route::post('/station/einlass', [EinlassController::class, 'scannen'])->middleware('throttle:180,1');

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

    // Verkaufsstelle druckt selbst (E128)
    Route::get('verkaufen', [SelbstdruckController::class, 'create'])->name('selbstdruck.create');
    Route::post('verkaufen', [SelbstdruckController::class, 'store'])->name('selbstdruck.store');
    Route::get('verkaufen/kassieren', [SelbstdruckController::class, 'kassieren'])->name('selbstdruck.kassieren');
    Route::get('verkaufen/{auftrag}', [SelbstdruckController::class, 'show'])->whereNumber('auftrag')->name('selbstdruck.show');
    Route::get('verkaufen/{auftrag}/etiketten.pdf', [SelbstdruckController::class, 'etiketten'])->whereNumber('auftrag')->name('selbstdruck.etiketten');
    Route::post('verkaufen/karten/{ticket}/nachdruck', [SelbstdruckController::class, 'nachdruck'])->whereNumber('ticket')->name('selbstdruck.nachdruck');

    // Reservierungen und Vorkasse (E40, E124–E127)
    Route::get('reservierungen', [ReservierungenController::class, 'index'])->name('reservierungen.index');
    Route::get('reservierungen/neu', [ReservierungenController::class, 'create'])->name('reservierungen.create');
    Route::post('reservierungen', [ReservierungenController::class, 'store'])->name('reservierungen.store');
    Route::post('reservierungen/ueberfaellig/stornieren', [ReservierungenController::class, 'ueberfaelligeStornieren'])->name('reservierungen.ueberfaellig');
    Route::get('reservierungen/{reservierung}', [ReservierungenController::class, 'show'])->whereNumber('reservierung')->name('reservierungen.show');
    Route::post('reservierungen/{reservierung}/bezahlt', [ReservierungenController::class, 'bezahlt'])->whereNumber('reservierung')->middleware('can:ticket.zahlungseingang')->name('reservierungen.bezahlt');
    Route::post('reservierungen/{reservierung}/abholung', [ReservierungenController::class, 'abholung'])->whereNumber('reservierung')->name('reservierungen.abholung');
    Route::post('reservierungen/{reservierung}/stornieren', [ReservierungenController::class, 'stornieren'])->whereNumber('reservierung')->name('reservierungen.stornieren');

    // Einstellungen (E40a, E121, E126, E127)
    Route::get('einstellungen', [EinstellungenController::class, 'show'])->name('einstellungen');
    Route::put('einstellungen', [EinstellungenController::class, 'allgemein'])->middleware('can:ticket.reservierungen')->name('einstellungen.allgemein');
    Route::put('einstellungen/bank', [EinstellungenController::class, 'bank'])->middleware('can:ticket.zahlungseingang')->name('einstellungen.bank');

    // Abendkasse (E32, E41–E46a, E114)
    Route::middleware('can:ticket.abendkasse')->prefix('abendkasse')->name('abendkasse.')->group(function () {
        Route::get('/', [AbendkasseController::class, 'index'])->name('index');
    });
    Route::middleware(['can:ticket.abendkasse', 'abendkasse.offen'])->prefix('abendkasse')->name('abendkasse.')->group(function () {
        Route::get('{vorstellung}', [AbendkasseController::class, 'show'])->whereNumber('vorstellung')->name('show');
        Route::get('{vorstellung}/verkauf', [AbendkasseController::class, 'verkaufFormular'])->whereNumber('vorstellung')->name('verkauf');
        Route::post('{vorstellung}/verkauf', [AbendkasseController::class, 'verkauf'])->whereNumber('vorstellung');
        Route::get('{vorstellung}/reservierungen', [AbendkasseController::class, 'reservierungen'])->whereNumber('vorstellung')->name('reservierungen');
        Route::get('{vorstellung}/reservierungen/{reservierung}', [AbendkasseController::class, 'reservierung'])->whereNumber(['vorstellung', 'reservierung'])->name('reservierung');
        Route::post('{vorstellung}/reservierungen/{reservierung}', [AbendkasseController::class, 'ausgeben'])->whereNumber(['vorstellung', 'reservierung']);
        Route::get('{vorstellung}/bestand', [AbendkasseController::class, 'bestandFormular'])->whereNumber('vorstellung')->name('bestand.scan');
        Route::post('{vorstellung}/bestand', [AbendkasseController::class, 'bestandScan'])->whereNumber('vorstellung');
        Route::get('{vorstellung}/bestand/{ticket}', [AbendkasseController::class, 'bestand'])->whereNumber(['vorstellung', 'ticket'])->name('bestand');
        Route::post('{vorstellung}/bestand/{ticket}', [AbendkasseController::class, 'bestandVerkaufen'])->whereNumber(['vorstellung', 'ticket']);
        Route::get('{vorstellung}/freikarten', [AbendkasseController::class, 'freikartenListe'])->whereNumber('vorstellung')->name('freikarten.liste');
        Route::post('{vorstellung}/freikarten/{auftrag}', [AbendkasseController::class, 'freikarten'])->whereNumber(['vorstellung', 'auftrag'])->name('freikarten');
        Route::get('{vorstellung}/kassieren', [AbendkasseController::class, 'kassieren'])->whereNumber('vorstellung')->name('kassieren');
        Route::get('{vorstellung}/einlass', [AbendkasseController::class, 'einlass'])->whereNumber('vorstellung')->name('einlass');
        Route::post('{vorstellung}/einlass', [AbendkasseController::class, 'einlassScannen'])->whereNumber('vorstellung')->middleware('throttle:180,1');
        Route::get('{vorstellung}/nicht-abgeholt', [AbendkasseController::class, 'nichtAbgeholtListe'])->whereNumber('vorstellung')->name('nicht_abgeholt');
        Route::post('{vorstellung}/nicht-abgeholt', [AbendkasseController::class, 'nichtAbgeholt'])->whereNumber('vorstellung');
    });

    // Stornieren einzeln und per Dauer-Scan (E1, E4b, E119)
    Route::middleware('can:ticket.stornieren')->prefix('stornieren')->name('storno.')->group(function () {
        Route::get('/', [StornoController::class, 'index'])->name('index');
        Route::post('erfassen', [StornoController::class, 'erfassen'])->middleware('throttle:240,1')->name('erfassen');
        Route::post('entfernen/{ticket}', [StornoController::class, 'entfernen'])->whereNumber('ticket')->name('entfernen');
        Route::post('leeren', [StornoController::class, 'leeren'])->name('leeren');
        Route::post('ausfuehren', [StornoController::class, 'ausfuehren'])->name('ausfuehren');
    });
});
