@extends('errors.rahmen')
@section('titel', 'Kein Zugriff')
@section('text', $exception->getMessage() ?: 'Für diese Seite fehlt Ihnen die Berechtigung.')
