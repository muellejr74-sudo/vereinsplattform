<?php

namespace App\Modules\Ticket\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Kern\Models\Vorstellung;
use App\Modules\Ticket\Dienste\Abschlusssperre;
use App\Modules\Ticket\Dienste\Auswertung;
use App\Modules\Ticket\Dienste\Berichte;
use App\Modules\Ticket\Models\Verkaufsstelle;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** Auswertungen je Vorstellung (E79, E79c) und Notfallliste (E2a). */
class AuswertungController extends Controller
{
    public function show(Vorstellung $vorstellung, Auswertung $auswertung): View
    {
        return view('ticket::auswertung.show', [
            'v' => $vorstellung->load('veranstaltung'),
            'z' => $auswertung->zahlen($vorstellung),
            'abrechnung' => $auswertung->abrechnung($vorstellung),
            'freikarten' => $auswertung->freikartenliste($vorstellung),
            'abgeschlossen' => Abschlusssperre::istAbgeschlossen($vorstellung->id),
        ]);
    }

    public function bericht(Vorstellung $vorstellung, Berichte $berichte): Response
    {
        return $this->pdf($berichte->abschlussbericht($vorstellung->load('veranstaltung')), 'abschlussbericht-'.$vorstellung->id.'.pdf');
    }

    public function abrechnung(Vorstellung $vorstellung, Verkaufsstelle $stelle, Berichte $berichte): Response
    {
        $inhalt = $berichte->abrechnungStelle($vorstellung->load('veranstaltung'), $stelle);
        abort_if($inhalt === null, 404);

        return $this->pdf($inhalt, 'abrechnung-'.$vorstellung->id.'-'.$stelle->id.'.pdf');
    }

    public function csv(Vorstellung $vorstellung, string $liste, Berichte $berichte): Response
    {
        return response($berichte->csv($vorstellung->load('veranstaltung'), $liste), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$liste.'-'.$vorstellung->beginn_um->format('Y-m-d').'.csv"',
        ]);
    }

    public function notfallliste(Vorstellung $vorstellung, Berichte $berichte): Response
    {
        return $this->pdf($berichte->notfallliste($vorstellung->load('veranstaltung')), 'notfallliste-'.$vorstellung->id.'.pdf');
    }

    private function pdf(string $inhalt, string $name): Response
    {
        return response($inhalt, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$name.'"']);
    }
}
