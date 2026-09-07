@extends('layouts.app')

@section('title', 'Client Profile — 2D MIS')

@section('content')
    {{-- Full-page mode only; the ?panel=1 variant renders this same
         partial inside the index offcanvas with no wrapper chrome. --}}
    @include('partials.breadcrumbs', [
        'breadcrumbs' => [
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Clients', 'url' => route('clients.index')],
            ['label' => 'Client Profile'],
        ],
    ])

    @include('clients._details', ['panel' => false, 'gip' => $gip, 'hasGipTransaction' => $hasGipTransaction])

    {{-- Full-page delete goes through the shared uiConfirm dialog (Batch F),
         matching the other modules' show pages. --}}
    @include('partials.confirm-modal')
@endsection
