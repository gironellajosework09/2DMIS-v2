{{-- User details (full page or panel partial) --}}
@php
    $isPanel = $panel ?? false;
    $managedUser = $managedUser ?? $user ?? null;
    $protected = $protected ?? [];
    $pagePerms = $pagePerms ?? [];
    $actionPerms = $actionPerms ?? [];
    $programPerms = $programPerms ?? [];
    $muniScopes = $muniScopes ?? [];
@endphp

@if (! $isPanel)
@extends('layouts.app')
@section('title', 'User Details — 2D MIS')
@section('content')

@endif

<div class="data-card p-[1.25rem]" data-panel-body>
    <div class="mb-[16px] flex flex-wrap items-center justify-between gap-[12px]">
        @if ($isPanel)
            <h2 class="m-0 text-dense font-heading font-semibold text-ink" data-panel-title>User Details</h2>
        @else
            <h1 class="m-0 text-page-title font-heading font-bold text-ink">User Details</h1>
        @endif
        <div class="flex flex-wrap items-center gap-[8px]">
            @if (! $isPanel)
                <a href="{{ route('admin.users.index') }}" class="btn-subtle no-underline">Back</a>
            @else
                <a href="{{ route('admin.users.show', $managedUser) }}" class="btn-subtle no-underline">Open full page</a>
            @endif
            <button type="button" class="btn-gold no-underline" data-edit-user="{{ $managedUser->id }}">Edit</button>
        </div>
    </div>

    <dl class="m-0 grid grid-cols-1 gap-x-[16px] gap-y-[10px] sm:grid-cols-2 lg:grid-cols-3">
        @foreach([
            'Username' => $managedUser->username,
            'Role' => $managedUser->role,
            'Status' => $managedUser->status,
            'Multi-device Exempt' => $managedUser->multi_device_exempt ? 'Yes' : 'No',
            'Created' => $managedUser->created_at,
        ] as $label => $value)
            <div class="min-w-0" data-panel-field>
                <dt class="ui-micro-label">{{ $label }}</dt>
                <dd class="m-0 truncate text-dense text-ink" title="{{ $value }}">{{ $value }}</dd>
            </div>
        @endforeach
    </dl>

    <hr class="my-[24px] border-line-light">
    <h3 class="mb-[12px] text-dense font-heading font-semibold text-ink">Page Permissions</h3>
    <div class="flex flex-wrap gap-2 mb-4">
        @foreach($pagePerms as $perm)
            <span class="program-tag">{{ $perm }}</span>
        @endforeach
        @empty
            <span class="text-dense text-ink-muted">None</span>
        @endforeach
    </div>

    <h3 class="mb-[12px] text-dense font-heading font-semibold text-ink">Action Permissions</h3>
    <div class="flex flex-wrap gap-2 mb-4">
        @foreach($actionPerms as $perm)
            <span class="program-tag">{{ $perm }}</span>
        @endforeach
        @empty
            <span class="text-dense text-ink-muted">None</span>
        @endforeach
    </div>

    <h3 class="mb-[12px] text-dense font-heading font-semibold text-ink">Program Access</h3>
    <div class="flex flex-wrap gap-2 mb-4">
        @foreach($programPerms as $perm)
            <span class="program-tag">{{ $perm }}</span>
        @endforeach
        @empty
            <span class="text-dense text-ink-muted">None</span>
        @endforeach
    </div>

    <h3 class="mb-[12px] text-dense font-heading font-semibold text-ink">Municipality Scope</h3>
    <div class="flex flex-wrap gap-2 mb-4">
        @foreach($muniScopes as $muniId)
            <span class="program-tag">{{ \App\Models\Municipality::find($muniId)?->name ?? $muniId }}</span>
        @endforeach
        @empty
            <span class="text-dense text-ink-muted">None</span>
        @endforeach
    </div>

    @if (in_array($managedUser->id, $protected))
    <div class="mt-4 p-3 bg-amber-50 border border-amber-200 rounded">
        <p class="text-sm text-amber-800"><strong>Protected Account:</strong> This user has Super Admin access and cannot be deleted or have their password reset from this interface.</p>
    </div>
    @endif

    {{-- UX-2: lightweight panel edit (password) --}}
    <div id="userPanelEdit" class="panel-edit-form" hidden>
        <form method="POST" action="{{ route('admin.users.reset-password', $managedUser) }}" data-panel-edit-form>
            @csrf
            @method('PUT')
            <div class="panel-form-errors" role="alert"></div>
            <div class="mb-3">
                <label class="field-label" for="panelUserPassword">New password</label>
                <input type="password" id="panelUserPassword" name="password" class="field-control" required minlength="8">
            </div>
            <div class="mb-3">
                <label class="field-label" for="panelUserPasswordConfirm">Confirm password</label>
                <input type="password" id="panelUserPasswordConfirm" name="password_confirmation" class="field-control" required minlength="8">
            </div>
            <div class="flex items-center gap-2">
                <button type="button" class="btn-subtle" data-edit-user-cancel="{{ $managedUser->id }}">Cancel</button>
                <button type="submit" class="btn-navy">Save Password</button>
            </div>
        </form>
    </div>
</div>

<div data-panel-title style="display:none;">{{ $managedUser->username }}</div>
<div data-panel-sub style="display:none;">{{ $managedUser->role }} &middot; {{ $managedUser->status }}</div>
<div data-panel-meta style="display:none;">
    <span class="status-badge {{ $managedUser->status === 'Active' ? 'is-paid' : 'is-neutral' }}">{{ $managedUser->status }}</span>
    <span class="status-badge {{ $managedUser->multi_device_exempt ? 'is-approved' : 'is-neutral' }}">{{ $managedUser->multi_device_exempt ? 'Multi-device Exempt' : 'Single Device' }}</span>
</div>
<div data-panel-actions style="display:none;">
    <button type="button" class="btn btn-gold" data-edit-user="{{ $managedUser->id }}">Edit</button>
    {{-- Phase 27: the dead #passwordModal trigger (data-bs-toggle/data-bs-target,
         proven inert — no such modal existed) is removed; password reset lives in
         the inline userPanelEdit form above. --}}
</div>

@if (! $isPanel)
@endsection
@endif

<script>
    (function () {
        'use strict';
        var editId = Number('{{ $managedUser->id }}');
        var editBox = document.getElementById('userPanelEdit');
        if (!editBox) return;

        function bindEdit() {
            document.querySelectorAll('[data-edit-user="' + editId + '"]').forEach(function (b) {
                b.addEventListener('click', function () { editBox.hidden = !editBox.hidden; });
            });
        }
        bindEdit();

        document.querySelectorAll('[data-edit-user-cancel="' + editId + '"]').forEach(function (c) {
            c.addEventListener('click', function () { editBox.hidden = true; });
        });

        var form = editBox.querySelector('[data-panel-edit-form]');
        if (form && window.DetailsPanel && window.DetailsPanel.submitPanelForm) {
            window.DetailsPanel.submitPanelForm(form);
        }
    })();
</script>