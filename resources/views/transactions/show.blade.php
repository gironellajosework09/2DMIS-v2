@extends('layouts.app')

@section('title', 'View Transaction — 2D MIS')

@php
    $isPanel = $panel ?? false;
    $fmt = fn ($d) => in_array((string) $d, ['', '0000-00-00', '0000-00-00 00:00:00', null], true) ? '' : date('m/d/Y', strtotime($d));
    $statusClass = match (true) {
        $transaction->status === 'PAID' => 'is-paid',
        str_starts_with((string) $transaction->status, 'PENDING') => 'is-pending',
        default => 'is-neutral',
    };
@endphp

@if (! $isPanel)
@section('content')

@endif

<div class="data-card p-[1.25rem]" data-panel-body>
    <div class="mb-[16px] flex flex-wrap items-center justify-between gap-[12px]">
        @if ($isPanel)
            <h2 class="m-0 text-dense font-heading font-semibold text-ink" data-panel-title>Transaction Details</h2>
        @else
            <h1 class="m-0 text-page-title font-heading font-bold text-ink">Transaction Details</h1>
        @endif
        <span class="status-badge {{ $statusClass }}">{{ $transaction->status }}</span>
        <div class="flex flex-wrap items-center gap-[8px]">
            @if (! $isPanel)
                <a href="{{ route('transactions.index') }}" class="btn-subtle no-underline">Back to All Transactions</a>
            @else
                <a href="{{ route('transactions.show', $transaction) }}" class="btn-subtle no-underline">Open full page</a>
            @endif
            @if (app(\App\Services\AccessControlService::class)->canAccessAction(auth()->user(), 'all_transactions.php', 'edit'))
                <a href="{{ route('transactions.edit', $transaction->id) }}" class="btn-gold no-underline">Edit</a>
            @endif
            @if (app(\App\Services\AccessControlService::class)->canAccessAction(auth()->user(), 'all_transactions.php', 'delete'))
                <form method="POST" action="{{ route('transactions.destroy', $transaction->id) }}"
                    data-confirm="Are you sure you want to delete this transaction? This cannot be undone.">
                    @csrf
                    <button type="submit" class="btn-red">Delete</button>
                </form>
            @endif
        </div>
    </div>

    <dl class="m-0 grid grid-cols-1 gap-x-[16px] gap-y-[10px] sm:grid-cols-2 lg:grid-cols-3">
        <div class="min-w-0">
            <dt class="ui-micro-label">Client</dt>
            <dd class="m-0 text-dense text-ink">
                @if ($transaction->client)
                    <a href="{{ route('clients.show', $transaction->client) }}" class="font-medium text-navy no-underline hover:text-navy-hover hover:underline">{{ $transaction->client->displayFullName() }}</a>
                @else
                    —
                @endif
            </dd>
        </div>
        <div class="min-w-0">
            <dt class="ui-micro-label">Program</dt>
            <dd class="m-0 whitespace-pre-line text-dense text-ink">{{ $transaction->program }}</dd>
        </div>
        <div class="min-w-0">
            <dt class="ui-micro-label">Assistance Type</dt>
            <dd class="m-0 whitespace-pre-line text-dense text-ink">{{ $transaction->type }}</dd>
        </div>
        <div class="min-w-0">
            <dt class="ui-micro-label">Patient</dt>
            <dd class="m-0 whitespace-pre-line text-dense text-ink">{{ $transaction->patient_name }}</dd>
        </div>
        <div class="min-w-0">
            <dt class="ui-micro-label">Date Applied</dt>
            <dd class="m-0 tabular-nums text-dense text-ink">{{ $fmt($transaction->date_applied) }}</dd>
        </div>
        <div class="min-w-0">
            <dt class="ui-micro-label">Remarks</dt>
            <dd class="m-0 whitespace-pre-line text-dense text-ink">{{ $transaction->remarks }}</dd>
        </div>
        <div class="min-w-0">
            <dt class="ui-micro-label">Comments</dt>
            <dd class="m-0 whitespace-pre-line text-dense text-ink">{{ $transaction->comments }}</dd>
        </div>
        <div class="min-w-0">
            <dt class="ui-micro-label">Suggested Amount</dt>
            <dd class="m-0 tabular-nums text-dense text-ink">₱{{ number_format($transaction->suggested_amount ?? 0, 2) }}</dd>
        </div>
        <div class="min-w-0">
            <dt class="ui-micro-label">Amount Paid</dt>
            <dd class="m-0 tabular-nums text-dense text-ink">₱{{ number_format($transaction->amount_paid ?? 0, 2) }}</dd>
        </div>
        <div class="min-w-0">
            <dt class="ui-micro-label">Pay Out Date</dt>
            <dd class="m-0 tabular-nums text-dense text-ink">{{ $fmt($transaction->payout_date) }}</dd>
        </div>
        <div class="min-w-0">
            <dt class="ui-micro-label">Date Paid</dt>
            <dd class="m-0 tabular-nums text-dense text-ink">{{ $fmt($transaction->date_paid) }}</dd>
        </div>
        <div class="min-w-0">
            <dt class="ui-micro-label">GWA</dt>
            <dd class="m-0 tabular-nums text-dense text-ink">{{ $transaction->gwa }}</dd>
        </div>
        <div class="min-w-0">
            <dt class="ui-micro-label">Units</dt>
            <dd class="m-0 tabular-nums text-dense text-ink">{{ $transaction->units }}</dd>
        </div>
    </dl>

    <hr class="my-[24px] border-line-light">

    <div class="flex flex-wrap items-center justify-between gap-[12px]">
        @if (! $isPanel)
            <a href="{{ route('transactions.index') }}" class="btn-subtle no-underline">Back to All Transactions</a>
        @else
            <a href="{{ route('transactions.index') }}" class="btn-subtle no-underline">Back to All Transactions</a>
        @endif
        <div class="flex gap-[8px]">
            @if (app(\App\Services\AccessControlService::class)->canAccessAction(auth()->user(), 'all_transactions.php', 'edit'))
                <a href="{{ route('transactions.edit', $transaction->id) }}" class="btn-gold no-underline">Edit</a>
            @endif
            @if (app(\App\Services\AccessControlService::class)->canAccessAction(auth()->user(), 'all_transactions.php', 'delete'))
                <form method="POST" action="{{ route('transactions.destroy', $transaction->id) }}"
                    data-confirm="Are you sure you want to delete this transaction? This cannot be undone.">
                    @csrf
                    <button type="submit" class="btn-red">Delete</button>
                </form>
            @endif
        </div>
    </div>
