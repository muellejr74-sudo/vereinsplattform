<?php

namespace App\Modules\Kern\Http\Controllers\Verein;

use App\Http\Controllers\Controller;
use App\Modules\Kern\Dienste\AufkleberPdf;
use App\Modules\Kern\Dienste\Protokoll;
use App\Modules\Kern\Dienste\Tischverwaltung;
use App\Modules\Kern\Mandanten\Mandantenkontext;
use App\Modules\Kern\Models\Tischcode;
use App\Modules\Kern\Models\Tischzuordnung;
use App\Modules\Kern\Models\Veranstaltung;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Eigene QR-Codes des Vereins (E68, E68a, E69, E108) und Tischzuordnung (E52a, E54, E71, E74, E106).
 * Recht „QR-Codes erstellen und Tische zuordnen“.
 */
class TischeController extends Controller
{
    public function __construct(private Mandantenkontext $kontext, private Tischverwaltung $tische) {}

    public function index(): View
    {
        $codes = Tischcode::vomVerein($this->kontext->id())->orderBy('laufnummer')->get();
        $zuordnungen = Tischzuordnung::with('veranstaltung')->whereIn('qr_code_id', $codes->pluck('id'))->gueltig()->get()->keyBy('qr_code_id');

        return view('kern::verein.tische.index', [
            'codes' => $codes,
            'zuordnungen' => $zuordnungen,
            'layout' => AufkleberPdf::STANDARD,
        ]);
    }

    public function erzeugen(Request $request): RedirectResponse
    {
        $daten = $request->validate([
            'anzahl' => ['required', 'integer', 'min:1', 'max:200'],
            'einheitscode' => ['nullable', 'boolean'],
        ], [], ['anzahl' => 'Anzahl']);
        $einheit = $request->boolean('einheitscode');
        $anzahl = $einheit ? 1 : (int) $daten['anzahl'];
        $mandantId = $this->kontext->id();

        DB::transaction(function () use ($anzahl, $einheit, $mandantId) {
            $nummer = (int) Tischcode::vomVerein($mandantId)->lockForUpdate()->max('laufnummer');
            for ($i = 1; $i <= $anzahl; $i++) {
                $nummer++;
                Tischcode::create([
                    'mandant_id' => $mandantId,
                    'code' => Tischcode::neuerCode(),
                    'laufnummer' => $nummer,
                    'bezeichnung' => ($einheit ? 'Einheitscode ' : 'Code ').$nummer,
                    'einheitscode' => $einheit,
                ]);
            }
        });

        Protokoll::schreibe('qrcodes.erzeugt', null, ['anzahl' => $anzahl, 'einheitscode' => $einheit]);

        return back()->with('hinweis', $einheit ? 'Einheitscode erzeugt.' : $anzahl.' QR-Codes erzeugt. Sie können sie jetzt als PDF drucken.');
    }

