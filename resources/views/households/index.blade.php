@extends('layouts.app')

@section('title', 'Households — 2D MIS')

{{-- Phase 1: Prototype-aligned Households with shared details panel --}}
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/datatables.css') }}">
    <style>
        /* ── Households scope: token skin over the DataTables Bootstrap
           integration. Prefixed with #households-screen — nothing here
           can leak to other screens. ── */
        #households-screen table.dataTable td {
            font-size: 0.8rem;
        }

        #households-screen table.dataTable th {
            font-size: 0.85rem;
            background-color: var(--color-navy);
            color: #fff;
            border-bottom: 0;
            white-space: nowrap;
        }

        #households-screen table.dataTable tbody tr {
            cursor: pointer;
        }

        #households-screen table.dataTable tbody tr:nth-child(odd) td {
            background-color: rgb(15 27 45 / 0.02);
        }

        #households-screen table.dataTable tbody tr:hover td {
            background-color: rgb(37 99 235 / 0.06);
        }

        #households-screen .actions-col {
            width: 120px;
            max-width: 120px;
            text-align: center;
            white-space: nowrap;
        }

        #households-screen .actions-col .btn {
            padding: 2px 6px;
            font-size: 11px;
        }

        #households-screen .dataTables_wrapper .dataTables_length select,
        #households-screen .dataTables_wrapper .dataTables_filter input {
            border: 1px solid var(--color-line);
            border-radius: var(--radius-control);
            padding: 0.25rem 0.5rem;
            font-size: 0.85rem;
        }

        #households-screen .dataTables_wrapper .dataTables_paginate .page-item.active .page-link {
            background-color: var(--color-navy);
            border-color: var(--color-navy);
        }

        #households-screen .page-link {
            color: var(--color-navy);
        }
    </style>
@endpush

@section('content')

    @include('partials.page-header', [
        'title' => 'Households',
        'subtitle' => 'Household registry grouped under client records.',
        'actions' => '
            <a href="'.route('households.create').'" class="btn-gold no-underline">+ Add Household</a>',
    ])

    @if (session('success'))
        {{-- Same persistent toast pattern as the layout flash channel:
             manual dismiss, live-region semantics, no silent expiry.
             Phase 20: Alpine state owns visibility + dismissal (was
             bootstrap.Toast via the layout init loop); server flash
             contract unchanged. --}}
        <div class="pointer-events-none fixed inset-x-0 top-[76px] z-[1100] flex flex-col items-end gap-2 px-[1rem] sm:px-[1.75rem]" aria-live="polite">
            <div x-data="{ open: true }"
                 x-show="open"
                 class="pointer-events-auto flex w-full max-w-[420px] items-start gap-[12px] rounded-panel bg-surface p-[1rem] shadow-pop ring-1 ring-line"
                 role="status">
                <span class="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-pill bg-teal/[0.12] text-teal" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5"><polyline points="20 6 9 17 4 12"/></svg>
                </span>
                <div class="min-w-0 flex-1 text-dense leading-snug text-ink">{{ session('success') }}</div>
                <button type="button" class="btn-close shrink-0" @click="open = false" aria-label="Close"></button>
            </div>
        </div>
    @endif

    <div id="households-screen">
        <section class="data-card" aria-label="Household registry">
            <div class="data-card-body flex flex-col gap-[14px]">
                @include('partials.filter-chips', ['filterChips' => $filterChips])

                <div class="flex flex-wrap items-end gap-3">
                    <div class="flex items-end gap-2 max-lg:w-full">
                        <button id="resetFilters" class="btn-subtle w-full lg:w-auto">Reset</button>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto px-[1.25rem] pb-[1.25rem]" tabindex="0" aria-label="Household table, scrolls horizontally on narrow screens">
                <table id="householdsTable" class="table table-sm" style="width:100%;">
                    <thead>
                        <tr>
                            <th>Household ID</th>
                            <th>Head of Household</th>
                            <th>Municipality</th>
                            <th>Barangay</th>
                            <th>Members</th>
                            <th class="actions-col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- Loaded via AJAX (server-side DataTables); action
                             buttons are rendered by the controller feed. --}}
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    @include('partials.confirm-modal')
@endsection

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="{{ asset('js/components/DetailsPanel.js') }}"></script>
    <script src="{{ asset('js/components/FilterChips.js') }}"></script>
    <script>
        $(document).ready(function() {
            $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });

            var table = $('#householdsTable').DataTable({
                processing: true,
                serverSide: true,
                autoWidth: false,
                ajax: {
                    url: '{{ route('households.data') }}',
                    type: 'POST',
                    data: function(d) {
                        var p = (window.householdFilters && window.householdFilters.getParams)
                            ? window.householdFilters.getParams()
                            : {};
                        d.municipality = p.municipality || '';
                        d.barangay = p.barangay || '';
                    }
                },
                columns: [
                    { data: "household_id" },
                    { data: "head_name" },
                    { data: "municipality" },
                    { data: "barangay" },
                    { data: "members" },
                    { data: "actions" }
                ],
                columnDefs: [{ targets: 5, orderable: false, searchable: false }],
                order: [[0, 'asc']],
                pageLength: 25,
                lengthMenu: [25, 50, 100],
                createdRow: function(row, data) {
                    $(row).attr({
                        'data-id': data.id,
                        'tabindex': 0,
                        'aria-label': 'Household ' + data.household_id + ', open details'
                    });
                }
            });

            // FilterChips — shared Phase 2C component (server-side DataTables feed).
            if (window.FilterChips) {
                window.householdFilters = FilterChips.init({
                    id: 'households-filters',
                    host: document.querySelector('[data-filter-host="households-filters"]'),
                    onApply: function() { table.draw(); }
                });
            }

            $('#resetFilters').on('click', function() {
                if (window.householdFilters && window.householdFilters.clearAll) {
                    window.householdFilters.clearAll();
                }
                table.draw();
            });

            // Row click -> shared details panel
            $('#householdsTable tbody').on('click', 'tr', function(e) {
                if ($(e.target).closest('.actions-col').length) {
                    return;
                }
                var id = $(this).data('id');
                if (id) {
                    window.DetailsPanel.load('households', id, {
                        url: '{{ route('households.show', '__ID__') }}'.replace('__ID__', id) + '?panel=1'
                    });
                }
            });

            // Keyboard twin (Enter / Space)
            $('#householdsTable tbody').on('keydown', 'tr[tabindex]', function(e) {
                if (e.key !== 'Enter' && e.key !== ' ') return;
                if ($(e.target).closest('.actions-col').length) return;
                e.preventDefault();
                var id = $(this).data('id');
                if (id) {
                    window.DetailsPanel.load('households', id, {
                        url: '{{ route('households.show', '__ID__') }}'.replace('__ID__', id) + '?panel=1'
                    });
                }
            });

            $('#householdsTable').on('click', '.delete-household', function() {
                var id = $(this).data('id');

                window.uiConfirm({
                    title: 'Delete household',
                    message: 'Delete this household? This cannot be undone.',
                    confirmLabel: 'Delete'
                }).then(function(ok) {
                    if (!ok) return;

                    fetch('{{ route('households.index') }}/' + id, {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                        })
                        .then(r => r.json())
                        .then(res => {
                            if (res.success) {
                                table.draw();
                            } else {
                                alert(res.message || 'Failed to delete household.');
                            }
                        })
                        .catch(() => alert('Error deleting household.'));
                });
            });
        });
    </script>
@endpush
