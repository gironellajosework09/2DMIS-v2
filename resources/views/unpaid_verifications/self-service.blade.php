<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Unpaid Verification — 2D MIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f7f9fb;
            font-family: system-ui, Segoe UI, Roboto, Helvetica, Arial;
            padding: 24px;
        }

        .card {
            max-width: 600px;
            margin: 0 auto;
        }

        .suggestions-list {
            position: absolute;
            z-index: 2000;
            width: 100%;
            background: #fff;
            border: 1px solid #ccc;
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

        .uppercase {
            text-transform: uppercase;
        }
    </style>
</head>
<body>

<div class="card shadow-sm p-4">
    <h3 class="mb-3 text-center">Unpaid Verification</h3>

    <div class="mb-3">
        <label class="form-label">Search your name</label>
        <div class="position-relative">
            <input id="nameInput" class="form-control uppercase" placeholder="Type your full name" autocomplete="off">
            <div id="suggestList" class="suggestions-list d-none"></div>
        </div>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-md-6">
            <label class="form-label">Municipality</label>
            <select id="municipalitySelect" class="form-select">
                <option value="">-- Select Municipality --</option>
            </select>
        </div>
        <div class="col-md-6 d-flex align-items-end">
            <button id="verifyBtn" class="btn btn-primary w-100" disabled>Verify</button>
        </div>
    </div>

    <div id="alertBox"></div>

    <div id="confirmSection" class="d-none mt-4">
        <hr>
        <h5 class="text-center mb-3">Who will attend the payout?</h5>
        <div class="d-flex justify-content-center gap-3 mb-3">
            <button id="btnSelf" class="btn btn-success">I will come personally</button>
            <button id="btnProxy" class="btn btn-warning">Proxy</button>
        </div>
    </div>

    <div id="proxyForm" class="d-none">
        <h6 class="mt-3">Proxy Information</h6>
        <div class="mb-2"><input id="proxyLastname" class="form-control uppercase" placeholder="Lastname"></div>
        <div class="mb-2"><input id="proxyFirstname" class="form-control uppercase" placeholder="Firstname"></div>
        <div class="mb-2"><input id="proxyMiddlename" class="form-control uppercase" placeholder="Middlename"></div>
        <div class="mb-2"><input id="proxyRelationship" class="form-control uppercase" placeholder="Relationship"></div>
        <div class="mb-2"><input id="proxyPhone" class="form-control" placeholder="Contact Number"></div>
        <div class="mb-2">
            <label class="form-label small mb-1">Birthdate</label>
            <input type="date" id="proxyBirthdate" class="form-control">
        </div>
        <div class="mb-2">
            <label class="form-label small mb-1">Gender</label>
            <select id="proxyGender" class="form-select">
                <option value="">-- Select Gender --</option>
                <option>Male</option>
                <option>Female</option>
            </select>
        </div>
        <div class="mb-2"><input id="proxyOccupation" class="form-control uppercase" placeholder="Occupation"></div>
        <div class="mb-3"><input id="proxyMonthlyIncome" class="form-control uppercase" placeholder="Monthly Income"></div>
        <button id="submitProxyBtn" class="btn btn-primary w-100">Submit Proxy Info</button>
    </div>

    <div id="successBox" class="alert alert-success d-none mt-4 text-center"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
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
            suggestList.classList.add('d-none');
            return;
        }
        debounce = setTimeout(() => {
            fetch(searchUrl + '?q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(data => {
                    if (!data.success || !data.results.length) {
                        suggestList.innerHTML = '<div class="p-2">No matches</div>';
                        suggestList.classList.remove('d-none');
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
                            suggestList.classList.add('d-none');
                            document.getElementById('verifyBtn').disabled = false;
                        };
                        suggestList.appendChild(btn);
                    });
                    suggestList.classList.remove('d-none');
                });
        }, 250);
    });

    document.addEventListener('click', e => {
        if (!document.querySelector('.position-relative').contains(e.target)) {
            suggestList.classList.add('d-none');
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
            document.getElementById('confirmSection').classList.remove('d-none');
        });
    });

    document.getElementById('btnSelf').addEventListener('click', () => {
        showConfirmation(false);
    });

    document.getElementById('btnProxy').addEventListener('click', () => {
        document.getElementById('proxyForm').classList.remove('d-none');
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
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = `
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Final Confirmation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
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
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="confirmYesBtn" class="btn btn-primary">Yes, Confirm Submission</button>
                </div>
            </div>
        </div>`;
        document.body.appendChild(modal);
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();

        modal.querySelector('#confirmYesBtn').addEventListener('click', () => {
            bsModal.hide();
            modal.remove();
            saveUnpaid(isProxy, lname, fname, mname, rel);
        });

        modal.addEventListener('hidden.bs.modal', () => modal.remove());
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
                document.getElementById('successBox').classList.remove('d-none');
                document.getElementById('successBox').textContent = data.message;
                document.getElementById('confirmSection').classList.add('d-none');
                document.getElementById('proxyForm').classList.add('d-none');
            } else {
                alert(data.message || 'Error saving information.');
            }
        });
    }
</script>
</body>
</html>
