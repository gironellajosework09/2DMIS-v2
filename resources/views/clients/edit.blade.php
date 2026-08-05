@extends('layouts.app')

@section('title', 'Edit Client — 2D MIS')

@section('content')
    <div class="card shadow-lg border-0 p-4">
        <h3 class="mb-3 text-center">Edit Client</h3>
        @include('clients._form', [
            'action' => route('clients.update', $client),
            'method' => 'PUT',
            'client' => $client,
            'barangays' => $barangays,
            'affOrgs' => $affOrgs,
        ])
    </div>
@endsection
