<?php

namespace App\Modules\Kern\Models;

use Illuminate\Database\Eloquent\Model;

/** Gerät, auf dem der Zwei-Faktor-Code 30 Tage nicht erneut abgefragt wird (E16d). */
class VertrautesGeraet extends Model
{
    protected $table = 'vertraute_geraete';

    protected $fillable = ['user_id', 'token_pruefsumme', 'bezeichnung', 'gueltig_bis'];

    protected function casts(): array
    {
        return ['gueltig_bis' => 'datetime'];
    }
}
