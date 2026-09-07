{{-- GIP Profile details (full page or panel partial) --}}
@php
    $isPanel = $panel ?? false;
    $client = $gip->client;
@endphp

@if (! $isPanel)
@extends('layouts.app')
@section('title', 'GIP Profile — 2D MIS')
@section('content')
@include('partials.breadcrumbs', [
    'breadcrumbs' => [
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Scholars', 'url' => route('scholars.index')],
        ['label' => 'GIP Profile'],
    ],
])
@endif

<div class="data-card p-[1.25rem]" data-panel-body>
    <div class="mb-[16px] flex flex-wrap items-center justify-between gap-[12px]">
        @if ($isPanel)
            <h2 class="m-0 text-dense font-heading font-semibold text-ink" data-panel-title>GIP Profile</h2>
        @else
            <h1 class="m-0 text-page-title font-heading font-bold text-ink">GIP Profile</h1>
        @endif
        <div class="flex flex-wrap items-center gap-[8px]">
            @if (! $isPanel)
                <a href="{{ route('scholars.index') }}" class="btn-subtle no-underline">Back</a>
            @else
                <a href="{{ route('scholars.gip-show', $gip) }}" class="btn-subtle no-underline">Open full page</a>
            @endif
            <a href="{{ route('scholars.edit', $gip->client_id) }}" class="btn-gold no-underline">Edit Scholar</a>
        </div>
    </div>

    <div class="grid items-start gap-[16px] md:grid-cols-[220px_minmax(0,1fr)]">
        <div class="text-center" data-panel-avatar>
            @if ($client->photo)
                <img src="{{ asset('storage/uploads/client_photos/'.$client->photo) }}" alt="Intern photo"
                    class="mx-auto max-w-full rounded-panel ring-1 ring-line" style="width:180px;">
            @else
                <div class="mx-auto grid place-items-center rounded-panel ring-1 ring-line text-dense text-ink-muted"
                    style="width:180px;height:200px;">No photo</div>
            @endif
            <div class="mt-[8px] font-semibold text-ink">{{ $client->full_name }}</div>
        </div>

        <dl class="m-0 grid grid-cols-1 gap-x-[16px] gap-y-[10px] sm:grid-cols-2 lg:grid-cols-3">
            @foreach([
                'Valid Government ID' => $gip->valid_govt_id,
                'ID Number' => $gip->id_number,
                'Insurance Beneficiary' => $gip->insurance_beneficiary,
                'Emergency Contact' => $gip->emergency_contact,
                'ECP Contact Number' => $gip->ecp_contact_number,
                'ECP Address' => $gip->ecp_address,
                'College' => $gip->college,
                'Course' => $gip->course,
                'Year Graduated' => $gip->year_graduated,
                'High School' => $gip->high_school,
                'Elementary School' => $gip->elementary_school,
                'Latest Work Experience' => $gip->latest_work_experience,
                'Position' => $gip->position,
                'Period of Engagement' => $gip->period_of_engagement,
                'Special Skills' => $gip->special_skills,
                'Achievements' => $gip->achievements,
                'Client ID' => $client->id ?? '—',
                'Municipality' => $client->municipality->name ?? '—',
                'Barangay' => $client->barangayInfo->name ?? '—',
                'Mobile No.' => $client->mobile_no ?? '—',
                'Email' => $client->email ?? '—',
            ] as $label => $value)
                <div class="min-w-0" data-panel-field>
                    <dt class="ui-micro-label">{{ $label }}</dt>
                    <dd class="m-0 truncate text-dense text-ink" title="{{ $value }}">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
</div>

<div data-panel-title style="display:none;">{{ $client->full_name }}</div>
<div data-panel-sub style="display:none;">GIP Profile &middot; Client ID: {{ $client->id ?? '—' }}</div>
<div data-panel-meta style="display:none;">
    <span class="program-tag">{{ $gip->program ?? 'GIP' }}</span>
</div>
<div data-panel-actions style="display:none;">
    <button type="button" class="btn btn-outline" data-sim="Opening scholar profile">Open Scholar Profile</button>
    <a href="{{ route('scholars.edit', $gip->client_id) }}" class="btn btn-gold no-underline">Edit Scholar</a>
</div>

@if (! $isPanel)
@endsection
@endif