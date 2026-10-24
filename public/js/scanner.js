/*
 * Scanner für Einlass, Stornieren und Abendkasse (E119).
 * - Kamera: erkennt QR-Codes Code für Code ohne Seitenwechsel (jsQR, Apache-2.0).
 * - Hand-Scanner (USB/Bluetooth) oder Tastatur: tippt in das Feld „code“ und drückt Enter.
 * Ein Formular mit data-scanner wird erkannt. Mit data-json="1" wird per Hintergrund-Anfrage
 * gesendet und das Ergebnis farbig angezeigt; sonst wird das Formular normal abgeschickt.
 */
(function () {
    'use strict';

    var audio = null;

    function ton(farbe) {
        try {
            if (!audio) { return; }
            var osc = audio.createOscillator();
            var laut = audio.createGain();
            osc.frequency.value = farbe === 'gruen' ? 1046 : (farbe === 'gelb' ? 660 : 220);
            laut.gain.value = 0.15;
            osc.connect(laut);
            laut.connect(audio.destination);
            osc.start();
            osc.stop(audio.currentTime + (farbe === 'gruen' ? 0.12 : 0.35));
        } catch (e) { /* ohne Ton weiter */ }
        if (farbe !== 'gruen' && navigator.vibrate) { navigator.vibrate([120, 60, 120]); }
    }

    function anzeigen(feld, daten) {
        if (!feld) { return; }
        feld.className = 'scan-ergebnis scan-ergebnis--' + (daten.farbe || 'rot');
        feld.innerHTML = '';
        var titel = document.createElement('strong');
        titel.textContent = daten.titel || '';
        var text = document.createElement('span');
        text.textContent = daten.text || '';
        feld.appendChild(titel);
        feld.appendChild(text);
        feld.hidden = false;
    }

    function einrichten(form) {
        var eingabe = form.querySelector('input[name=code]');
        var knopf = form.querySelector('[data-scanner-start]');
        var video = form.querySelector('video');
        var ergebnis = document.querySelector(form.getAttribute('data-ergebnis') || '#scan-ergebnis');
        var liste = form.getAttribute('data-liste') ? document.querySelector(form.getAttribute('data-liste')) : null;
        var json = form.getAttribute('data-json') === '1';
        var leinwand = document.createElement('canvas');
        var zeichner = leinwand.getContext('2d', { willReadFrequently: true });
        var strom = null;
        var laeuft = false;
        var beschaeftigt = false;
        var letzter = '';
        var zuletztGesehen = 0;

        function senden(code) {
            if (!json) {
                eingabe.value = code;
                form.submit();
                return;
            }
            beschaeftigt = true;
            var daten = new FormData(form);
            daten.set('code', code);
            fetch(form.action, {
                method: 'POST',
                body: daten,
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (antwort) {
                if (antwort.status === 419) {
                    return { farbe: 'rot', titel: 'Sitzung abgelaufen', text: 'Bitte die Seite neu laden.' };
                }
                return antwort.json();
            }).then(function (d) {
                anzeigen(ergebnis, d);
                ton(d.farbe);
                if (liste && d.html !== undefined) { liste.innerHTML = d.html; }
                if (d.zaehler) {
                    document.querySelectorAll('[data-zaehler]').forEach(function (el) {
                        var wert = d.zaehler[el.getAttribute('data-zaehler')];
                        if (wert !== undefined) { el.textContent = wert; }
                    });
                }
            }).catch(function () {
                anzeigen(ergebnis, { farbe: 'rot', titel: 'Keine Verbindung', text: 'Bitte noch einmal scannen.' });
                ton('rot');
            }).then(function () {
                beschaeftigt = false;
                eingabe.value = '';
                if (!laeuft) { eingabe.focus(); }
            });
        }

        function schleife() {
            if (!laeuft) { return; }
            if (!beschaeftigt && video.readyState >= 2 && video.videoWidth > 0) {
                var faktor = Math.min(1, 720 / video.videoWidth);
                leinwand.width = Math.round(video.videoWidth * faktor);
                leinwand.height = Math.round(video.videoHeight * faktor);
                zeichner.drawImage(video, 0, 0, leinwand.width, leinwand.height);
                var bild = zeichner.getImageData(0, 0, leinwand.width, leinwand.height);
                var treffer = window.jsQR ? window.jsQR(bild.data, bild.width, bild.height, { inversionAttempts: 'dontInvert' }) : null;
                if (treffer && treffer.data) {
                    var jetzt = Date.now();
                    // Derselbe Code wird nur erneut gesendet, wenn er zwischendurch mindestens
                    // 2 Sekunden nicht vor der Kamera war – sonst würde eine länger gehaltene
                    // Karte fälschlich als „schon eingelassen“ erscheinen.
                    if (treffer.data !== letzter || jetzt - zuletztGesehen > 2000) {
                        letzter = treffer.data;
                        senden(treffer.data);
                    }
                    zuletztGesehen = jetzt;
                }
            }
            setTimeout(schleife, 150);
        }

        function starten() {
            try { audio = audio || new (window.AudioContext || window.webkitAudioContext)(); } catch (e) { audio = null; }
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                anzeigen(ergebnis, { farbe: 'rot', titel: 'Keine Kamera', text: 'Dieser Browser erlaubt keinen Kamerazugriff. Bitte Code eintippen oder Hand-Scanner nutzen.' });
                return;
            }
            navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false }).then(function (s) {
                strom = s;
                video.srcObject = s;
                video.setAttribute('playsinline', '');
                video.muted = true;
                video.hidden = false;
                video.play();
                laeuft = true;
                knopf.textContent = 'Kamera ausschalten';
                schleife();
            }).catch(function () {
                anzeigen(ergebnis, { farbe: 'rot', titel: 'Kamera nicht freigegeben', text: 'Bitte den Kamerazugriff erlauben – oder den Code eintippen.' });
            });
        }

        function stoppen() {
            laeuft = false;
            if (strom) { strom.getTracks().forEach(function (t) { t.stop(); }); }
            strom = null;
            video.hidden = true;
            knopf.textContent = 'Kamera einschalten';
        }

        if (knopf && video) {
            knopf.addEventListener('click', function () { if (laeuft) { stoppen(); } else { starten(); } });
        }

        form.addEventListener('submit', function (e) {
            if (!json) { return; }
            e.preventDefault();
            var code = eingabe.value.trim();
            if (code) { senden(code); }
        });
    }

    document.querySelectorAll('form[data-scanner]').forEach(einrichten);
})();
