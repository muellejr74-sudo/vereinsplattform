@if ($paginator->hasPages())
    <nav class="seiten" aria-label="Seiten">
        @if ($paginator->onFirstPage())
            <span class="seiten__aus">‹ Zurück</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev">‹ Zurück</a>
        @endif

        <span>Seite {{ $paginator->currentPage() }}@if (method_exists($paginator, 'lastPage')) von {{ $paginator->lastPage() }}@endif</span>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next">Weiter ›</a>
        @else
            <span class="seiten__aus">Weiter ›</span>
        @endif
    </nav>
@endif