</div>

<div data-panel-title style="display:none;">Transaction #{{ $transaction->id }} &middot; {{ $transaction->program }}</div>
<div data-panel-sub style="display:none;">{{ $transaction->client?->displayFullName() ?? '—' }}</div>
<div data-panel-meta style="display:none;">
    <span class="status-badge {{ $statusClass }}">{{ $transaction->status }}</span>
    <span class="program-tag">{{ $transaction->program }}</span>
</div>
<div data-panel-actions style="display:none;">
    @if (app(\App\Services\AccessControlService::class)->canAccessAction(auth()->user(), 'all_transactions.php', 'edit'))
        <a href="{{ route('transactions.edit', $transaction->id) }}" class="btn btn-gold no-underline">Edit</a>
    @endif
    @if (app(\App\Services\AccessControlService::class)->canAccessAction(auth()->user(), 'all_transactions.php', 'delete'))
        <form method="POST" action="{{ route('transactions.destroy', $transaction->id) }}"
            data-confirm="Are you sure you want to delete this transaction? This cannot be undone.">
            @csrf
            <button type="submit" class="btn btn-red">Delete</button>
        </form>
    @endif
    <a href="{{ route('transactions.index') }}" class="btn btn-subtle no-underline">Back to List</a>
</div>

@if (! $isPanel)
@endsection
@endif

@include('partials.confirm-modal')