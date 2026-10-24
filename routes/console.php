<?php

use App\Modules\Kern\Dienste\Tischverwaltung;
use App\Modules\Kern\Models\Protokolleintrag;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Zeitgesteuerte Aufgaben
|--------------------------------------------------------------------------
|
| Werden vom Cronjob im Minutentakt angestoßen ("php artisan schedule:run").
|
*/

// Protokolleinträge älter als 2 Jahre automatisch löschen (E89).
Schedule::command('model:prune', ['--model' => [Protokolleintrag::class]])->dailyAt('03:15');

// Abgelaufene Tischzuordnungen lösen – kurz nach Mitternacht (E71, E107).
Schedule::call(fn () => app(Tischverwaltung::class)->abgelaufeneLoesen())
    ->name('tischzuordnungen-loesen')->dailyAt('00:05');
