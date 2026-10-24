@extends('layouts.basis')

@section('titel', 'Zwei-Faktor-Anmeldung einrichten')

@section('inhalt')
    <section class="karte karte--schmal">
        <h1>Zwei-Faktor-Anmeldung einrichten</h1>
        <p>Als Super-Administrator schützen Sie Ihr Konto zusätzlich mit einem Code aus einer App auf Ihrem Handy.</p>

        <ol class="schritte">
            <li>Installieren Sie auf Ihrem Handy eine Authenticator-App, z. B. <strong>Google Authenticator</strong> oder <strong>Microsoft Authenticator</strong>.</li>
            <li>Tippen Sie in der App auf „+“ bzw. „Konto hinzufügen“ und scannen Sie diesen QR-Code:
                <div class="qr">{!! $qrCode !!}</div>
                <details>
                    <summary>QR-Code lässt sich nicht scannen?</summary>
                    <p>Wählen Sie in der App „Einrichtungsschlüssel eingeben“ und tippen Sie diesen Schlüssel ab (zeitbasiert):</p>
                    <p><code class="schluessel">{{ $geheimnisLesbar }}</code></p>
                </details>
            </li>
            <li>Geben Sie zur Bestätigung den 6-stelligen Code ein, den die App jetzt anzeigt:</li>
        </ol>

        @include('kern::teile.fehler')
        <form method="post" action="{{ route('zweifaktor.einrichten') }}" class="formular">
            @csrf
            <label for="code">Code aus der App</label>
            <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="10" required autofocus class="eingabe--code">
            <button type="submit" class="knopf">Einrichtung abschließen</button>
        </form>
        <p class="hinweis">Danach erhalten Sie einmalig Ihre Notfall-Codes für den Fall, dass Sie Ihr Handy verlieren.</p>
    </section>
@endsection
