<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web-Routen
|--------------------------------------------------------------------------
|
| Stufe 0: nur die Startseite. Die Routen der Module (Kern, Ticket, Saal,
| Gästeportal, Werbung) werden in späteren Stufen in ihren eigenen Modulen
| angelegt und hier eingebunden.
|
*/

Route::view('/', 'startseite')->name('startseite');
