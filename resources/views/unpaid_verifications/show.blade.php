{{-- Unpaid Verification detail (full page or panel partial) --}}
@php
    $isPanel = $panel ?? false;
    $single = $single ?? null;
@endphp

@if (! $isPanel)
@extends('layouts.app')
@section('title', 'Unpaid Verification Details — 2D MIS')
@section('content')
@include('partials.breadcrumbs', [
    'breadcrumbs' => [
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Unpaid Grantees', 'url' => route('unpaid-verifications.index')],
        ['label' => 'Verification Details'],
    ],
])
@endif

<div class="data-card p-[1.25rem]" data-panel-body>
    <div class="mb-[16px] flex flex-wrap items-center justify-between gap-[12px]">
        @if ($isPanel)
            <h2 class="m-0 text-dense font-heading font-semibold text-ink" data-panel-title>Verification Details</h2>
        @else
            <h1 class="m-0 text-page-title font-heading font-bold text-ink">Verification Details</h1>
        @endif
        <div class="flex flex-wrap items-center gap-[8px]">
            @if (! $isPanel)
                <a href="{{ route('unpaid-verifications.index') }}" class="btn-subtle no-underline">Back</a>
            @else
                <a href="{{ route('unpaid-verifications.show', $single->id) }}" class="btn-subtle no-underline">Open full page</a>
            @endif
            @if (app(\App\Services\AccessControlService::class)->canAccessAction(auth()->user(), 'unpaid_verifications.php', 'delete'))
                <form method="POST" action="{{ route('unpaid-verifications.data') }}"
                    data-confirm="Are you sure you want to delete this record? This cannot be undone.">
                    @csrf
                    <input type="hidden" name="delete_id" value="{{ $single->id }}">
                    <button type="submit" class="btn-red">Delete</button>
                </form>
            @endif
        </div>
    </div>

    <div class="grid items-start gap-[16px] md:grid-cols-[220px_minmax(0,1fr)]" data-panel-avatar>
        <div class="text-center">
            @if ($single->client && $single->client->photo)
                <img src="{{ asset('storage/uploads/client_photos/'.$single->client->photo) }}" alt="Client photo"
                    class="mx-auto max-w-full rounded-panel ring-1 ring-line" style="width:180px;">
            @else
                <div class="mx-auto grid place-items-center rounded-panel ring-1 ring-line text-dense text-ink-muted"
                    style="width:180px;height:200px;">{{ $single->client_name[0] ?? '?' }}</div>
            @endif
            <div class="mt-[8px] font-semibold text-ink">{{ $single->client_name ?? '—' }}</div>
        </div>

        <dl class="m-0 grid grid-cols-1 gap-x-[16px] gap-y-[10px] sm:grid-cols-2 lg:grid-cols-3">
            @foreach([
                'ID' => $single->id ?? '—',
                'Client Name' => $single->client_name ?? '—',
                'Municipality' => $single->municipality_name ?? '—',
                'Is Proxy?' => $single->is_proxy_label ?? '—',
                'Proxy Name' => $single->proxy_fullname ?? '—',
                'Relationship' => $single->proxy_relationship ?? '—',
                'Phone' => $single->proxy_phone ?? '—',
                'Birthdate' => $single->proxy_birthdate ?? '—',
                'Gender' => $single->proxy_gender ?? '—',
                'Occupation' => $single->proxy_occupation ?? '—',
                'Monthly Income' => $single->proxy_monthlyincome ?? '—',
                'Submitted At' => $single->created_at ?? '—',
            ] as $label => $value)
                <div class="min-w-0" data-panel-field>
                    <dt class="ui-micro-label">{{ $label }}</dt>
                    <dd class="m-0 truncate text-dense text-ink" title="{{ $value }}">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
</div>

<div data-panel-title style="display:none;">Unpaid Verification #{{ $single->id }}</div>
<div data-panel-sub style="display:none;">{{ $single->client_name }} &middot; {{ $single->municipality_name }}</div>
<div data-panel-meta style="display:none;">
    <span class="status-badge {{ $single->is_proxy ? 'is-pending' : 'is-paid' }}">{{ $single->is_proxy_label }}</span>
</div>
<div data-panel-actions style="display:none;">
    @if (app(\App\Services\AccessControlService::class)->canAccessAction(auth()->user(), 'unpaid_verifications.php', 'delete'))
        <form method="POST" action="{{ route('unpaid-verifications.data') }}"
            data-confirm="Are you sure you want to delete this record? This cannot be undone.">
            @csrf
            <input type="hidden" name="delete_id" value="{{ $single->id }}">
            <button type="submit" class="btn btn-red">Delete</button>
        </form>
    @endif
    <a href="{{ route('unpaid-verifications.index') }}" class="btn btn-subtle no-underline">Back to List</a>
</div>

@if (! $isPanel)
@endsection
@endif