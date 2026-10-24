@if ($errors->any())
    <div class="meldung meldung--fehler" role="alert">
        @if ($errors->count() === 1)
            {{ $errors->first() }}
        @else
            <ul>
                @foreach ($errors->all() as $fehler)
                    <li>{{ $fehler }}</li>
                @endforeach
            </ul>
        @endif
    </div>
@endif
