<?php

namespace App\Modules\Kern\Mandanten;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Zentraler Filter der Mandantentrennung (E14a).
 *
 * Wird automatisch an jede Abfrage eines mandantengebundenen Modells gehängt:
 * - Verein gesetzt  → nur Datensätze dieses Vereins
 * - kein Verein     → gar keine Datensätze (im Zweifel nichts zeigen)
 */
class MandantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $mandantId = app(Mandantenkontext::class)->id();

        if ($mandantId === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->qualifyColumn('mandant_id'), $mandantId);
    }
}
