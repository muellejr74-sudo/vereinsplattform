{{-- Scanner-Formular (E119): Kamera, Hand-Scanner oder Eintippen.
     Parameter: $aktion, $json (bool), $liste (CSS-Selektor, optional), $beschriftung, $knopf --}}
@once
    @push('skripte')
        <script src="{{ asset('js/vendor/jsQR-1.4.0.js') }}"></script>
        <script src="{{ asset('js/scanner.js') }}?v=2"></script>
    @endpush
@endonce
<form method="post" action="{{ $aktion }}" class="formular scanner" data-scanner @if ($json ?? false) data-json="1" @endif data-ergebnis="#scan-ergebnis" @isset($liste) data-liste="{{ $liste }}" @endisset>
    @csrf
    <button type="button" class="knopf knopf--zweit" data-scanner-start>Kamera einschalten</button>
    <video hidden muted playsinline></video>
    <label for="code">{{ $beschriftung ?? 'QR-Code scannen oder Ticketnummer eintippen' }}</label>
    <div class="formular--zeile">
        <input id="code" name="code" required autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="z. B. TVM-26-00001" style="flex:1">
        <button type="submit" class="knopf">{{ $knopf ?? 'Prüfen' }}</button>
    </div>
</form>
<div id="scan-ergebnis" class="scan-ergebnis scan-ergebnis--{{ session('scan')['farbe'] ?? 'leer' }}" @if (! session('scan')) hidden @endif aria-live="assertive">
    @if (session('scan'))
        <strong>{{ session('scan')['titel'] }}</strong><span>{{ session('scan')['text'] }}</span>
    @endif
</div>
