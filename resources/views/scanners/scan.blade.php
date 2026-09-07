@extends('layouts.app')

@section('title', $config['title'].' — 2D MIS')

{{-- Batch G migration (UI_UX_ANALYSIS §8.9 Group 2, scanner engine shell):
     presentation-only. The html5-qrcode mount (#reader), the lookup/save
     fetch contracts, the modal + audio feedback and every id the script
     binds to are unchanged — the <script> block below is byte-preserved.
     Layout follows the prototype scanner language: navy capture viewport,
     framed scan region, result card in a companion column. --}}
@push('styles')
    <style>
        /* ── Scanner screen scope. Prefixed with #scanner-screen —
           nothing here can leak to other screens. ── */
        #scanner-screen .scanner-viewport {
            background-color: var(--color-navy);
            border-radius: var(--radius-panel);
            padding: 32px 20px;
            min-height: 400px;
        }

        #scanner-screen .scanner-frame {
            width: min(320px, 100%);
            margin-inline: auto;
            border: 3px solid rgb(252 209 22 / 0.4);
            border-radius: 20px;
            padding: 8px;
        }

        #scanner-screen #reader {
            width: 100%;
            max-width: 500px;
            margin-inline: auto;
        }

        #scanner-screen .scanner-label {
            color: rgb(255 255 255 / 0.6);
            font-size: var(--ui-text-sm);
            text-align: center;
            margin-top: 20px;
        }

        #scanner-screen .result-heading {
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 16px;
            margin-bottom: 24px;
            border-bottom: 1px solid var(--color-line);
        }

        #scanner-screen #details .section-line,
        #scanner-screen #details .fs-4 {
            color: var(--color-navy);
        }

        #scanner-screen #details .section-line {
            font-weight: 700;
            font-size: 1rem;
            letter-spacing: 0.04em;
            margin-top: 10px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--color-line-light);
        }
    </style>
@endpush

