@extends('layouts.app')

@section('title', 'Dashboard — 2D MIS')

@section('content')
    <div class="card shadow-lg border-0 p-4">
        <h3 class="mb-1">Dashboard</h3>
        <p class="text-muted mb-0">Welcome, {{ auth()->user()->username }}.</p>
    </div>
@endsection
