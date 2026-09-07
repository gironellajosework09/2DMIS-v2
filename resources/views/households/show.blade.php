@extends('layouts.app')

@section('title', 'View Household — 2D MIS')

 {{-- Batch G migration (UI_UX_ANALYSIS §8.9 Group 3 remainder): household
      detail. Static server-rendered read-only view; routes and member
      links unchanged. --}}
@push('styles')
    <style>
        /* ── Household-show scope. Prefixed with #household-show-screen —
           nothing here can leak to other screens. ── */
        #household-show-screen .members-table th {
            font-size: 0.85rem;
            background-color: var(--color-navy);
            color: #fff;
            border-bottom: 0;
            white-space: nowrap;
        }

        #household-show-screen .members-table td {
            font-size: 0.875rem;
        }

        #household-show-screen .members-table tbody tr:nth-child(odd) td {
            background-color: rgb(15 27 45 / 0.02);
        }

        #household-show-screen .members-table tbody tr:hover td {
            background-color: rgb(37 99 235 / 0.06);
        }

        #household-show-screen .members-table th,
        #household-show-screen .members-table td {
            border: 1px solid var(--color-line-light);
        }
    </style>
@endpush

@php
    $isPanel = $panel ?? false;
@endphp

@if (! $isPanel)
@section('content')
@include('partials.breadcrumbs', [
    'breadcrumbs' => [
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Households', 'url' => route('households.index')],
        ['label' => $household->headClient->full_name ?? $household->household_id],
    ],
])
@include('partials.page-header', [
    'title' => 'Household Details',
    'subtitle' => $household->headClient->full_name ?? '',
    'actions' => '
        <a href="'.route('households.index').'" class="btn-subtle no-underline">Back</a>',
])
@endsection
@endif

<div id="household-show-screen" class="flex flex-col gap-[16px]" data-panel-body>
    <div class="mb-[16px] flex flex-wrap items-center justify-between gap-[12px]" data-panel-header>
        @if ($isPanel)
            <h2 class="m-0 text-dense font-heading font-semibold text-ink" data-panel-title>Household Details</h2>
        @else
            <h1 class="m-0 text-page-title font-heading font-bold text-ink">Household Details</h1>
        @endif
        <div class="flex flex-wrap items-center gap-[8px]">
            @if (! $isPanel)
                <a href="{{ route('households.index') }}" class="btn-subtle no-underline">Back</a>
            @else
                <a href="{{ route('households.show', $household) }}" class="btn-subtle no-underline">Open full page</a>
            @endif
            @if (app(\App\Services\AccessControlService::class)->canAccessPage(auth()->user(), 'household.php'))
                <a href="{{ route('households.edit', $household) }}" class="btn-gold no-underline">Edit</a>
            @endif
        </div>
    </div>

    <div class="grid items-start gap-[16px] md:grid-cols-[220px_minmax(0,1fr)]" data-panel-avatar>
        <div class="text-center">
            @if ($household->headClient->photo)
                <img src="{{ asset('storage/uploads/client_photos/'.$household->headClient->photo) }}" alt="Head of Household photo"
                    class="mx-auto max-w-full rounded-panel ring-1 ring-line" style="width:180px;">
            @else
                <div class="mx-auto grid place-items-center rounded-panel ring-1 ring-line text-dense text-ink-muted"
                    style="width:180px;height:200px;">No photo</div>
            @endif
            <div class="mt-[8px] font-semibold text-ink">{{ $household->headClient->full_name ?? '' }}</div>
        </div>

        <dl class="m-0 grid grid-cols-1 gap-x-[16px] gap-y-[10px] sm:grid-cols-2 lg:grid-cols-3">
            @foreach([
                'Household ID' => $household->household_id,
                'Head of Household' => $household->headClient->full_name ?? '—',
                'Municipality' => $household->headClient->municipality->name ?? '—',
                'Barangay' => $household->headClient->barangayInfo->name ?? '—',
                'Total Members' => count($members) . ' member' . (count($members) != 1 ? 's' : ''),
            ] as $label => $value)
                <div class="min-w-0" data-panel-field>
                    <dt class="ui-micro-label">{{ $label }}</dt>
                    <dd class="m-0 truncate text-dense text-ink" title="{{ $value }}">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </div>

    <hr class="my-[24px] border-line-light">
    <h3 class="mb-[12px] text-dense font-heading font-semibold text-ink">Household Members</h3>
    <div class="overflow-x-auto">
        <table class="table members-table align-middle mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th style="width:80px;">Age</th>
                    <th style="width:120px;">Sex</th>
                    <th style="width:90px;">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($members as $member)
                    <tr>
                        <td>
                            <strong>{{ $member->full_name }}</strong>
                            @if ($member->id == $household->head_household)
                                <span class="status-badge is-success ms-2">Head of Household</span>
                            @endif
                        </td>
                        <td>{{ $member->age }}</td>
                        <td>{{ $member->sex }}</td>
                        <td><a href="{{ route('clients.show', $member->id) }}" class="btn-subtle no-underline">View</a></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-dense text-ink-muted">No household members found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div data-panel-title style="display:none;">{{ $household->headClient->full_name ?? $household->household_id }}</div>
<div data-panel-sub style="display:none;">{{ $household->household_id }}</div>
<div data-panel-meta style="display:none;">
    <span class="status-badge is-info">{{ count($members) }} Member{{ count($members) != 1 ? 's' : '' }}</span>
</div>
<div data-panel-actions style="display:none;">
    @if (app(\App\Services\AccessControlService::class)->canAccessPage(auth()->user(), 'household.php'))
        <a href="{{ route('households.edit', $household) }}" class="btn btn-gold no-underline">Edit</a>
    @endif
    <a href="{{ route('households.index') }}" class="btn btn-subtle no-underline">Back to List</a>
</div>