<?php

namespace App\Modules\Kern\Dienste;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Erzeugt QR-Codes als SVG-Grafik (ohne Internetdienst, alles auf dem Server).
 * Wird ab Stufe 2/3 auch für Tisch- und Ticket-Codes genutzt.
 */
class QrCode
{
    public static function svg(string $inhalt, int $groesse = 220): string
    {
        $schreiber = new Writer(new ImageRenderer(new RendererStyle($groesse, 2), new SvgImageBackEnd));

        // XML-Kopfzeile entfernen, damit das SVG direkt in die Seite eingebettet werden kann.
        return preg_replace('/^<\?xml[^>]*\?>\s*/', '', $schreiber->writeString($inhalt));
    }
}
