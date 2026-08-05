@extends('layouts.app')

@section('title', 'Add Client — 2D MIS')

@section('content')
    <div class="card shadow-lg border-0 p-4">
        <h3 class="mb-3 text-center">Add New Client</h3>
        @include('clients._form', [
            'action' => route('clients.store'),
            'method' => 'POST',
        ])
    </div>
@endsection
