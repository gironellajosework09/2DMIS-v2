@extends('layouts.app')

@section('title', 'Edit Client — 2D MIS')

@section('content')
    @include('partials.breadcrumbs', [
        'breadcrumbs' => [
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Clients', 'url' => route('clients.index')],
            ['label' => 'Edit Client'],
        ],
    ])

    @include('partials.page-header', ['title' => 'Edit Client'])

    <section class="data-card" aria-label="Edit client form">
        <div class="data-card-body">
            @include('clients._form', [
                'action' => route('clients.update', $client),
                'method' => 'PUT',
                'client' => $client,
                'barangays' => $barangays,
                'affOrgs' => $affOrgs,
            ])
        </div>
    </section>
@endsection
