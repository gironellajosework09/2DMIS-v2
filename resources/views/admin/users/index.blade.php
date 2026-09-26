@extends('layouts.app')

@section('title', 'User Management — 2D MIS')

{{-- Phase 1: Prototype-aligned User Management with shared details panel --}}
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/datatables.css') }}">
    <style>
        /* ── Admin users scope: token skin over the DataTables
           integration. Prefixed with #users-admin-screen —
           nothing here can leak to other screens. ── */
        #users-admin-screen table.dataTable td {
            font-size: 0.8rem;
        }

        #users-admin-screen table.dataTable th {
            font-size: 0.85rem;
            background-color: var(--color-navy);
            color: #fff;
            border-bottom: 0;
            white-space: nowrap;
        }

        #users-admin-screen table.dataTable tbody tr {
            cursor: pointer;
        }

        #users-admin-screen table.dataTable tbody tr:nth-child(odd) td {
            background-color: rgb(15 27 45 / 0.02);
        }

        #users-admin-screen table.dataTable tbody tr:hover td {
            background-color: rgb(37 99 235 / 0.06);
        }

        #users-admin-screen .dataTables_wrapper .dataTables_length select,
        #users-admin-screen .dataTables_wrapper .dataTables_filter input {
            border: 1px solid var(--color-line);
            border-radius: var(--radius-control);
            padding: 0.25rem 0.5rem;
            font-size: 0.85rem;
        }

        #users-admin-screen .dataTables_wrapper .dataTables_paginate .page-item.active .page-link {
            background-color: var(--color-navy);
            border-color: var(--color-navy);
        }

        #users-admin-screen .page-link {
            color: var(--color-navy);
        }
    </style>
@endpush

@section('content')

    @include('partials.page-header', [
        'title' => 'Super Admin Panel — User Management',
        'subtitle' => 'Accounts that can sign in to the system.',
        'actions' => '
            <a href="'.route('admin.users.create').'" class="btn-gold no-underline">+ Create User</a>',
    ])

    <div id="users-admin-screen">
        @if (session('login_status'))
            <div class="ui-notice ui-notice-info mb-[16px]" role="alert" x-data="{ open: true }" x-show="open">
                {{ session('login_status') }}
                <button type="button" class="btn-close" @click="open = false" aria-label="Close"></button>
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-[16px] flex items-start gap-[10px] rounded-[var(--radius-control)] border-l-[3px] border-[var(--color-red)] bg-[rgb(206_17_38_/_0.06)] p-[13px_16px] text-dense text-ink" role="alert" x-data="{ open: true }" x-show="open">
                {{ $errors->first() }}
                <button type="button" class="btn-close" @click="open = false" aria-label="Close"></button>
            </div>
        @endif

        <section class="data-card" aria-label="User accounts">
            <div class="overflow-x-auto px-[1.25rem] pb-[1.25rem] pt-[4px]">
                <table id="usersTable" class="table align-middle mb-0" style="width:100%;">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th class="text-center" style="width: 160px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $managedUser)
                            <tr data-id="{{ $managedUser->id }}" tabindex="0" aria-label="User {{ $managedUser->username }}, open details">
                                <td>{{ $managedUser->username }}</td>
                                <td>{{ $managedUser->role }}</td>
                                <td>{{ $managedUser->status }}</td>
                                <td>{{ $managedUser->created_at }}</td>
                                <td class="text-center">
                                    @if (in_array($managedUser->id, $protectedUserIds, true))
                                        <button type="button" class="btn-subtle w-full lg:w-auto" disabled>Reset Password</button>
                                    @else
                                        <button type="button" class="btn-navy w-full lg:w-auto reset-btn"
                                            data-id="{{ $managedUser->id }}"
                                            data-username="{{ $managedUser->username }}"
                                            aria-haspopup="dialog">
                                            Reset Password
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    {{-- Password reset modal — Tailwind + Alpine.js (Phase 12). Moved off
         bootstrap.Modal / show.bs.modal / data-bs-*. Form contract preserved
         exactly: same #passwordForm, @csrf, PUT method, field names, and the
         dynamic form.action built from the trigger's data-id. Trigger data
         transfers explicitly from the Reset button (data-id / data-username)
         into Alpine state (no Bootstrap relatedTarget dependency). --}}
    <form method="POST" id="passwordForm"
          x-data="passwordResetModalComponent()"
          x-cloak
          role="dialog"
          aria-modal="true"
          aria-labelledby="passwordModalTitle"
          aria-describedby="passwordModalBody"
          class="pointer-events-none fixed inset-0 z-[200]">
        @csrf
        @method('PUT')
        <div x-show="$store.passwordResetModal.open"
             x-transition.opacity.duration.200ms
             @click="$store.passwordResetModal.close()"
             class="pointer-events-auto absolute inset-0 bg-ink/40"
             aria-hidden="true"></div>
        <div class="pointer-events-none absolute inset-0 flex items-center justify-center overflow-y-auto p-4">
            <div x-show="$store.passwordResetModal.open"
                 x-ref="dialog"
                 x-transition.opacity.duration.200ms
                 @keydown.escape.window="$store.passwordResetModal.close()"
                 @keydown.tab.prevent.stop="handleTab($event)"
                 class="pointer-events-auto flex max-h-[90vh] w-full max-w-[480px] flex-col overflow-hidden rounded-panel bg-surface shadow-pop ring-1 ring-line">
                <div class="flex shrink-0 items-center justify-between gap-2 border-b border-line bg-navy px-[1.25rem] py-[1rem]">
                    <h5 id="passwordModalTitle" class="mb-0 text-dense font-heading font-semibold text-white">Reset Password</h5>
                    <button type="button"
                        class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-btn text-white/70 transition duration-150 ease-standard hover:bg-white/10 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-gold"
                        @click="$store.passwordResetModal.close()"
                        aria-label="Close">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div id="passwordModalBody" class="min-h-0 flex-1 overflow-y-auto p-[1.25rem]">
                    <input type="hidden" name="user_id" id="user_id">
                    <div>
                        <label for="modal_username" class="field-label">Username</label>
                        <input type="text" id="modal_username" class="form-control" disabled>
                    </div>
                    <div class="mt-[12px]">
                        <label for="password" class="field-label">New Password</label>
                        <input type="password" name="password" id="password" class="form-control" required minlength="8">
                    </div>
                    <div class="mt-[12px]">
                        <label for="password_confirmation" class="field-label">Confirm Password</label>
                        <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" required>
                    </div>
                </div>
                <div class="flex shrink-0 items-center justify-end gap-2 border-t border-line bg-neutral-100 px-[1.25rem] py-[0.9rem]">
                    <button type="button" class="btn-subtle" @click="$store.passwordResetModal.close()">Cancel</button>
                    <button type="submit" class="btn-navy">Save</button>
                </div>
            </div>
        </div>
    </form>

    <script>
        (function () {
            document.addEventListener('alpine:init', function () {
                Alpine.store('passwordResetModal', {
                    open: false,
                    _prevFocus: null,
                    openFor: function (id, username) {
                        if (this.open) return;
                        this._prevFocus = document.activeElement;
                        var form = document.getElementById('passwordForm');
                        if (form) {
                            form.action = '{{ url('admin/users') }}/' + id + '/password';
                            var uid = document.getElementById('user_id');
                            if (uid) uid.value = id;
                            var un = document.getElementById('modal_username');
                            if (un) un.value = username || '';
                        }
                        document.body.style.overflow = 'hidden';
                        this.open = true;
                        Alpine.nextTick(function () {
                            var closeBtn = document.querySelector('#passwordModal [aria-label="Close"]');
                            if (closeBtn) closeBtn.focus();
                        });
                    },
                    close: function () {
                        if (!this.open) return;
                        this.open = false;
                        document.body.style.overflow = '';
                        if (this._prevFocus && typeof this._prevFocus.focus === 'function') {
                            this._prevFocus.focus();
                        }
                        this._prevFocus = null;
                    }
                });
            });

            window.passwordResetModalComponent = function () {
                return {
                    get open() { return this.$store.passwordResetModal.open; },
                    close: function () { this.$store.passwordResetModal.close(); },
                    handleTab: function (e) {
                        var dlg = this.$refs.dialog;
                        if (!dlg) return;
                        var focusables = dlg.querySelectorAll('button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])');
                        if (!focusables.length) return;
                        var first = focusables[0];
                        var last = focusables[focusables.length - 1];
                        if (e.shiftKey && document.activeElement === first) { last.focus(); }
                        else if (!e.shiftKey && document.activeElement === last) { first.focus(); }
                    }
                };
            };

            window.openPasswordResetModal = function (id, username) {
                Alpine.store('passwordResetModal').openFor(id, username);
            };
        })();
    </script>
