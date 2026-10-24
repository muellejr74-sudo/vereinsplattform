@extends('layouts.basis')

@section('titel', 'Kassieren')

@section('inhalt')
    <section class="karte karte--schmal">
        <h1>Kassieren</h1>
        <p class="hinweis">{{ $text }}</p>
        <p class="kasse-betrag">Zu zahlen <strong>{{ \App\Modules\Ticket\Dienste\Geld::anzeigen($betrag) }}</strong></p>
        @include('ticket::teile.rueckgeld', ['betrag' => $betrag])
        <a class="knopf knopf--gross" href="{{ route('tickets.abendkasse.show', $v) }}" style="display:block;text-align:center;margin-top:1rem">Abschließen – zurück zum Menü</a>
        <p class="hinweis">Das Rückgeld wird nur angezeigt, nicht gespeichert.</p>
    </section>
@endsection