@section('content')
    @include('partials.breadcrumbs', [
        'breadcrumbs' => [
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => $config['title']],
        ],
    ])

    @include('partials.page-header', [
        'title' => $config['title'],
        'subtitle' => 'Scan a QR code to validate, then confirm the transaction.',
    ])

    @php($fields = $config['ui']['fields'] ?? [])
    @php($attendanceMode = ($config['mode'] ?? null) === 'seat_attendance' || ($config['mode'] ?? null) === 'unpaid_attendance')

    <div id="scanner-screen" class="grid grid-cols-1 items-start gap-[24px] xl:grid-cols-[minmax(0,560px)_minmax(0,1fr)]">
        {{-- Capture column: pre-scan setup fields + camera viewport --}}
        <section class="data-card" aria-label="Scanner">
            <div class="data-card-body flex flex-col gap-[16px]">
                @if (count(array_intersect($fields, ['date_applied', 'date_paid'])) > 0)
                    <div class="grid grid-cols-1 gap-[12px] sm:grid-cols-2">
                        @if (in_array('date_applied', $fields, true))
                            <div class="min-w-0">
                                <label for="constDateApplied" class="field-label">Date Applied</label>
                                <input type="date" id="constDateApplied" class="form-control">
                            </div>
                        @endif
                        @if (in_array('date_paid', $fields, true))
                            <div class="min-w-0">
                                <label for="constDatePaid" class="field-label">Date Paid</label>
                                <input type="date" id="constDatePaid" class="form-control">
                            </div>
                        @endif
                    </div>
                @endif

                @if (in_array('amount_paid', $fields, true))
                    <div class="max-w-[280px] min-w-0">
                        <label for="amountPaid" class="field-label">Amount Paid</label>
                        <input type="number" step="0.01" id="amountPaid" class="form-control" placeholder="Enter amount">
                    </div>
                @elseif (! empty($config['ui']['amount_paid_readonly'] ?? null))
                    <div class="max-w-[280px] min-w-0">
                        <span class="field-label">Amount Paid</span>
                        <input type="text" class="form-control" value="{{ $config['ui']['amount_paid_readonly'] }}" readonly>
                    </div>
                @endif

                <div class="scanner-viewport">
                    <div class="scanner-frame">
                        <div id="reader"></div>
                    </div>
                    <p class="scanner-label mb-0">Position the QR code within the frame</p>
                </div>
            </div>
        </section>

        {{-- Result column: scan outcome + generic-form flow --}}
        <section class="data-card" aria-label="Scan result">
            <div class="data-card-body flex flex-col gap-[24px]">

                <div id="scanResultArea" style="display:none;">
                    <div class="result-heading">
                        <span class="grid h-7 w-7 shrink-0 place-items-center rounded-pill bg-teal/[0.12] text-teal" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><polyline points="20 6 9 17 4 12"/></svg>
                        </span>
                        <h3 class="mb-0 text-base font-semibold text-ink">{{ $attendanceMode ? 'Transaction Details' : 'Client Details' }}</h3>
                    </div>
                    <div id="details" class="rounded-[var(--radius-control)] border border-line bg-surface-hover p-[14px] text-dense leading-relaxed text-ink"></div>
                    <div class="mt-[16px] flex justify-center gap-2">
                        <button class="btn-navy" id="saveBtn">
                            {{ $attendanceMode ? 'Confirm' : 'Save Transaction' }}
                        </button>
                        <button class="btn-subtle" id="cancelBtn">Cancel / Scan Again</button>
                    </div>
                </div>

                @if (($config['mode'] ?? null) === 'generic_form')
                    <div id="formArea" style="display:none;">
                        <div class="result-heading">
                            <span class="grid h-7 w-7 shrink-0 place-items-center rounded-pill bg-navy/[0.08] text-navy" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            </span>
                            <h3 class="mb-0 text-base font-semibold text-ink">Client Details</h3>
                        </div>
                        <div id="clientDetails" class="rounded-[var(--radius-control)] border border-line bg-surface-hover p-[14px] text-dense text-ink"></div>

                        <form id="transactionForm" class="mt-[16px] flex flex-col gap-[12px]">
                            <input type="hidden" name="client_id" id="client_id">

                            <div class="max-w-[360px] min-w-0">
                                <label for="program" class="field-label">Program <span class="text-danger">*</span></label>
                                <select name="program" id="program" class="form-select" required>
                                    <option value="">-- Select Program --</option>
                                    @foreach ($config['programs'] as $program)
                                        <option value="{{ $program }}">{{ $program }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="relative">
                                <span class="field-label">Beneficiary <span class="text-danger">*</span></span>
                                <div class="form-check mt-1">
                                    <input class="form-check-input" type="radio" name="patient_option" id="patient_self" value="self" checked>
                                    <label class="form-check-label" for="patient_self">
                                        Self (<span id="selfName">Scanned Client</span>)
                                    </label>
                                </div>

                                <div class="form-check mt-1">
                                    <input class="form-check-input" type="radio" name="patient_option" id="patient_custom" value="custom">
                                    <label class="form-check-label" for="patient_custom">Enter Name</label>
                                </div>
                                <input type="text" name="patient_name_custom" id="patient_name_custom_input" class="form-control mt-2" placeholder="Enter patient name" disabled>

                                <div class="form-check mt-1">
                                    <input class="form-check-input" type="radio" name="patient_option" id="patient_existing" value="existing">
                                    <label class="form-check-label" for="patient_existing">Select Existing Client</label>
                                </div>
                                <input type="text" id="existing_search" class="form-control mt-2" placeholder="Search existing client" disabled aria-label="Search existing client">
                                <input type="hidden" name="existing_client_id" id="existing_client_id">
                                <ul id="search_results" class="list-group position-absolute bg-white border" style="width:min(24rem,100%); z-index:1000;"></ul>
                            </div>

                            <div class="grid grid-cols-1 gap-[12px] sm:grid-cols-3">
                                <div class="min-w-0">
                                    <label for="date_applied" class="field-label">Date Applied <span class="text-danger">*</span></label>
                                    <input type="date" name="date_applied" id="date_applied" class="form-control" required>
                                </div>
                                <div class="min-w-0">
                                    <label for="type" class="field-label">Type <span class="text-danger">*</span></label>
                                    <select name="type" id="type" class="form-select" required>
                                        <option value="">-- Select Type --</option>
                                        @foreach ($config['ui']['types'] as $type)
                                            <option value="{{ $type }}">{{ $type }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="min-w-0">
                                    <label for="status" class="field-label">Status <span class="text-danger">*</span></label>
                                    <select name="status" id="status" class="form-select" required>
                                        @foreach ($config['ui']['statuses'] as $status)
                                            <option value="{{ $status }}">{{ $status }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-[12px] sm:grid-cols-2">
                                <div class="min-w-0">
                                    <label for="remarks" class="field-label">Remarks</label>
                                    <input type="text" name="remarks" id="remarks" class="form-control uppercase">
                                </div>
                                <div class="min-w-0">
                                    <label for="comments" class="field-label">Comments</label>
                                    <input type="text" name="comments" id="comments" class="form-control uppercase">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-[12px] sm:grid-cols-3">
                                <div class="min-w-0">
                                    <label for="suggested_amount" class="field-label">Suggested Amount</label>
                                    <input type="number" step="0.01" name="suggested_amount" id="suggested_amount" class="form-control">
                                </div>
                                <div class="min-w-0">
                                    <label for="amount_paid" class="field-label">Amount Paid</label>
                                    <input type="number" step="0.01" name="amount_paid" id="amount_paid" class="form-control">
                                </div>
                                <div class="min-w-0">
                                    <label for="payout_date" class="field-label">Pay Out Date</label>
                                    <input type="date" name="payout_date" id="payout_date" class="form-control">
                                </div>
                                <div class="min-w-0">
                                    <label for="date_paid" class="field-label">Date Paid</label>
                                    <input type="date" name="date_paid" id="date_paid" class="form-control">
                                </div>
                                <div class="min-w-0">
                                    <label for="gwa" class="field-label">GWA</label>
                                    <input type="number" step="0.0001" name="gwa" id="gwa" class="form-control">
                                </div>
                                <div class="min-w-0">
                                    <label for="units" class="field-label">Units</label>
                                    <input type="number" step="0.0001" name="units" id="units" class="form-control">
                                </div>
                            </div>

                            <div class="mt-[4px] flex items-center justify-end gap-2">
                                <button type="button" class="btn-subtle" id="formCancelBtn">Cancel / Scan Again</button>
                                <button type="submit" class="btn-navy">Save Transaction</button>
                            </div>
                        </form>
                    </div>
                @endif
            </div>
        </section>
    </div>

    {{-- Scanner message modal (#messageModal) — Tailwind + Alpine.js (Phase 13).
         Migrated off bootstrap.Modal / data-bs-*. The showModal(msg, type, title,
         onOk) public contract and callback (afterModal) sequencing are preserved:
         title/message are still set as plain text (innerText — keeps newlines in
         the multi-line "Already Saved" details), the OK button still runs the
         callback synchronously on click (default reloadPage / resumeAfterModal),
         backdrop + ESC still close without firing the callback. --}}
    <div id="messageModal"
         x-data="scannerMessageModalComponent()"
         x-cloak
         role="dialog"
         aria-modal="true"
         aria-labelledby="modalTitle"
         aria-describedby="modalMessage"
         class="pointer-events-none fixed inset-0 z-[200]">
        <div x-show="$store.scannerMessageModal.open"
             x-transition.opacity.duration.200ms
             @click="$store.scannerMessageModal.close()"
             class="pointer-events-auto absolute inset-0 bg-ink/40"
             aria-hidden="true"></div>
        <div class="pointer-events-none absolute inset-0 flex items-center justify-center overflow-y-auto p-4">
            <div x-show="$store.scannerMessageModal.open"
                 x-ref="dialog"
                 x-transition.opacity.duration.200ms
                 @keydown.escape.window="$store.scannerMessageModal.close()"
                 @keydown.tab.prevent.stop="handleTab($event)"
                 class="pointer-events-auto flex max-h-[90vh] w-full max-w-[480px] flex-col rounded-panel bg-surface shadow-pop ring-1 ring-line">
                <div class="flex shrink-0 items-center justify-between gap-2 border-b border-line bg-navy px-[1.25rem] py-[1rem]">
                    <h5 id="modalTitle" class="mb-0 text-dense font-heading font-semibold text-white">Notification</h5>
                    <button type="button"
                        class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-btn text-white/70 transition duration-150 ease-standard hover:bg-white/10 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-gold"
                        @click="$store.scannerMessageModal.close()"
                        aria-label="Close">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div id="modalMessage" class="min-h-0 flex-1 overflow-y-auto whitespace-pre-line p-[1.25rem] text-sm leading-relaxed text-ink"></div>
                <div class="flex shrink-0 items-center justify-end gap-2 border-t border-line bg-neutral-100 px-[1.25rem] py-[0.9rem]">
                    <button type="button" id="modalOkBtn" class="btn-navy" @click="$store.scannerMessageModal.handleOk()">OK</button>
                </div>
            </div>
        </div>
    </div>

    <audio id="soundSuccess" src="{{ asset('sounds/success.mp3') }}" preload="auto"></audio>
    <audio id="soundError" src="{{ asset('sounds/not_found.mp3') }}" preload="auto"></audio>

    <script>
        (function () {
            document.addEventListener('alpine:init', function () {
                Alpine.store('scannerMessageModal', {
                    open: false,
                    title: '',
                    body: '',
                    afterModal: null,
                    _prevFocus: null,
                    openWith: function (opts) {
                        this.title = opts.title || 'Notification';
                        this.body = opts.body || '';
                        this.afterModal = opts.afterModal || null;
                        this._prevFocus = document.activeElement;
                        var t = document.getElementById('modalTitle');
                        if (t) t.innerText = this.title;
                        var b = document.getElementById('modalMessage');
                        if (b) b.innerText = this.body;
                        document.body.style.overflow = 'hidden';
                        this.open = true;
                        Alpine.nextTick(function () {
                            var ok = document.getElementById('modalOkBtn');
                            if (ok) ok.focus();
                        });
                    },
                    close: function () {
                        if (!this.open) return;
                        this.open = false;
                        this.afterModal = null;
                        document.body.style.overflow = '';
                        if (this._prevFocus && typeof this._prevFocus.focus === 'function') {
                            this._prevFocus.focus();
                        }
                        this._prevFocus = null;
                    },
                    handleOk: function () {
                        var cb = this.afterModal;
                        this.close();
                        if (cb && typeof cb === 'function') cb();
                    }
                });
            });

            window.scannerMessageModalComponent = function () {
                return {
                    get open() { return this.$store.scannerMessageModal.open; },
                    close: function () { this.$store.scannerMessageModal.close(); },
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
        })();
    </script>
@endsection

@push('scripts')
    <script src="https://unpkg.com/html5-qrcode"></script>
    <script>
        const SCANNER = @json($scannerJs);

        const CSRF = '{{ csrf_token() }}';
        const CSRF_HEADERS = { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': CSRF };

        let html5QrcodeScanner = null;
        let lastScan = null;

        function playSound(type) {
            const el = type === 'success'
                ? document.getElementById('soundSuccess')
                : document.getElementById('soundError');
            if (el) {
                el.currentTime = 0;
                el.play().catch(() => {});
            }
        }

        function showModal(msg, type, title, onOk) {
            var store = Alpine.store('scannerMessageModal');
            if (store) {
                store.openWith({ title: title || 'Notification', body: msg, afterModal: onOk || reloadPage });
            } else {
                document.getElementById('modalTitle').innerText = title || 'Notification';
                document.getElementById('modalMessage').innerText = msg;
            }
            playSound(type || 'error');
        }

        function reloadPage() {
            location.reload();
        }

        function resumeAfterModal() {
            document.getElementById('scanResultArea').style.display = 'none';
            if (document.getElementById('formArea')) {
                document.getElementById('formArea').style.display = 'none';
            }
            try {
                html5QrcodeScanner.render(onScanSuccess, onScanFailure);
            } catch (e) {
                console.error('Error resuming scanner:', e);
            }
        }

        function nextAfterModal() {
            return SCANNER.resume ? resumeAfterModal : reloadPage;
        }

        function onScanFailure() {}

        function renderResult(info, decodedText) {
            let html;
            if (SCANNER.mode === 'seat_attendance') {
                html = `
                    <div class="fs-4 fw-bold text-primary mb-2">${info.program || '—'}</div>
                    <div><strong>Full Name:</strong> ${info.patient_name || decodedText}</div>
                    <div><strong>Town:</strong> ${info.town || '—'}</div>
                    <div class="section-line">SECTION: ${info.section || '—'}</div>
                    <div class="section-line">BOX: ${info.box || '—'}</div>
                    <div class="section-line">ROW: ${info.row || '—'}</div>
                    <div class="section-line">SEAT: ${info.seat || '—'}</div>
                    <div class="mt-2"><strong>Comments:</strong> ${info.comments || '—'}</div>`;
            } else if (SCANNER.mode === 'unpaid_attendance') {
                html = `
                    <div class="fs-4 fw-bold text-primary mb-2">${info.program || '—'}</div>
                    <div><strong>Full Name:</strong> ${info.patient_name || decodedText}</div>
                    <div><strong>Status:</strong> ${info.status || '—'}</div>
                    <div><strong>Comments:</strong> ${info.comments || '—'}</div>`;
            } else if (SCANNER.mode === 'update_in_place') {
                html = `<b>Name:</b> ${info.patient_name}<br><b>Program:</b> ${info.program}<br><b>Remarks:</b> ${info.remarks || '—'}`;
            } else {
                html = `<b>Name:</b> ${info.full_name}<br><b>Client ID:</b> ${info.id}`;
                if (info.municipality) html += `<br><b>Municipality:</b> ${info.municipality}`;
                if (info.barangay) html += `<br><b>Barangay:</b> ${info.barangay}`;
                if (info.program) html += `<br><b>Program:</b> ${info.program}`;
            }
            document.getElementById('details').innerHTML = html;
        }

        function onScanSuccess(decodedText) {
            html5QrcodeScanner.clear();

            const body = new URLSearchParams();
            body.append('action', 'lookup');
            body.append('scanned', decodedText);

            fetch(SCANNER.lookupUrl, {
                method: 'POST',
                headers: CSRF_HEADERS,
                body,
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        lastScan = { decodedText, id: data.data.id ?? null, info: data.data, alreadyScanned: false };
                        if (SCANNER.generic) {
                            document.getElementById('clientDetails').innerHTML =
                                `<b>Name:</b> ${data.data.full_name}<br><b>Client ID:</b> ${data.data.id}`;
                            document.getElementById('client_id').value = data.data.id;
                            document.getElementById('selfName').innerText = data.data.full_name;
                            document.getElementById('formArea').style.display = 'block';
                            if (SCANNER.scanSuccessSound) playSound('success');
                            return;
                        }
                        renderResult(data.data, decodedText);
                        document.getElementById('scanResultArea').style.display = 'block';
                    } else if (SCANNER.attendance && data.message && data.message.indexOf('already been scanned') !== -1) {
                        const b2 = new URLSearchParams();
                        b2.append('action', 'lookup_ignore_scan');
                        b2.append('scanned', decodedText);
                        fetch(SCANNER.lookupUrl, {
                            method: 'POST',
                            headers: CSRF_HEADERS,
                            body: b2,
                        })
                            .then(r => r.json())
                            .then(infoData => {
                                const info = infoData.data || {};
                                lastScan = { decodedText, id: info.id ?? null, info, alreadyScanned: true };
                                renderResult(info, decodedText);
                                document.getElementById('scanResultArea').style.display = 'block';
                            })
                            .catch(() => {
                                lastScan = null;
                                showModal('Already scanned, but details unavailable.', 'error', 'Error', nextAfterModal());
                            });
                    } else {
                        lastScan = null;
                        showModal(data.message || 'No transaction found.', 'error', 'Error', nextAfterModal());
                    }
                })
                .catch(() => {
                    lastScan = null;
                    showModal('Network or server error.', 'error', 'Error', nextAfterModal());
                });
        }

        document.getElementById('saveBtn').addEventListener('click', function () {
            if (!lastScan) {
                showModal('Invalid or unrecognized QR code.', 'error', 'Error', nextAfterModal());
                return;
            }

            if (lastScan.alreadyScanned) {
                showModal('This QR code has already been scanned.', 'error', 'Error', nextAfterModal());
                return;
            }

            if (SCANNER.mode === 'date_guarded_transaction') {
                if (!document.getElementById('constDateApplied').value || !document.getElementById('constDatePaid').value) {
                    showModal('Please fill in Date Applied and Date Paid before saving.', 'error', 'Required Fields', nextAfterModal());
                    return;
                }
                if (SCANNER.fields.includes('amount_paid') && !document.getElementById('amountPaid').value) {
                    showModal('Please fill in Amount Paid before saving.', 'error', 'Required Fields', nextAfterModal());
                    return;
                }
            }

            if (SCANNER.mode === 'update_in_place' && !document.getElementById('constDatePaid').value) {
                showModal('Please select Date Paid.', 'error', 'Required Field', nextAfterModal());
                return;
            }

            const body = new URLSearchParams();
            body.append('action', 'save');

            const idField = SCANNER.mode === 'update_in_place' ? 'transaction_id' : 'id';
            if (lastScan.id !== null && lastScan.id !== undefined) {
                body.append(idField, lastScan.id);
            }

            if (SCANNER.fields.includes('date_applied')) {
                body.append('date_applied', document.getElementById('constDateApplied').value || '');
            }
            if (SCANNER.fields.includes('date_paid')) {
                body.append('date_paid', document.getElementById('constDatePaid').value || '');
            }
            if (SCANNER.fields.includes('amount_paid')) {
                body.append('amount_paid', document.getElementById('amountPaid').value || '');
            }
            if (SCANNER.attendance) {
                body.append('scanned', lastScan.decodedText);
            }

            fetch(SCANNER.saveUrl, {
                method: 'POST',
                headers: CSRF_HEADERS,
                body,
            })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        let message = res.message || SCANNER.successMessage;
                        if (SCANNER.mode === 'exam_derived' && lastScan.info.program) {
                            message = 'Transaction saved successfully under ' + lastScan.info.program;
                        }
                        showModal(message, 'success', 'Success', nextAfterModal());
                    } else if (res.alreadySaved) {
                        let msg = res.message || 'Transaction already recorded for this client.';
                        if (res.existing) {
                            const ex = res.existing;
                            msg += '\n\nExisting transaction details:\n';
                            if (ex.id) msg += 'Transaction ID: ' + ex.id + '\n';
                            if (ex.date_applied) msg += 'Date Applied: ' + ex.date_applied + '\n';
                            if (ex.date_paid) msg += 'Date Paid: ' + (ex.date_paid || '—') + '\n';
                            if (ex.status) msg += 'Status: ' + ex.status + '\n';
                            if (ex.remarks) msg += 'Remarks: ' + ex.remarks + '\n';
                        }
                        showModal(msg, 'error', 'Already Saved', nextAfterModal());
                    } else {
                        showModal(res.message || 'Error saving transaction.', 'error', 'Error', nextAfterModal());
                    }
                })
                .catch(() => {
                    showModal('Network or server error.', 'error', 'Error', nextAfterModal());
                });
        });

        document.getElementById('cancelBtn').addEventListener('click', function () {
            SCANNER.resume ? resumeAfterModal() : reloadPage();
        });

        if (SCANNER.generic) {
            const selfRadio = document.getElementById('patient_self');
            const customRadio = document.getElementById('patient_custom');
            const existingRadio = document.getElementById('patient_existing');
            const customInput = document.getElementById('patient_name_custom_input');
            const searchInput = document.getElementById('existing_search');
            const existingClientId = document.getElementById('existing_client_id');
            const results = document.getElementById('search_results');

            function resetPatientInputs() {
                customInput.disabled = true;
                customInput.value = '';
                searchInput.disabled = true;
                searchInput.value = '';
                existingClientId.value = '';
                results.innerHTML = '';
            }

            selfRadio.addEventListener('change', resetPatientInputs);
            customRadio.addEventListener('change', () => {
                customInput.disabled = false;
                customInput.focus();
                searchInput.disabled = true;
                searchInput.value = '';
                existingClientId.value = '';
                results.innerHTML = '';
            });
            existingRadio.addEventListener('change', () => {
                customInput.disabled = true;
                customInput.value = '';
                searchInput.disabled = false;
                searchInput.focus();
                existingClientId.value = '';
                results.innerHTML = '';
            });

            searchInput.addEventListener('input', () => {
                const val = searchInput.value.trim();
                results.innerHTML = '';
                if (val.length < 2) return;

                fetch('{{ route('transactions.clients-search') }}?q=' + encodeURIComponent(val))
                    .then(res => res.json())
                    .then(data => {
                        data.forEach(c => {
                            const fullName = c.lastname + ', ' + c.firstname + ' ' + (c.middlename ?? '') + ' ' + (c.extensionname ?? '');
                            const li = document.createElement('li');
                            li.classList.add('list-group-item', 'list-group-item-action');
                            li.textContent = fullName.trim();
                            li.style.cursor = 'pointer';
                            li.addEventListener('click', () => {
                                searchInput.value = fullName.trim();
                                existingClientId.value = c.id;
                                results.innerHTML = '';
                            });
                            results.appendChild(li);
                        });
                    });
            });

            document.addEventListener('click', (e) => {
                if (!results.contains(e.target) && e.target !== searchInput) {
                    results.innerHTML = '';
                }
            });

            customInput.addEventListener('input', function () {
                this.value = this.value.toUpperCase();
            });

            document.getElementById('formCancelBtn').addEventListener('click', () => reloadPage());

            document.getElementById('transactionForm').addEventListener('submit', function (e) {
                e.preventDefault();
                const fd = new FormData(this);
                fd.append('action', 'save');
                fetch(SCANNER.saveUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF },
                    body: fd,
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            showModal(data.message || 'Saved', 'success', 'Success', reloadPage);
                        } else {
                            showModal(data.message || 'Error saving', 'error', 'Error', reloadPage);
                        }
                    })
                    .catch(() => showModal('Save failed.', 'error', 'Error', reloadPage));
            });
        }

        html5QrcodeScanner = new Html5QrcodeScanner(
            'reader',
            { fps: 10, qrbox: { width: 250, height: 250 } },
            false
        );
        html5QrcodeScanner.render(onScanSuccess, onScanFailure);
    </script>
@endpush
