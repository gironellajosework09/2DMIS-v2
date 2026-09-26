{{-- Audit Log detail (full page or panel partial) --}}
@php
    $isPanel = $panel ?? false;
    $log = $log ?? null;
    $tables = $tables ?? [];
    $targetTable = $targetTable ?? 'tbl_clients';
@endphp

@if (! $isPanel)
@extends('layouts.app')
@section('title', 'Audit Log Details — 2D MIS')
@section('content')

@endif

<div class="data-card p-[1.25rem]" data-panel-body>
    <div class="mb-[16px] flex flex-wrap items-center justify-between gap-[12px]">
        @if ($isPanel)
            <h2 class="m-0 text-dense font-heading font-semibold text-ink" data-panel-title>Audit Entry #{{ $log->id }}</h2>
        @else
            <h1 class="m-0 text-page-title font-heading font-bold text-ink">Audit Entry #{{ $log->id }}</h1>
        @endif
        <div class="flex flex-wrap items-center gap-[8px]">
            @if (! $isPanel)
                <a href="{{ route('admin.audit-logs.index') }}" class="btn-subtle no-underline">Back</a>
            @else
                <a href="{{ route('admin.audit-logs.show', ['id' => $log->id, 'table' => $targetTable]) }}" class="btn-subtle no-underline">Open full page</a>
            @endif
        </div>
    </div>

    <dl class="m-0 grid grid-cols-1 gap-x-[16px] gap-y-[10px] sm:grid-cols-2 lg:grid-cols-3">
        @foreach([
            'Timestamp' => $log->created_at ? \Carbon\Carbon::parse($log->created_at, 'UTC')->setTimezone('Asia/Manila')->format('m/d/Y - h:i A') : '—',
            'Severity' => $log->type ?? '—',
            'Actor' => ($log->username ?? '—') . ' (@' . ($log->user->username ?? '—') . ')',
            'Action Code' => $log->action ?? '—',
            'Module' => $log->target_table ?? '—',
            'Affected Record' => $log->target_name ?? $log->target_id ?? '—',
        ] as $label => $value)
            <div class="min-w-0" data-panel-field>
                <dt class="ui-micro-label">{{ $label }}</dt>
                <dd class="m-0 truncate text-dense text-ink" title="{{ $value }}">
                    @if($label === 'Severity')
                        <span class="status-badge {{ match(true) {
                            $value === 'success' => 'is-paid',
                            $value === 'info' => 'is-approved',
                            $value === 'warning' => 'is-pending',
                            $value === 'danger' => 'is-neutral',
                            default => 'is-neutral',
                        } }}">
                            {{ $value }}
                        </span>
                    @else
                        {{ $value }}
                    @endif
                </dd>
            </div>
        @endforeach
    </dl>

    <hr class="my-[24px] border-line-light">
    <h3 class="mb-[12px] text-dense font-heading font-semibold text-ink">Description</h3>
    <div class="details-note">{{ $log->description }}</div>
</div>

<div data-panel-title style="display:none;">Audit Entry #{{ $log->id }}</div>
<div data-panel-sub style="display:none;">{{ $log->target_table }} - {{ $log->action }}</div>
<div data-panel-meta style="display:none;">
    <span class="status-badge {{ match(true) {
        $log->type === 'success' => 'is-paid',
        $log->type === 'info' => 'is-approved',
        $log->type === 'warning' => 'is-pending',
        $log->type === 'danger' => 'is-neutral',
        default => 'is-neutral',
    } }}">{{ $log->type }}</span>
    <span class="program-tag">{{ $log->action }}</span>
</div>
<div data-panel-actions style="display:none;">
    <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-subtle no-underline">Back to Audit Logs</a>
</div>

@if (! $isPanel)
@endsection
@endif