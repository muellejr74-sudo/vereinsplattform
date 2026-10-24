/*
 * Reihenfolge per Ziehen (SortableJS, MIT) oder mit ▲ ▼ – speichert sofort.
 * Liste: <ul data-sortierbar data-url="…" data-token="…">, Einträge mit data-id,
 * Griff mit class="griff", Knöpfe mit data-schieben="hoch|runter".
 */
(function () {
    'use strict';

    document.querySelectorAll('[data-sortierbar]').forEach(function (liste) {
        var status = document.createElement('p');
        status.className = 'hinweis sortier-status';
        status.setAttribute('role', 'status');
        liste.parentNode.insertBefore(status, liste.nextSibling);

        function eintraege() {
            return Array.prototype.filter.call(liste.children, function (e) { return e.hasAttribute('data-id'); });
        }

        function pfeile() {
            var alle = eintraege();
            alle.forEach(function (e, i) {
                var hoch = e.querySelector('[data-schieben="hoch"]'), runter = e.querySelector('[data-schieben="runter"]');
                if (hoch) { hoch.disabled = i === 0; }
                if (runter) { runter.disabled = i === alle.length - 1; }
            });
        }

        function melden(text, ok) {
            status.textContent = text;
            status.style.color = ok ? '' : 'var(--status-storniert)';
            clearTimeout(melden.zeit);
            if (ok) { melden.zeit = setTimeout(function () { status.textContent = ''; }, 2500); }
        }

        function speichern() {
            pfeile();
            fetch(liste.dataset.url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': liste.dataset.token },
                body: JSON.stringify({ ids: eintraege().map(function (e) { return e.dataset.id; }) })
            }).then(function (antwort) {
                melden(antwort.ok ? 'Reihenfolge gespeichert.' : 'Speichern fehlgeschlagen – bitte die Seite neu laden.', antwort.ok);
            }).catch(function () {
                melden('Keine Verbindung – Reihenfolge nicht gespeichert.', false);
            });
        }

        liste.addEventListener('click', function (ereignis) {
            var knopf = ereignis.target.closest('[data-schieben]');
            if (!knopf) { return; }
            ereignis.preventDefault(); // Knöpfe liegen in <summary>: nicht auf-/zuklappen
            var eintrag = knopf.closest('[data-id]');
            if (knopf.dataset.schieben === 'hoch' && eintrag.previousElementSibling) {
                liste.insertBefore(eintrag, eintrag.previousElementSibling);
            } else if (knopf.dataset.schieben === 'runter' && eintrag.nextElementSibling) {
                liste.insertBefore(eintrag.nextElementSibling, eintrag);
            } else {
                return;
            }
            knopf.focus();
            speichern();
        });

        if (window.Sortable) {
            window.Sortable.create(liste, {
                handle: '.griff',
                draggable: '[data-id]',
                animation: 150,
                forceFallback: true, // eigene Zieh-Logik: am PC und Handy gleich
                delay: 120,
                delayOnTouchOnly: true,
                onEnd: function (ereignis) { if (ereignis.oldIndex !== ereignis.newIndex) { speichern(); } }
            });
        }

        pfeile();
    });
})();
