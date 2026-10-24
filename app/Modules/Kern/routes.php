<?php

use App\Modules\Kern\Http\Controllers\AnmeldungController;
use App\Modules\Kern\Http\Controllers\KontoController;
use App\Modules\Kern\Http\Controllers\PasswortController;
use App\Modules\Kern\Http\Controllers\RechtstexteController;
use App\Modules\Kern\Http\Controllers\StartseiteController;
use App\Modules\Kern\Http\Controllers\Verwaltung\BenutzerController;
use App\Modules\Kern\Http\Controllers\Verwaltung\ProtokollController;
use App\Modules\Kern\Http\Controllers\Verwaltung\RechtstexteController as RechtstexteVerwaltung;
use App\Modules\Kern\Http\Controllers\Verwaltung\VereineController;
use Illuminate\Support\Facades\Route;

/*
| Adressen des Plattformkerns (Stufe 1a).
| Alle Seiten laufen über die Middleware-Gruppe "web" (Sitzung, Formularschutz, Mandantenkontext).
*/

// Öffentlich: Startseite und Rechtstexte (E83)
Route::get('/', [StartseiteController::class, 'anzeigen'])->middleware('zweifaktor')->name('startseite');
Route::get('/impressum', [RechtstexteController::class, 'impressum'])->name('impressum');
Route::get('/datenschutz', [RechtstexteController::class, 'datenschutz'])->name('datenschutz');
Route::get('/verein/{kuerzel}/impressum', [RechtstexteController::class, 'vereinsimpressum'])->name('verein.impressum');
Route::get('/verein/{kuerzel}/datenschutz', [RechtstexteController::class, 'vereinsdatenschutz'])->name('verein.datenschutz');

// Nur für nicht angemeldete Besucher
Route::middleware('guest')->group(function () {
    Route::get('/anmelden', [AnmeldungController::class, 'formular'])->name('anmeldung');
    Route::post('/anmelden', [AnmeldungController::class, 'anmelden']);
    Route::get('/anmelden/code', [AnmeldungController::class, 'zweiFaktorFormular'])->name('anmeldung.zweifaktor');
    Route::post('/anmelden/code', [AnmeldungController::class, 'zweiFaktorPruefen']);

    Route::get('/passwort-vergessen', [PasswortController::class, 'vergessenFormular'])->name('passwort.vergessen');
    Route::post('/passwort-vergessen', [PasswortController::class, 'linkSenden'])->middleware('throttle:6,1');
    Route::get('/passwort-zuruecksetzen/{token}', [PasswortController::class, 'zuruecksetzenFormular'])->name('passwort.formular');
    Route::post('/passwort-zuruecksetzen', [PasswortController::class, 'zuruecksetzen'])->middleware('throttle:10,1')->name('passwort.speichern');
    Route::get('/einladung/{token}', [PasswortController::class, 'einladungFormular'])->name('einladung.formular');
    Route::post('/einladung', [PasswortController::class, 'einladungAnnehmen'])->middleware('throttle:10,1')->name('einladung.speichern');
});

// Angemeldet
Route::middleware('auth')->group(function () {
    Route::post('/abmelden', [AnmeldungController::class, 'abmelden'])->name('abmelden');

    // Zwei-Faktor einrichten muss VOR der Pflichtprüfung erreichbar sein.
    Route::get('/konto/zwei-faktor/einrichten', [KontoController::class, 'zweiFaktorEinrichten'])->name('zweifaktor.einrichten');
    Route::post('/konto/zwei-faktor/einrichten', [KontoController::class, 'zweiFaktorBestaetigen']);

    Route::middleware('zweifaktor')->group(function () {
        Route::post('/verein-wechseln', [StartseiteController::class, 'vereinWechseln'])->name('verein.wechseln');

        Route::get('/konto', [KontoController::class, 'anzeigen'])->name('konto');
        Route::put('/konto/passwort', [KontoController::class, 'passwortAendern'])->name('konto.passwort');
        Route::post('/konto/zwei-faktor/notfallcodes', [KontoController::class, 'notfallcodesErneuern'])->name('zweifaktor.notfallcodes');
        Route::post('/konto/zwei-faktor/geraete-abmelden', [KontoController::class, 'geraeteAbmelden'])->name('zweifaktor.geraete');

        // Verwaltung – nur Super-Admin
        Route::middleware('superadmin')->prefix('verwaltung')->name('verwaltung.')->group(function () {
            Route::view('/', 'kern::verwaltung.uebersicht')->name('uebersicht');

            Route::resource('vereine', VereineController::class)->except(['show', 'destroy'])->parameters(['vereine' => 'verein']);
            Route::post('vereine/{verein}/aktiv', [VereineController::class, 'aktivUmschalten'])->name('vereine.aktiv');
            Route::get('vereine/{verein}/texte', [VereineController::class, 'texte'])->name('vereine.texte');
            Route::put('vereine/{verein}/texte', [VereineController::class, 'texteSpeichern']);

            Route::resource('benutzer', BenutzerController::class)->except(['show', 'destroy'])->parameters(['benutzer' => 'person']);
            Route::post('benutzer/{person}/aktiv', [BenutzerController::class, 'aktivUmschalten'])->name('benutzer.aktiv');
            Route::post('benutzer/{person}/einladung', [BenutzerController::class, 'einladungErneut'])->name('benutzer.einladung');

            Route::get('protokoll', [ProtokollController::class, 'index'])->name('protokoll');
            Route::get('rechtstexte', [RechtstexteVerwaltung::class, 'bearbeiten'])->name('rechtstexte');
            Route::put('rechtstexte', [RechtstexteVerwaltung::class, 'speichern']);
        });
    });
});
