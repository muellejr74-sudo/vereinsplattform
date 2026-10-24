<details class="hinweis" style="margin:1rem 0">
    <summary>Druckeinstellungen (Aufkleberbogen A4)</summary>
    <div class="formular formular--zeile" style="margin-top:0.5rem">
        <div class="feld"><label for="spalten">Spalten</label><input id="spalten" name="spalten" type="number" min="1" max="6" value="{{ $layout['spalten'] }}"></div>
        <div class="feld"><label for="zeilen">Zeilen</label><input id="zeilen" name="zeilen" type="number" min="1" max="12" value="{{ $layout['zeilen'] }}"></div>
        <div class="feld"><label for="rand_oben">Rand oben/unten (mm)</label><input id="rand_oben" name="rand_oben" type="number" step="0.5" min="0" max="50" value="{{ $layout['rand_oben'] }}"></div>
        <div class="feld"><label for="rand_links">Rand links/rechts (mm)</label><input id="rand_links" name="rand_links" type="number" step="0.5" min="0" max="50" value="{{ $layout['rand_links'] }}"></div>
        <div class="feld"><label for="abstand_waagerecht">Abstand zwischen Spalten (mm)</label><input id="abstand_waagerecht" name="abstand_waagerecht" type="number" step="0.5" min="0" max="30" value="{{ $layout['abstand_waagerecht'] }}"></div>
        <div class="feld"><label for="abstand_senkrecht">Abstand zwischen Zeilen (mm)</label><input id="abstand_senkrecht" name="abstand_senkrecht" type="number" step="0.5" min="0" max="30" value="{{ $layout['abstand_senkrecht'] }}"></div>
        <div class="feld"><label for="start">Beginnen bei Aufkleber Nr.</label><input id="start" name="start" type="number" min="1" max="72" value="{{ $layout['start'] }}"></div>
        <label class="auswahl" style="font-weight:normal"><input type="checkbox" name="rahmen" value="1" @checked($layout['rahmen'])> dünne Schnittlinien (für normales Papier)</label>
    </div>
    <p>Voreinstellung: 3 × 7 Aufkleber je Bogen. „Beginnen bei“ überspringt bereits benutzte Aufkleber eines angebrochenen Bogens.</p>
</details>
