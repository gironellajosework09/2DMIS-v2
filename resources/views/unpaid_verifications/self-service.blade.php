<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Unpaid Verification — 2D MIS</title>
    {{-- Batch G migration: standalone public head shares the built app
         stylesheet + app.js (Alpine) + ui.css (see auth/login). The grantee
         search/verify/submit fetch contracts are unchanged; the JS-built
         confirmation modal migrated from bootstrap.Modal to Tailwind + Alpine
         (Phase 17, same dynamic create->show->remove lifecycle, same close
         set). Phase 24 removed the Bootstrap JS bundle (zero live consumers);
         Phase 27 removes the Bootstrap CSS CDN — ui.css §4.8–4.10 owns the
         shared families. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            background: var(--color-bg);
            font-family: 'Roboto', system-ui, -apple-system, 'Segoe UI', sans-serif;
            padding: 24px;
        }

        .suggestions-list {
            position: absolute;
            z-index: 2000;
            width: 100%;
            background: var(--color-surface);
            border: 1px solid var(--color-line);
            max-height: 220px;
            overflow: auto;
        }

        .suggestions-list button {
            width: 100%;
            border: none;
            background: none;
            padding: 8px 12px;
            text-align: left;
        }

        .suggestions-list button:hover {
            background: var(--color-surface-hover);
        }
    </style>
</head>
<body>

<div class="data-card mx-auto !p-[1.75rem] max-w-[600px]">
    <h1 class="mb-3 text-center text-lg font-semibold text-ink">Unpaid Verification</h1>

    <div class="mb-3">
        <label for="nameInput" class="field-label">Search your name</label>
        <div class="position-relative">
            <input id="nameInput" class="form-control uppercase" placeholder="Type your full name" autocomplete="off">
            <div id="suggestList" class="suggestions-list hidden"></div>
        </div>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-md-6">
            <label for="municipalitySelect" class="field-label">Municipality</label>
            <select id="municipalitySelect" class="form-select">
                <option value="">-- Select Municipality --</option>
            </select>
        </div>
        <div class="col-md-6 flex align-items-end">
            <button id="verifyBtn" class="btn-navy w-full" disabled>Verify</button>
        </div>
    </div>

    <div id="alertBox"></div>

    <div id="confirmSection" class="hidden mt-4">
        <hr>
        <h2 class="text-center mb-3 text-base font-semibold text-ink">Who will attend the payout?</h2>
        <div class="flex flex-wrap justify-content-center gap-2 mb-3">
            <button id="btnSelf" class="btn-navy">I will come personally</button>
            <button id="btnProxy" class="btn-gold">Proxy</button>
        </div>
    </div>

    <div id="proxyForm" class="hidden">
        <h3 class="mt-3 mb-2 text-dense font-semibold text-ink">Proxy Information</h3>
        <div class="mb-2"><input id="proxyLastname" class="form-control uppercase" placeholder="Lastname" aria-label="Lastname"></div>
        <div class="mb-2"><input id="proxyFirstname" class="form-control uppercase" placeholder="Firstname" aria-label="Firstname"></div>
        <div class="mb-2"><input id="proxyMiddlename" class="form-control uppercase" placeholder="Middlename" aria-label="Middlename"></div>
        <div class="mb-2"><input id="proxyRelationship" class="form-control uppercase" placeholder="Relationship" aria-label="Relationship"></div>
        <div class="mb-2"><input id="proxyPhone" class="form-control" placeholder="Contact Number" aria-label="Contact number"></div>
        <div class="mb-2">
            <label for="proxyBirthdate" class="field-label">Birthdate</label>
            <input type="date" id="proxyBirthdate" class="form-control">
        </div>
        <div class="mb-2">
            <label for="proxyGender" class="field-label">Gender</label>
            <select id="proxyGender" class="form-select">
                <option value="">-- Select Gender --</option>
                <option>Male</option>
                <option>Female</option>
            </select>
        </div>
        <div class="mb-2"><input id="proxyOccupation" class="form-control uppercase" placeholder="Occupation" aria-label="Occupation"></div>
        <div class="mb-3"><input id="proxyMonthlyIncome" class="form-control uppercase" placeholder="Monthly Income" aria-label="Monthly income"></div>
        <button id="submitProxyBtn" class="btn-navy w-full">Submit Proxy Info</button>
    </div>

    <div id="successBox" class="alert alert-success hidden mt-4 text-center"></div>
</div>

<script>
    // Phase 17: localized Alpine store for the JS-built Final Confirmation
    // modal — replaces bootstrap.Modal while preserving the exact dynamic
    // create -> show -> remove -> recreate lifecycle, the trigger contract,
    // the close set (X / Cancel / backdrop click / ESC — none confirm), and
    // the business flow (Confirm -> close+remove -> saveUnpaid). Alpine owns
    // presentation only; saveUnpaid (fetch contract) is untouched.
    document.addEventListener('alpine:init', function () {
        Alpine.store('unpaidConfirmationModal', {
            open: false,
            _prevFocus: null,
            _onConfirm: null,
            _rootEl: null,
            openFor: function (rootEl, onConfirm) {
                this._rootEl = rootEl;
                this._onConfirm = onConfirm;
                this._prevFocus = document.activeElement;
                document.body.style.overflow = 'hidden';
                this.open = true;
                var root = rootEl;
                Alpine.nextTick(function () {
                    var closeBtn = root.querySelector('[aria-label="Close"]');
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
                this._onConfirm = null;
                var root = this._rootEl;
                this._rootEl = null;
                if (root && root.parentNode) {
                    Alpine.destroyTree(root);
                    root.remove();
                }
            },
            confirm: function () {
                var onConfirm = this._onConfirm;
                this.close();
                if (onConfirm) onConfirm();
            }
        });
    });

    window.unpaidConfirmationModalComponent = function () {
        return {
            get open() { return this.$store.unpaidConfirmationModal.open; },
            close: function () { this.$store.unpaidConfirmationModal.close(); },
            confirm: function () { this.$store.unpaidConfirmationModal.confirm(); },
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

    const searchUrl = '{{ route('grantee-search', ['kind' => 'unpaid']) }}';
    const verifyUrl = '{{ route('grantee-search.verify', ['kind' => 'unpaid']) }}';
    const saveUrl = '{{ route('unpaid-verification.submit') }}';
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    let selectedClientId = null;

    function postForm(url, params) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams(params)
        }).then(r => r.json());
    }

    fetch(searchUrl + '?munis=1').then(r => r.json()).then(data => {
        if (data.success) {
            const sel = document.getElementById('municipalitySelect');
            data.municipalities.forEach(m => {
                const o = document.createElement('option');
                o.value = m.id;
                o.textContent = m.name;
                sel.appendChild(o);
            });
        }
    });

    const nameInput = document.getElementById('nameInput');
    const suggestList = document.getElementById('suggestList');
    let debounce = null;

    nameInput.addEventListener('input', () => {
        const q = nameInput.value.trim();
        if (debounce) clearTimeout(debounce);
        if (!q) {
            suggestList.classList.add('hidden');
            return;
        }
        debounce = setTimeout(() => {
            fetch(searchUrl + '?q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(data => {
                    if (!data.success || !data.results.length) {
                        suggestList.innerHTML = '<div class="p-2">No matches</div>';
                        suggestList.classList.remove('hidden');
                        return;
                    }
                    suggestList.innerHTML = '';
                    data.results.forEach(r => {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'text-start';
                        btn.innerHTML = '<strong>' + r.full_name.toUpperCase() + '</strong>';
                        btn.onclick = () => {
                            nameInput.value = r.full_name.toUpperCase();
                            selectedClientId = r.id;
                            suggestList.classList.add('hidden');
                            document.getElementById('verifyBtn').disabled = false;
                        };
                        suggestList.appendChild(btn);
                    });
                    suggestList.classList.remove('hidden');
                });
        }, 250);
    });

    document.addEventListener('click', e => {
        if (!document.querySelector('.position-relative').contains(e.target)) {
            suggestList.classList.add('hidden');
        }
    });

    document.getElementById('verifyBtn').addEventListener('click', () => {
        const muni = document.getElementById('municipalitySelect').value;
        if (!selectedClientId || !muni) {
            document.getElementById('alertBox').innerHTML = '<div class="alert alert-danger">Please select your name and municipality.</div>';
            return;
        }
        postForm(verifyUrl, {
            action: 'verify',
            client_id: selectedClientId,
            municipality_id: muni
        }).then(data => {
            if (!data.success) {
                document.getElementById('alertBox').innerHTML = '<div class="alert alert-danger">' + (data.message || 'Verification failed') + '</div>';
                return;
            }
            document.getElementById('alertBox').innerHTML = '<div class="alert alert-success text-center">Verification successful! Confirm attendance below.</div>';
            document.getElementById('confirmSection').classList.remove('hidden');
        });
    });

    document.getElementById('btnSelf').addEventListener('click', () => {
        showConfirmation(false);
    });

    document.getElementById('btnProxy').addEventListener('click', () => {
        document.getElementById('proxyForm').classList.remove('hidden');
    });

    document.getElementById('submitProxyBtn').addEventListener('click', () => {
        const lname = document.getElementById('proxyLastname').value.trim();
        const fname = document.getElementById('proxyFirstname').value.trim();
        const mname = document.getElementById('proxyMiddlename').value.trim();
        const rel = document.getElementById('proxyRelationship').value.trim();
        const phone = document.getElementById('proxyPhone').value.trim();
        const birthdate = document.getElementById('proxyBirthdate').value;
        const gender = document.getElementById('proxyGender').value;
        const occ = document.getElementById('proxyOccupation').value.trim();
        const income = document.getElementById('proxyMonthlyIncome').value.trim();

        if (!lname || !fname || !rel) {
            alert('Please complete proxy information.');
            return;
        }

        showConfirmation(true, lname, fname, mname, rel);

        window.proxyExtra = { phone, birthdate, gender, occ, income };
    });

    function showConfirmation(isProxy, lname = '', fname = '', mname = '', rel = '') {
        // Phase 17: same dynamic create->append->init->open->...->remove
        // lifecycle, but presentation is Tailwind + Alpine now (no
        // bootstrap.Modal, no data-bs-*). The modal is created fresh each open
        // with the current proxy/self values baked in and removed on close,
        // exactly as before. Confirm still runs saveUnpaid() — untouched fetch
        // contract.
        const modal = document.createElement('div');
        modal.innerHTML = `
        <div x-data="unpaidConfirmationModalComponent()"
             x-cloak
             role="dialog"
             aria-modal="true"
             aria-labelledby="unpaidConfirmationModalTitle"
             class="pointer-events-none fixed inset-0 z-[200]">
            <div x-show="$store.unpaidConfirmationModal.open"
                 x-transition.opacity.duration.200ms
                 @click="$store.unpaidConfirmationModal.close()"
                 class="pointer-events-auto absolute inset-0 bg-ink/40"
                 aria-hidden="true"></div>
            <div class="pointer-events-none absolute inset-0 flex items-center justify-center overflow-y-auto p-4">
                <div x-show="$store.unpaidConfirmationModal.open"
                     x-ref="dialog"
                     x-transition.opacity.duration.200ms
                     @keydown.escape.window="$store.unpaidConfirmationModal.close()"
                     @keydown.tab.prevent.stop="handleTab($event)"
                     class="pointer-events-auto flex max-h-[90vh] w-full max-w-[480px] flex-col overflow-hidden rounded-panel bg-surface shadow-pop ring-1 ring-line">
                    <div class="flex shrink-0 items-center justify-between gap-2 border-b border-line bg-navy px-[1.25rem] py-[1rem]">
                        <h5 id="unpaidConfirmationModalTitle" class="mb-0 text-dense font-heading font-semibold text-white">Final Confirmation</h5>
                        <button type="button"
                            class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-btn text-white/70 transition duration-150 ease-standard hover:bg-white/10 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-gold"
                            @click="$store.unpaidConfirmationModal.close()"
                            aria-label="Close">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <div class="min-h-0 flex-1 overflow-y-auto p-[1.25rem]">
                        <p class="mb-2">
                            ${isProxy
                                ? `You are submitting proxy information for the payout:<br><strong>${lname}, ${fname} ${mname}</strong><br>Relationship: <strong>${rel}</strong>`
                                : `You are confirming that <strong>you</strong> will personally attend the payout.`}
                        </p>
                        <div class="alert alert-warning small mb-0">
                            <strong>Important:</strong> You can only submit once.
                            Please make sure your information is <u>correct</u> before confirming.
                        </div>
                    </div>
                    <div class="flex shrink-0 items-center justify-end gap-2 border-t border-line bg-neutral-100 px-[1.25rem] py-[0.9rem]">
                        <button type="button" class="btn-subtle" @click="$store.unpaidConfirmationModal.close()">Cancel</button>
                        <button type="button" id="confirmYesBtn" class="btn-navy" @click="$store.unpaidConfirmationModal.confirm()">Yes, Confirm Submission</button>
                    </div>
                </div>
            </div>
        </div>`;
        document.body.appendChild(modal);
        Alpine.initTree(modal);
        Alpine.store('unpaidConfirmationModal').openFor(modal, function () {
            saveUnpaid(isProxy, lname, fname, mname, rel);
        });
    }

    function saveUnpaid(isProxy, lname = '', fname = '', mname = '', rel = '') {
        const muni = document.getElementById('municipalitySelect').value;
        const extras = window.proxyExtra || {};
        postForm(saveUrl, {
            client_id: selectedClientId,
            municipality_id: muni,
            is_proxy: isProxy ? 1 : 0,
            proxy_lastname: lname,
            proxy_firstname: fname,
            proxy_middlename: mname,
            proxy_relationship: rel,
            proxy_phone: extras.phone || '',
            proxy_birthdate: extras.birthdate || '',
            proxy_gender: extras.gender || '',
            proxy_occupation: extras.occ || '',
            proxy_monthlyincome: extras.income || ''
        }).then(data => {
            if (data.success) {
                document.getElementById('successBox').classList.remove('hidden');
                document.getElementById('successBox').textContent = data.message;
                document.getElementById('confirmSection').classList.add('hidden');
                document.getElementById('proxyForm').classList.add('hidden');
            } else {
                alert(data.message || 'Error saving information.');
            }
        });
    }
</script>
</body>
</html>
