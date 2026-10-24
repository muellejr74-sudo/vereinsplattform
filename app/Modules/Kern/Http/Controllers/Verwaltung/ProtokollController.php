<?php

namespace App\Modules\Kern\Http\Controllers\Verwaltung;

use App\Http\Controllers\Controller;
use App\Modules\Kern\Dienste\Protokoll;
use App\Modules\Kern\Models\Mandant;
use App\Modules\Kern\Models\Protokolleintrag;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Protokoll ansehen (E84). In Stufe 1a nur für den Super-Admin; die Ansicht
 * für Vereinsadmins (nur eigener Verein) folgt mit Stufe 1b.
 */
class ProtokollController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->validate([
            'verein' => ['nullable', 'string', 'max:20'],
            'aktion' => ['nullable', 'string', 'max:60'],
            'suche' => ['nullable', 'string', 'max:100'],
        ]);

        $eintraege = Protokolleintrag::with('mandant')
            ->when(($filter['verein'] ?? '') === 'plattform', fn ($q) => $q->whereNull('mandant_id'))
            ->when(ctype_digit((string) ($filter['verein'] ?? '')), fn ($q) => $q->where('mandant_id', (int) $filter['verein']))
            ->when($filter['aktion'] ?? null, fn ($q, $aktion) => $q->where('aktion', $aktion))
            ->when($filter['suche'] ?? null, fn ($q, $suche) => $q->where(fn ($q) => $q
                ->where('akteur', 'like', '%'.$suche.'%')
                ->orWhere('details', 'like', '%'.$suche.'%')))
            ->orderByDesc('zeitpunkt')->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('kern::verwaltung.protokoll', [
            'eintraege' => $eintraege,
            'vereine' => Mandant::orderBy('name')->get(),
            'aktionen' => Protokoll::BEZEICHNUNGEN,
            'filter' => $filter,
        ]);
    }
}
