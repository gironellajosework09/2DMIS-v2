@extends('layouts.app')

@section('title', $config['title'].' — 2D MIS')

@push('styles')
    <style>
        #reader {
            width: 100%;
            max-width: 500px;
            margin: 20px auto;
        }

        #details .section-line {
            font-weight: 700;
            font-size: 1.25rem;
            color: #dc3545;
            margin-top: 10px;
        }
    </style>
@endpush

@section('content')
    <div class="card shadow-lg border-0 p-4">
        <h3 class="mb-3 text-center">{{ $config['title'] }}</h3>

        @php($fields = $config['ui']['fields'] ?? [])

        @if (count(array_intersect($fields, ['date_applied', 'date_paid'])) > 0)
            <div class="row mb-3">
                @if (in_array('date_applied', $fields, true))
                    <div class="col-md-6">
                        <label class="form-label">Date Applied</label>
                        <input type="date" id="constDateApplied" class="form-control">
                    </div>
                @endif
                @if (in_array('date_paid', $fields, true))
                    <div class="col-md-6">
                        <label class="form-label">Date Paid</label>
                        <input type="date" id="constDatePaid" class="form-control">
                    </div>
                @endif
            </div>
        @endif

        @if (in_array('amount_paid', $fields, true))
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="amountPaid" class="form-label">Amount Paid</label>
                    <input type="number" step="0.01" id="amountPaid" class="form-control" placeholder="Enter amount">
                </div>
            </div>
        @elseif (! empty($config['ui']['amount_paid_readonly'] ?? null))
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Amount Paid</label>
                    <input type="text" class="form-control" value="{{ $config['ui']['amount_paid_readonly'] }}" readonly>
                </div>
            </div>
        @endif

        <div id="reader"></div>

        <div class="mt-3" id="scanResultArea" style="display:none;">
            <h5>{{ ($config['mode'] ?? null) === 'seat_attendance' || ($config['mode'] ?? null) === 'unpaid_attendance' ? 'Transaction Details' : 'Client Details' }}</h5>
            <div id="details" class="alert alert-info"></div>
            <div class="text-center">
                <button class="btn btn-success" id="saveBtn">
                    {{ ($config['mode'] ?? null) === 'seat_attendance' || ($config['mode'] ?? null) === 'unpaid_attendance' ? 'Confirm' : 'Save Transaction' }}
                </button>
                <button class="btn btn-secondary" id="cancelBtn">Cancel / Scan Again</button>
            </div>
        </div>

        @if (($config['mode'] ?? null) === 'generic_form')
            <div class="mt-4" id="formArea" style="display:none;">
                <h5>Client Details</h5>
                <div id="clientDetails" class="alert alert-info"></div>

                <form id="transactionForm">
                    <input type="hidden" name="client_id" id="client_id">

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label>Program <span class="text-danger">*</span></label>
                            <select name="program" class="form-select" required>
                                <option value="">-- Select Program --</option>
                                @foreach ($config['programs'] as $program)
                                    <option value="{{ $program }}">{{ $program }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="mb-3 position-relative">
                        <label>Beneficiary <span class="text-danger">*</span></label>
                        <div class="form-check">
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
                        <input type="text" id="existing_search" class="form-control mt-2" placeholder="Search existing client" disabled>
                        <input type="hidden" name="existing_client_id" id="existing_client_id">
                        <ul id="search_results" class="list-group position-absolute w-50 bg-white border"></ul>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label>Date Applied <span class="text-danger">*</span></label>
                            <input type="date" name="date_applied" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label>Type <span class="text-danger">*</span></label>
                            <select name="type" class="form-select" required>
                                <option value="">-- Select Type --</option>
                                @foreach ($config['ui']['types'] as $type)
                                    <option value="{{ $type }}">{{ $type }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label>Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select" required>
                                @foreach ($config['ui']['statuses'] as $status)
                                    <option value="{{ $status }}">{{ $status }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label>Remarks</label>
                        <input type="text" name="remarks" class="form-control uppercase">
                    </div>
                    <div class="mb-3">
                        <label>Comments</label>
                        <input type="text" name="comments" class="form-control uppercase">
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label>Suggested Amount</label>
                            <input type="number" step="0.01" name="suggested_amount" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label>Amount Paid</label>
                            <input type="number" step="0.01" name="amount_paid" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label>Pay Out Date</label>
                            <input type="date" name="payout_date" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label>Date Paid</label>
                            <input type="date" name="date_paid" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label>GWA</label>
                            <input type="number" step="0.0001" name="gwa" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label>Units</label>
                            <input type="number" step="0.0001" name="units" class="form-control">
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary" id="formCancelBtn">Cancel / Scan Again</button>
                        <button type="submit" class="btn btn-primary">Save Transaction</button>
                    </div>
                </form>
            </div>
        @endif
    </div>

    <div class="modal fade" id="messageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Notification</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalMessage"></div>
                <div class="modal-footer">
                    <button type="button" id="modalOkBtn" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <audio id="soundSuccess" src="{{ asset('sounds/success.mp3') }}" preload="auto"></audio>
    <audio id="soundError" src="{{ asset('sounds/not_found.mp3') }}" preload="auto"></audio>
@endsection

@push('scripts')
    <script src="https://unpkg.com/html5-qrcode"></script>
    <script>
        const SCANNER = @json($scannerJs);

        const CSRF = '{{ csrf_token() }}';
        const CSRF_HEADERS = { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': CSRF };

        let html5QrcodeScanner = null;
        let lastScan = null;
        let afterModal = null;

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
            document.getElementById('modalTitle').innerText = title || 'Notification';
            document.getElementById('modalMessage').innerText = msg;
            afterModal = onOk || reloadPage;
            new bootstrap.Modal(document.getElementById('messageModal')).show();
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

        document.getElementById('modalOkBtn').addEventListener('click', function () {
            if (afterModal) afterModal();
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