    public function pdf(Request $request, AufkleberPdf $pdf): Response
    {
        $daten = $this->layoutPruefen($request);
        $codes = Tischcode::vomVerein($this->kontext->id())
            ->when($daten['codes'] ?? null, fn ($q, $ids) => $q->whereIn('id', $ids))
            ->orderBy('laufnummer')->get();
        abort_if($codes->isEmpty(), 422, 'Keine QR-Codes ausgewählt.');

        $verein = $this->kontext->mandant();

        return response($pdf->erzeuge($codes, $daten, 'QR-Codes '.$verein->name), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="qr-codes-'.strtolower($verein->kuerzel).'.pdf"',
        ]);
    }

    public function loeschen(Tischcode $tischcode): RedirectResponse
    {
        abort_unless($tischcode->mandant_id === $this->kontext->id(), 404);
        if ($this->tische->aktuelleZuordnung($tischcode)) {
            return back()->withErrors(['code' => 'Der Code ist noch einem Tisch zugeordnet. Bitte zuerst die Zuordnung lösen.']);
        }
        Protokoll::schreibe('qrcode.geloescht', $tischcode, ['code' => $tischcode->code, 'bezeichnung' => $tischcode->bezeichnung], null, $this->kontext->id());
        $tischcode->delete();

        return back()->with('hinweis', 'QR-Code gelöscht. Ein bereits gedruckter Aufkleber mit diesem Code funktioniert nicht mehr.');
    }

    // ---------------- Zuordnung ----------------

    /** Formular nach dem Scan (E106) oder aus der Liste der eigenen Codes. */
    public function zuordnenFormular(Request $request): View|RedirectResponse
    {
        $code = $request->query('code') ? $this->codeFinden((string) $request->query('code')) : null;
        $veranstaltungen = Veranstaltung::orderByDesc('id')->get();

        if ($veranstaltungen->isEmpty()) {
            return redirect()->route('verein.veranstaltungen.create')->with('hinweis', 'Bitte legen Sie zuerst eine Veranstaltung an.');
        }

        $vorauswahl = $veranstaltungen->firstWhere('id', (int) $request->query('veranstaltung')) ?? $veranstaltungen->first();

        return view('kern::verein.tische.zuordnen', [
            'code' => $code,
            'freieCodes' => $code ? collect() : Tischcode::vomVerein($this->kontext->id())
                ->whereNotIn('id', Tischzuordnung::gueltig()->pluck('qr_code_id'))->orderBy('laufnummer')->get(),
            'veranstaltungen' => $veranstaltungen,
            'vorauswahl' => $vorauswahl,
            'vorschlaege' => $veranstaltungen->mapWithKeys(fn ($v) => [$v->id => $v->vorschlagGueltigBis()->toDateString()]),
            'bisher' => $bisher = $code ? $this->tische->aktuelleZuordnung($code) : null,
            // Tischnummer nur vorbelegen, wenn der Tisch schon im eigenen Verein zugeordnet war.
            'bisherigeNummer' => $bisher && (int) $bisher->mandant_id === $this->kontext->id() ? $bisher->tischnummer : null,
        ]);
    }

    public function zuordnen(Request $request): RedirectResponse
    {
        $daten = $request->validate([
            'code' => ['required', 'string', 'max:16'],
            'veranstaltung_id' => ['required', 'integer', Rule::exists('veranstaltungen', 'id')->where('mandant_id', $this->kontext->id())],
            'tischnummer' => ['nullable', 'string', 'max:10'],
            'gueltig_bis' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
        ], [], ['veranstaltung_id' => 'Veranstaltung', 'tischnummer' => 'Tischnummer', 'gueltig_bis' => 'Gültig bis']);

        $code = $this->codeFinden($daten['code']);
        $veranstaltung = Veranstaltung::findOrFail($daten['veranstaltung_id']);

        try {
            $ergebnis = $this->tische->zuordnen($code, $veranstaltung, $daten['tischnummer'] ?? null, Carbon::parse($daten['gueltig_bis']), $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['tischnummer' => $e->getMessage()]);
        }

        $z = $ergebnis['zuordnung'];
        $text = $code->einheitscode
            ? 'Einheitscode der Veranstaltung „'.$veranstaltung->name.'“ zugeordnet.'
            : 'Tisch '.$z->tischnummer.' ist jetzt „'.$veranstaltung->name.'“ zugeordnet (bis '.$z->gueltig_bis->format('d.m.Y').'). Bitte die Nummer ins Freifeld des Aufklebers schreiben.';

        // Nach dem Scan direkt zurück zur Kamera – für den nächsten Tisch.
        return redirect()->route('verein.tische.erfolg', ['veranstaltung' => $veranstaltung->id])->with('hinweis', $text);
    }

    public function erfolg(Request $request): View
    {
        $veranstaltung = Veranstaltung::findOrFail((int) $request->query('veranstaltung'));

        return view('kern::verein.tische.erfolg', [
            'veranstaltung' => $veranstaltung,
            'anzahl' => Tischzuordnung::where('veranstaltung_id', $veranstaltung->id)->gueltig()->whereNotNull('tischnummer')->count(),
        ]);
    }

    public function verlaengern(Request $request, Tischzuordnung $zuordnung): RedirectResponse
    {
        $daten = $request->validate(['gueltig_bis' => ['required', 'date_format:Y-m-d', 'after_or_equal:today']], [], ['gueltig_bis' => 'Gültig bis']);
        $zuordnung->update(['gueltig_bis' => $daten['gueltig_bis']]);
        Protokoll::schreibe('tisch.zugeordnet', $zuordnung, ['tischnummer' => $zuordnung->tischnummer, 'gueltig_bis' => $daten['gueltig_bis'], 'verlaengert' => true]);

        return back()->with('hinweis', 'Gültigkeit geändert.');
    }

    /** Alle Tische einer Veranstaltung auf ein Datum setzen (z. B. nach Terminverschiebung). */
    public function alleVerlaengern(Request $request, Veranstaltung $veranstaltung): RedirectResponse
    {
        $daten = $request->validate(['gueltig_bis' => ['required', 'date_format:Y-m-d', 'after_or_equal:today']], [], ['gueltig_bis' => 'Gültig bis']);
        $anzahl = Tischzuordnung::where('veranstaltung_id', $veranstaltung->id)->gueltig()->update(['gueltig_bis' => $daten['gueltig_bis']]);
        Protokoll::schreibe('tisch.zugeordnet', $veranstaltung, ['alle' => $anzahl, 'gueltig_bis' => $daten['gueltig_bis'], 'verlaengert' => true]);

        return back()->with('hinweis', $anzahl.' Tische gelten jetzt bis '.Carbon::parse($daten['gueltig_bis'])->format('d.m.Y').'.');
    }

    public function loesen(Request $request, Tischzuordnung $zuordnung): RedirectResponse
    {
        $this->tische->loesen($zuordnung, $request->user());

        return back()->with('hinweis', 'Die Zuordnung wurde gelöst.');
    }

    // ------------------------------------------------------------------

    /** Betreiber-Tische (alle Vereine) oder eigene Vereins-Codes – fremde Vereins-Codes nie. */
    private function codeFinden(string $code): Tischcode
    {
        $tischcode = Tischcode::where('code', strtoupper(trim($code)))->first();
        abort_unless($tischcode && ($tischcode->istBetreiberTisch() || $tischcode->mandant_id === $this->kontext->id()), 404, 'Dieser QR-Code kann von Ihrem Verein nicht zugeordnet werden.');

        return $tischcode;
    }

    /** @return array<string, mixed> */
    public function layoutPruefen(Request $request): array
    {
        $daten = $request->validate([
            'spalten' => ['nullable', 'integer', 'min:1', 'max:6'],
            'zeilen' => ['nullable', 'integer', 'min:1', 'max:12'],
            'rand_oben' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'rand_links' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'abstand_waagerecht' => ['nullable', 'numeric', 'min:0', 'max:30'],
            'abstand_senkrecht' => ['nullable', 'numeric', 'min:0', 'max:30'],
            'start' => ['nullable', 'integer', 'min:1', 'max:72'],
            'rahmen' => ['nullable', 'boolean'],
            'codes' => ['nullable', 'array'],
            'codes.*' => ['integer'],
        ]);
        $daten = array_filter($daten, fn ($w) => $w !== null);
        $daten['rahmen'] = $request->boolean('rahmen');

        return $daten;
    }
}
