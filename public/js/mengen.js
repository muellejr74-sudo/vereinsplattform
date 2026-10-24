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
        plus.addEventListener('click', function () { aendern(1); });
        // Ein Tipp ins Feld markiert die Zahl, damit man direkt überschreiben kann.
        feld.addEventListener('focus', function () { feld.select(); });
    });
})();
