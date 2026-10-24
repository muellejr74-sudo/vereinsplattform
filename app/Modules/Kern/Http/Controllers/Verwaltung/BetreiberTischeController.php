<?php

namespace App\Modules\Kern\Http\Controllers\Verwaltung;

use App\Http\Controllers\Controller;
use App\Modules\Kern\Dienste\AufkleberPdf;
use App\Modules\Kern\Dienste\Protokoll;
use App\Modules\Kern\Dienste\Tischverwaltung;
use App\Modules\Kern\Http\Controllers\Verein\TischeController;
use App\Modules\Kern\Models\Mandant;
use App\Modules\Kern\Models\Tischcode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Liste der Betreiber-Tische (E52) – nur Super-Admin. Keine Verleihverwaltung:
 * Die Übersicht zeigt lediglich, wo ein Tisch gerade zugeordnet ist.
 */
class BetreiberTischeController extends Controller
{
    public function index(Tischverwaltung $tische): View
    {
        $codes = Tischcode::betreiber()->orderBy('laufnummer')->get();
        $zuordnungen = DB::table('tischzuordnungen')
            ->join('veranstaltungen', 'veranstaltungen.id', '=', 'tischzuordnungen.veranstaltung_id')
            ->whereIn('qr_code_id', $codes->pluck('id'))
            ->whereDate('gueltig_bis', '>=', today())
            ->get(['qr_code_id', 'tischzuordnungen.mandant_id', 'tischnummer', 'gueltig_bis', 'veranstaltungen.name as veranstaltung'])
            ->keyBy('qr_code_id');

        return view('kern::verwaltung.tische', [
            'codes' => $codes,
            'zuordnungen' => $zuordnungen,
            'vereine' => Mandant::pluck('kuerzel', 'id'),
            'layout' => AufkleberPdf::STANDARD,
        ]);
    }

    public function erzeugen(Request $request): RedirectResponse
    {
        $daten = $request->validate(['anzahl' => ['required', 'integer', 'min:1', 'max:500']], [], ['anzahl' => 'Anzahl']);

        DB::transaction(function () use ($daten) {
            $nummer = (int) Tischcode::betreiber()->lockForUpdate()->max('laufnummer');
            for ($i = 1; $i <= $daten['anzahl']; $i++) {
                $nummer++;
                Tischcode::create(['mandant_id' => null, 'code' => Tischcode::neuerCode(), 'laufnummer' => $nummer, 'bezeichnung' => 'Tisch '.$nummer]);
            }
        });
        Protokoll::schreibe('qrcodes.erzeugt', null, ['anzahl' => (int) $daten['anzahl'], 'betreiber' => true], null, null);

        return back()->with('hinweis', $daten['anzahl'].' Betreiber-Tische angelegt.');
    }

    public function pdf(Request $request, AufkleberPdf $pdf): Response
    {
        $daten = app(TischeController::class)->layoutPruefen($request);
        $codes = Tischcode::betreiber()
            ->when($daten['codes'] ?? null, fn ($q, $ids) => $q->whereIn('id', $ids))
            ->orderBy('laufnummer')->get();
        abort_if($codes->isEmpty(), 422, 'Keine Tische ausgewählt.');

        return response($pdf->erzeuge($codes, $daten, 'Betreiber-Tische'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="betreiber-tische.pdf"',
        ]);
    }

    public function loeschen(Tischcode $tischcode, Tischverwaltung $tische): RedirectResponse
    {
        abort_unless($tischcode->istBetreiberTisch(), 404);
        if ($tische->aktuelleZuordnung($tischcode)) {
            return back()->withErrors(['code' => 'Der Tisch ist gerade einem Verein zugeordnet und kann nicht gelöscht werden.']);
        }
        Protokoll::schreibe('qrcode.geloescht', $tischcode, ['code' => $tischcode->code, 'bezeichnung' => $tischcode->bezeichnung], null, null);
        $tischcode->delete();

        return back()->with('hinweis', 'Tisch gelöscht.');
    }
}
