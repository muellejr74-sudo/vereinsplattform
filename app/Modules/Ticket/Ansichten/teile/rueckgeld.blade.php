{{-- Rückgeld-Rechner: nur Anzeige, nichts wird gespeichert. Parameter: $betrag (Cent) --}}
            <div class="formular" id="kassieren" data-betrag="{{ $betrag }}">
                <label for="gegeben">Gegeben (in €)</label>
                <input id="gegeben" type="text" inputmode="decimal" autocomplete="off" placeholder="0,00" style="font-size:1.4rem;text-align:right">
                <div class="knopfzeile knopfzeile--voll" style="margin-top:0.5rem">
                    <button type="button" class="knopf knopf--zweit" data-geld="{{ $betrag }}">passend</button>
                    @foreach ([1000, 2000, 5000, 10000] as $schein)
                        @if ($schein > $betrag)<button type="button" class="knopf knopf--zweit" data-geld="{{ $schein }}">{{ $schein / 100 }} €</button>@endif
                    @endforeach
                </div>
                <p class="kasse-betrag" id="rueckgeld-zeile" hidden>Rückgeld <strong id="rueckgeld">0,00 €</strong></p>
                <p class="meldung meldung--fehler" id="zu-wenig" hidden></p>
            </div>

            <script>
                (function () {
                    var feld = document.getElementById('gegeben'), betrag = parseInt(document.getElementById('kassieren').dataset.betrag, 10);
                    var zeile = document.getElementById('rueckgeld-zeile'), wert = document.getElementById('rueckgeld'), fehlt = document.getElementById('zu-wenig');
                    function euro(c) { return (c / 100).toFixed(2).replace('.', ',') + ' €'; }
                    function rechnen() {
                        var text = feld.value.replace(/\s/g, '').replace(',', '.');
                        if (text === '') { zeile.hidden = true; fehlt.hidden = true; return; }
                        var gegeben = Math.round(parseFloat(text) * 100);
                        if (isNaN(gegeben)) { zeile.hidden = true; return; }
                        if (gegeben < betrag) { zeile.hidden = true; fehlt.hidden = false; fehlt.textContent = 'Es fehlen noch ' + euro(betrag - gegeben) + '.'; return; }
                        fehlt.hidden = true; zeile.hidden = false; wert.textContent = euro(gegeben - betrag);
                    }
                    feld.addEventListener('input', rechnen);
                    document.querySelectorAll('[data-geld]').forEach(function (k) {
                        k.addEventListener('click', function () { feld.value = (parseInt(k.dataset.geld, 10) / 100).toFixed(2).replace('.', ','); rechnen(); });
                    });
                })();
            </script>
