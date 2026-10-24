/*
 * Plus-/Minus-Knöpfe für Mengenfelder (input type=number mit data-menge).
 * Ohne JavaScript bleibt das normale Zahlenfeld – nichts geht verloren.
 */
(function () {
    'use strict';

    function knopf(zeichen, beschriftung) {
        var k = document.createElement('button');
        k.type = 'button';
        k.className = 'menge__knopf';
        k.textContent = zeichen;
        k.setAttribute('aria-label', beschriftung);
        return k;
    }

    document.querySelectorAll('input[type=number][data-menge]').forEach(function (feld) {
        var huelle = document.createElement('div');
        huelle.className = 'menge';
        feld.parentNode.insertBefore(huelle, feld);
        var minus = knopf('−', 'eins weniger');
        var plus = knopf('+', 'eins mehr');
        huelle.appendChild(minus);
        huelle.appendChild(feld);
        huelle.appendChild(plus);
        feld.setAttribute('inputmode', 'numeric');

        function aendern(schritt) {
            var min = feld.min !== '' ? parseInt(feld.min, 10) : 0;
            var max = feld.max !== '' ? parseInt(feld.max, 10) : Infinity;
            var wert = (parseInt(feld.value, 10) || 0) + schritt;
            feld.value = Math.max(min, Math.min(max, wert));
            feld.dispatchEvent(new Event('input', { bubbles: true }));
            feld.dispatchEvent(new Event('change', { bubbles: true }));
        }

        minus.addEventListener('click', function () { aendern(-1); });
        feld.mengeKnopfPlus = plus;
        plus.addEventListener('click', function () { aendern(1); });
        // Ein Tipp ins Feld markiert die Zahl, damit man direkt überschreiben kann.
        feld.addEventListener('focus', function () { feld.select(); });
    });

    /*
     * Sofort-Prüfung gegen die freien Plätze: Formular mit data-frei="10" oder
     * data-frei-aus="#auswahl" (Zahl steht an der gewählten Option). Der Server prüft
     * beim Absenden trotzdem noch einmal – das hier ist nur die schnelle Rückmeldung.
     */
    document.querySelectorAll('form[data-frei], form[data-frei-aus]').forEach(function (form) {
        var hinweis = document.createElement('div');
        hinweis.className = 'meldung meldung--fehler';
        hinweis.setAttribute('role', 'alert');
        hinweis.hidden = true;
        var felder = form.querySelectorAll('input[data-menge]');
        if (!felder.length) { return; }
        var bloecke = form.querySelectorAll('[data-veranstaltung]:not(option)');
        var letztes = bloecke.length ? bloecke[bloecke.length - 1] : (felder[felder.length - 1].closest('.mengen-zeile') || felder[felder.length - 1]);
        letztes.parentNode.insertBefore(hinweis, letztes.nextSibling);

        function frei() {
            if (form.dataset.frei !== undefined) { return parseInt(form.dataset.frei, 10); }
            var auswahl = document.querySelector(form.dataset.freiAus);
            var option = auswahl && auswahl.selectedOptions[0];
            return option && option.dataset.frei !== undefined ? parseInt(option.dataset.frei, 10) : null;
        }

        function pruefen() {
            var grenze = frei();
            var summe = 0;
            felder.forEach(function (f) { if (!f.disabled && !f.closest('[hidden]')) { summe += parseInt(f.value, 10) || 0; } });
            var zuViel = grenze !== null && summe > grenze;
            hinweis.hidden = !zuViel;
            if (zuViel) {
                hinweis.textContent = summe + ' Karten gewählt, aber nur noch ' + grenze + ' frei – bitte ' + (summe - grenze) + ' weniger.';
            }
            form.querySelectorAll('button[type=submit]').forEach(function (k) {
                // Nur selbst gesetzte Sperren wieder lösen (andere Sperren, z. B. „keine Kategorien“, bleiben).
                if (zuViel) { k.disabled = true; k.dataset.zuViel = '1'; } else if (k.dataset.zuViel) { k.disabled = false; delete k.dataset.zuViel; }
            });
            felder.forEach(function (f) {
                if (f.mengeKnopfPlus) { f.mengeKnopfPlus.disabled = grenze !== null && summe >= grenze && !f.closest('[hidden]'); }
            });
        }

        form.addEventListener('input', pruefen);
        form.addEventListener('change', pruefen);
        pruefen();
    });
})();
