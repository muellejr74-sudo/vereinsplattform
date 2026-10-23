@extends('layouts.basis')

@section('titel', config('app.name'))

@section('inhalt')
    <section class="karte">
        <h1>Willkommen bei der {{ config('app.name') }}</h1>
        <p>Die Plattform befindet sich im Aufbau.</p>
        <p class="hinweis">Stufe 0 – Grundgerüst</p>
    </section>
@endsection
