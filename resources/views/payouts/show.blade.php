{{-- Payout Attendance detail (full page or panel partial) --}}
@php
    $isPanel = $panel ?? false;
    $config = config('payout.attendance.'.$variant);
    $single = $single ?? null;
    $showSeats = $config['seat_table'] ?? false;
@endphp

@if (! $isPanel)
@extends('layouts.app')
@section('title', $config['title'].' — 2D MIS')
@section('content')

@endif

<div class="data-card p-[1.25rem]" data-panel-body>
    <div class="mb-[16px] flex flex-wrap items-center justify-between gap-[12px]">
        @if ($isPanel)
            <h2 class="m-0 text-dense font-heading font-semibold text-ink" data-panel-title>{{ $config['title'] }} Details</h2>
        @else
            <h1 class="m-0 text-page-title font-heading font-bold text-ink">{{ $config['title'] }} Details</h1>
        @endif
        <div class="flex flex-wrap items-center gap-[8px]">
            @if (! $isPanel)
                <a href="{{ route('payout-attendance.'.$variant.'.index') }}" class="btn-subtle no-underline">Back</a>
            @else
                <a href="{{ route('payout-attendance.'.$variant.'.show', $single->id) }}" class="btn-subtle no-underline">Open full page</a>
            @endif
            @if (app(\App\Services\AccessControlService::class)->canAccessAction(auth()->user(), $config['page'], 'edit'))
                <button type="button" class="btn-gold" data-edit-payout="{{ $single->id }}">Edit</button>
            @endif
            @if (app(\App\Services\AccessControlService::class)->canAccessAction(auth()->user(), $config['page'], 'delete'))
                <form method="POST" action="{{ route('payout-attendance.'.$variant.'.data') }}"
                    data-confirm="Are you sure you want to delete this scanned payout?">
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
                'Scan ID' => $single->id ?? '—',
                'Transaction ID' => $single->transaction_id ?? '—',
                'Program' => $single->program ?? '—',
                'Client Name' => $single->client_name ?? '—',
                'Municipality' => $single->municipality_name ?? '—',
                'Scanned By' => $single->scanned_by_name ?? '—',
                'Scanned At' => $single->scanned_at ?? '—',
                @if ($showSeats)
                    'Section' => $single->section ?? '—',
                    'Box' => $single->box ?? '—',
                    'Row' => $single->row ?? '—',
                    'Seat' => $single->seat ?? '—',
                @endif
            ] as $label => $value)
                <div class="min-w-0" data-panel-field>
                    <dt class="ui-micro-label">{{ $label }}</dt>
                    <dd class="m-0 truncate text-dense text-ink" title="{{ $value }}">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </div>

    @if (! empty($single->scanned_text))
    <hr class="my-[24px] border-line-light">
    <h3 class="mb-[12px] text-dense font-heading font-semibold text-ink">Scanned Text</h3>
    <pre class="whitespace-pre-wrap bg-[#f8f9fa] p-[12px] rounded-[var(--radius-control)] border border-[var(--color-line)] text-dense text-ink">{{ $single->scanned_text }}</pre>
    @endif
</div>

<div data-panel-title style="display:none;">{{ $config['title'] }} #{{ $single->id }}</div>
<div data-panel-sub style="display:none;">{{ $single->transaction_id }} &middot; {{ $single->program }}</div>
<div data-panel-meta style="display:none;">
    <span class="status-badge {{ $single->status ?? 'is-neutral' }}">{{ $single->status_label ?? $single->status }}</span>
    <span class="program-tag">{{ $single->program }}</span>
</div>
<div data-panel-actions style="display:none;">
    @if (app(\App\Services\AccessControlService::class)->canAccessAction(auth()->user(), $config['page'], 'edit'))
        <button type="button" class="btn btn-gold" data-edit-payout="{{ $single->id }}">Edit</button>
    @endif
    @if (app(\App\Services\AccessControlService::class)->canAccessAction(auth()->user(), $config['page'], 'delete'))
        <form method="POST" action="{{ route('payout-attendance.'.$variant.'.data') }}"
            data-confirm="Are you sure you want to delete this scanned payout?">
            @csrf
            <input type="hidden" name="delete_id" value="{{ $single->id }}">
            <button type="submit" class="btn btn-red">Delete</button>
        </form>
    @endif
    <a href="{{ route('payout-attendance.'.$variant.'.index') }}" class="btn btn-subtle no-underline">Back to List</a>
</div>

@if (! $isPanel)
@endsection
@endif