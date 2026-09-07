@extends('layouts.app')

@section('title', 'Add Client — 2D MIS')

@section('content')
    @include('partials.breadcrumbs', [
        'breadcrumbs' => [
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Clients', 'url' => route('clients.index')],
            ['label' => 'Add Client'],
        ],
    ])

    @include('partials.page-header', ['title' => 'Add New Client'])

    <section class="data-card" aria-label="New client form">
        <div class="data-card-body">
            @include('clients._form', [
                'action' => route('clients.store'),
                'method' => 'POST',
            ])
        </div>
    </section>
@endsection