@endsection

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="{{ asset('js/components/DetailsPanel.js') }}"></script>
    <script>
        $(document).ready(function() {
            var table = $('#usersTable').DataTable({
                processing: true,
                serverSide: true,
                autoWidth: false,
                ajax: {
                    url: '{{ route('admin.users.data') }}',
                    type: 'POST'
                },
                columns: [
                    { data: 'username' },
                    { data: 'role' },
                    { data: 'status' },
                    { data: 'created_at' },
                    { data: 'actions', orderable: false, searchable: false }
                ],
                order: [[3, 'desc']],
                pageLength: 25,
                lengthMenu: [25, 50, 100],
                createdRow: function(row, data) {
                    $(row).attr({
                        'data-id': data.id,
                        'tabindex': 0,
                        'aria-label': 'User ' + data.username + ', open details'
                    });
                }
            });

            // Row click -> shared details panel
            $('#usersTable tbody').on('click', 'tr', function(e) {
                if ($(e.target).closest('td:last-child').length) {
                    return;
                }
                var id = $(this).data('id');
                if (id) {
                    window.DetailsPanel.load('users', id, {
                        url: '{{ route('admin.users.show', '__ID__') }}'.replace('__ID__', id) + '?panel=1'
                    });
                }
            });

            // Keyboard twin (Enter / Space)
            $('#usersTable tbody').on('keydown', 'tr[tabindex]', function(e) {
                if (e.key !== 'Enter' && e.key !== ' ') return;
                if ($(e.target).closest('td:last-child').length) return;
                e.preventDefault();
                var id = $(this).data('id');
                if (id) {
                    window.DetailsPanel.load('users', id, {
                        url: '{{ route('admin.users.show', '__ID__') }}'.replace('__ID__', id) + '?panel=1'
                    });
                }
            });

            // Password modal — Alpine (Phase 12): a single delegated handler
            // drives both the server-rendered rows and the DataTables AJAX
            // rows (both carry .reset-btn + data-id/data-username), opening the
            // migrated Alpine modal via the window.openPasswordResetModal bridge.
            // Disabled (protected super-admin) buttons do not dispatch clicks.
            $(document).on('click', '.reset-btn', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var id = $(this).data('id');
                var username = $(this).data('username');
                window.openPasswordResetModal(id, username);
            });
        });
    </script>
@endpush
