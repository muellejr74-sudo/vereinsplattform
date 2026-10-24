{{-- Skripte für sortierbare Listen (Ziehen + ▲▼). Einmal je Seite. --}}
@once
    @push('skripte')
        <script src="{{ asset('js/vendor/Sortable-1.15.7.min.js') }}"></script>
        <script src="{{ asset('js/reihenfolge.js') }}?v=2"></script>
    @endpush
@endonce
