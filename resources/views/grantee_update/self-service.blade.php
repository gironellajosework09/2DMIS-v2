<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Scholarship Grantee Update — 2D MIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f7f9fb;
            font-family: system-ui, Segoe UI, Roboto, Helvetica, Arial;
            padding: 24px;
        }

        .card {
            max-width: 1000px;
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
    <h3 class="mb-3 text-center">Scholarship Grantee Self-Update</h3>

    <div class="mb-3">
        <label class="form-label">Search your name</label>
        <div class="position-relative">
            <input id="nameInput" class="form-control uppercase" placeholder="TYPE YOUR FULL NAME..." autocomplete="off">
            <div id="suggestList" class="suggestions-list d-none"></div>
        </div>
    </div>

    <div class="mb-3 d-none" id="mobileVerifyWrap">
        <label class="form-label">Enter your registered Mobile Number (first verification)</label>
        <div class="input-group">
            <input id="mobileVerifyInput" class="form-control" placeholder="e.g. 09XXXXXXXXX" maxlength="11" disabled>
            <button id="mobileVerifyBtn" class="btn btn-outline-primary" type="button" disabled>Verify Mobile No.</button>
        </div>
        <div id="mobileVerifyMsg" class="mt-2 small"></div>
        <a href="#" id="forgotMobileLink" class="text-decoration-none small text-danger">
            Forgot your registered mobile number?
        </a>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-md-6">
            <label class="form-label">Municipality (for verification)</label>
            <select id="municipalitySelect" class="form-select" required>
                <option value="">-- Select Municipality --</option>
            </select>
        </div>
        <div class="col-md-6 d-flex align-items-end">
            <button id="verifyBtn" class="btn btn-primary w-100" disabled>Verify &amp; Load My Details</button>
        </div>
    </div>

    <div id="alertBox"></div>

    <div id="updateFormWrap" class="d-none">
        <hr>
        <h5>Personal Details <span style="font-size: 15px; color: red;">(Leave it BLANK if not applicable)</span></h5>
        <form id="updateForm">
            <input type="hidden" name="client_id" id="client_id">

            <div class="row">
                <div class="col-md-3 mb-2">
                    <label class="form-label">Last name</label>
                    <input name="lastname" id="lastname" class="form-control uppercase" readonly>
                </div>
                <div class="col-md-3 mb-2">
                    <label class="form-label">First name</label>
                    <input name="firstname" id="firstname" class="form-control uppercase" readonly>
                </div>
                <div class="col-md-3 mb-2">
                    <label class="form-label">Middle name</label>
                    <input name="middlename" id="middlename" class="form-control uppercase" readonly>
                </div>
                <div class="col-md-3 mb-2">
                    <label class="form-label">Extension name</label>
                    <input name="extensionname" id="extensionname" class="form-control uppercase" readonly>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-2">
                    <label class="form-label">Municipality</label>
                    <select name="city_municipality" id="city_municipality" class="form-select" disabled></select>
                </div>
                <div class="col-md-4 mb-2">
                    <label class="form-label">Barangay</label>
                    <select name="barangay" id="barangay" class="form-select" disabled></select>
                </div>
                <div class="col-md-4 mb-2">
                    <label class="form-label">House No.</label>
                    <input name="house_no" id="house_no" class="form-control uppercase">
                </div>
            </div>

            <div class="row">
                <div class="col-md-3 mb-2">
                    <label class="form-label">Mobile No. <span class="text-danger">*</span></label>
                    <input name="mobile_no" id="mobile_no" class="form-control" required>
                </div>
                <div class="col-md-3 mb-2">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <input name="email" id="email" class="form-control" required>
                </div>
                <div class="col-md-3 mb-2">
                    <label class="form-label">Birthdate <span class="text-danger">*</span></label>
                    <input type="date" name="birthdate" id="birthdate" class="form-control" required>
                </div>
                <div class="col-md-3 mb-2">
                    <label class="form-label">Age</label>
                    <input type="number" name="age" id="age" readonly class="form-control">
                </div>
            </div>

            <div class="row">
                <div class="col-md-3 mb-2">
                    <label class="form-label">Sex <span class="text-danger">*</span></label>
                    <select name="sex" id="sex" class="form-select" required>
                        <option value="">--Select--</option>
                        <option value="MALE">MALE</option>
                        <option value="FEMALE">FEMALE</option>
                    </select>
                </div>
                <div class="col-md-3 mb-2">
                    <label class="form-label">Civil Status <span class="text-danger">*</span></label>
                    <select name="civil_status" id="civil_status" class="form-select" required>
                        <option value="">--Select--</option>
                        <option value="SINGLE">SINGLE</option>
                        <option value="MARRIED">MARRIED</option>
                        <option value="WIDOWED">WIDOWED</option>
                    </select>
                </div>
                <div class="col-md-3 mb-2">
                    <label class="form-label">PWD <span class="text-danger">*</span></label>
                    <select name="pwd" id="pwd" class="form-select" required>
                        <option value="">--Select--</option>
                        <option value="NO">NO</option>
                        <option value="YES">YES</option>
                    </select>
                </div>
                <div class="col-md-3 mb-2">
                    <label class="form-label">IP</label>
                    <input name="ip" id="ip" class="form-control uppercase">
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-2">
                    <label class="form-label">IP Group</label>
                    <input name="ip_group" id="ip_group" class="form-control uppercase">
                </div>
                <div class="col-md-6 mb-2">
                    <label class="form-label">Occupation</label>
                    <input name="occupation" id="occupation" class="form-control uppercase">
                </div>
            </div>

            <hr>
            <h5>Scholarship Details</h5>

            <div class="row">
                <div class="col-md-4 mb-2">
                    <label class="form-label">Program</label>
                    <input name="sch_program" id="sch_program" class="form-control" readonly>
                </div>
                <div class="col-md-4 mb-2">
                    <label class="form-label">School <span class="text-danger">*</span></label>
                    <input name="school" id="school" class="form-control uppercase" required>
                </div>
                <div class="col-md-4 mb-2">
                    <label class="form-label">College Department <span class="text-danger">*</span></label>
                    <input name="college_department" id="college_department" class="form-control uppercase" required>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-2">
                    <label class="form-label">Course <span class="text-danger">*</span></label>
                    <input name="course" id="course" class="form-control uppercase" required>
                </div>
                <div class="col-md-4 mb-2">
                    <label class="form-label">Year Level <span class="text-danger">*</span></label>
                    <select name="year_level" id="year_level" class="form-select" required>
                        <option value="">-- Select Year Level --</option>
                        <option value="1ST YEAR">1ST YEAR</option>
                        <option value="2ND YEAR">2ND YEAR</option>
                        <option value="3RD YEAR">3RD YEAR</option>
                        <option value="4TH YEAR">4TH YEAR</option>
                        <option value="5TH YEAR">5TH YEAR</option>
                    </select>
                </div>
                <div class="col-md-4 mb-2">
                    <label class="form-label">Is Regular <span class="text-danger">*</span></label>
                    <select name="is_regular" id="is_regular" class="form-select" required>
                        <option value="0">NO</option>
                        <option value="1">YES</option>
                    </select>
                </div>
            </div>

            <div class="mt-3 d-flex gap-2">
                <button id="saveBtn" class="btn btn-success">Save Updates</button>
                <button id="cancelBtn" type="button" class="btn btn-secondary">Cancel</button>
            </div>
            <div id="saveMsg" class="mt-3"></div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const searchUrl = '{{ route('grantee-search', ['kind' => 'grantee']) }}';
    const verifyUrl = '{{ route('grantee-search.verify', ['kind' => 'grantee']) }}';
    const saveUrl = '{{ route('grantee-update.store') }}';
    const mobileVerifyUrl = '{{ route('grantee.verify-mobile') }}';
    const barangaysUrl = '{{ route('grantee.barangays') }}';
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    let selectedClientId = null;

    fetch(searchUrl + '?munis=1').then(r => r.json()).then(data => {
        if (data.success) {
            const sel1 = document.getElementById('municipalitySelect');
            const sel2 = document.getElementById('city_municipality');
            data.municipalities.forEach(m => {
                const o1 = document.createElement('option');
                o1.value = m.id;
                o1.textContent = m.name;
                const o2 = o1.cloneNode(true);
                sel1.appendChild(o1);
                sel2.appendChild(o2);
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
                        btn.innerHTML = '<strong>' + r.full_name.toUpperCase() + '</strong>';
                        btn.onclick = () => {
                            nameInput.value = r.full_name.toUpperCase();
                            selectedClientId = r.id;
                            suggestList.classList.add('d-none');

                            const wrap = document.getElementById('mobileVerifyWrap');
                            const mobileInput = document.getElementById('mobileVerifyInput');
                            const mobileBtn = document.getElementById('mobileVerifyBtn');
                            const msg = document.getElementById('mobileVerifyMsg');

                            wrap.classList.remove('d-none');
                            mobileInput.disabled = false;
                            mobileBtn.disabled = false;
                            msg.innerHTML = "<span class='text-info'>Please enter the registered mobile number for verification.</span>";

                            document.getElementById('municipalitySelect').disabled = true;
                            document.getElementById('verifyBtn').disabled = true;
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

    document.getElementById('verifyBtn').addEventListener('click', async () => {
        const municipalityId = document.getElementById('municipalitySelect').value;
        const alertBox = document.getElementById('alertBox');
        alertBox.innerHTML = '';
        if (!selectedClientId) {
            alertBox.innerHTML = '<div class="alert alert-danger">Select your name first.</div>';
            return;
        }
        if (!municipalityId) {
            alertBox.innerHTML = '<div class="alert alert-danger">Select your municipality for verification.</div>';
            return;
        }
        const resp = await fetch(verifyUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({
                action: 'verify',
                client_id: selectedClientId,
                municipality_id: municipalityId
            })
        });
        const data = await resp.json();
        if (!data.success) {
            alertBox.innerHTML = '<div class="alert alert-danger">' + (data.message || 'Verification failed') + '</div>';
            return;
        }

        document.getElementById('updateFormWrap').classList.remove('d-none');
        const c = data.client;
        const s = data.scholarship || {};
        document.getElementById('client_id').value = c.id;
        ['lastname', 'firstname', 'middlename', 'extensionname', 'house_no', 'mobile_no', 'email', 'birthdate', 'age', 'sex', 'civil_status', 'pwd', 'ip', 'ip_group', 'occupation']
            .forEach(k => {
                document.getElementById(k).value = c[k] || '';
            });
        document.getElementById('city_municipality').value = c.city_municipality;
        loadBarangays(c.city_municipality, c.barangay);
        document.getElementById('sch_program').value = data.program || '';
        document.getElementById('school').value = s.school || '';
        document.getElementById('course').value = s.course || '';
        document.getElementById('college_department').value = s.college_department || '';
        document.getElementById('year_level').value = s.year_level || '';
        document.getElementById('is_regular').value = s.is_regular ? '1' : '0';
    });

    async function loadBarangays(muni, selected = '') {
        const sel = document.getElementById('barangay');
        sel.innerHTML = '<option>Loading...</option>';
        const res = await fetch(barangaysUrl + '?municipality_id=' + muni);
        const data = await res.json();
        sel.innerHTML = '<option value="">-- Select Barangay --</option>';
        data.forEach(b => {
            const o = document.createElement('option');
            o.value = b.id;
            o.textContent = b.name;
            if (b.id == selected) o.selected = true;
            sel.appendChild(o);
        });
    }

    document.getElementById('city_municipality').addEventListener('change', e => loadBarangays(e.target.value));

    document.getElementById('birthdate').addEventListener('change', function() {
        const d = new Date(this.value);
        if (!isNaN(d)) {
            const t = new Date();
            let age = t.getFullYear() - d.getFullYear();
            const m = t.getMonth() - d.getMonth();
            if (m < 0 || (m === 0 && t.getDate() < d.getDate())) age--;
            document.getElementById('age').value = age;
        }
    });

    document.getElementById('cancelBtn').onclick = () => {
        document.getElementById('updateFormWrap').classList.add('d-none');
    };

    document.getElementById('updateForm').addEventListener('submit', async e => {
        e.preventDefault();
        const fd = new FormData(e.target);
        const saveBtn = document.getElementById('saveBtn');
        saveBtn.disabled = true;
        const msg = document.getElementById('saveMsg');
        msg.innerHTML = '';

        const resp = await fetch(saveUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken },
            body: fd
        });
        const data = await resp.json();

        if (data.success) {
            const lastname = (document.getElementById('lastname').value || '').trim().toUpperCase();
            const firstname = (document.getElementById('firstname').value || '').trim().toUpperCase();
            const middlename = (document.getElementById('middlename').value || '').trim().toUpperCase();
            const fullName = (lastname + ', ' + firstname + (middlename ? ' ' + middlename : '')).replace(/\s+/g, ' ').trim();

            const size = '220x220';
            const qrURL = 'https://api.qrserver.com/v1/create-qr-code/?size=' + encodeURIComponent(size) + '&data=' + encodeURIComponent(fullName);
            const downloadName = fullName.replace(/\s+/g, '_') + '_qr.png';

            msg.innerHTML = `
                <div class="alert alert-success text-center">
                    <h5 class="mb-2">Your update has been saved successfully!</h5>
                    <p class="mb-2">Please take a screenshot of this QR Code.</p>
                    <img id="generatedQr" src="${qrURL}" alt="QR Code" class="img-thumbnail" width="220" height="220" style="display:block;margin:0 auto">
                    <div class="mt-2">
                        <a id="downloadQr" class="btn btn-sm btn-outline-primary" href="${qrURL}" download="${downloadName}">Download QR</a>
                    </div>
                    <p class="mt-2 fw-bold">${fullName}</p>
                </div>
            `;

            const img = document.getElementById('generatedQr');
            img.onerror = function() {
                img.style.display = 'none';
                const dl = document.getElementById('downloadQr');
                if (dl) dl.style.display = 'none';
                msg.querySelector('.alert').insertAdjacentHTML('beforeend', `<div class="mt-2 text-danger">QR generation failed — please copy this text instead: <br><strong>${fullName}</strong></div>`);
            };
        } else {
            msg.innerHTML = `<div class="alert alert-danger">${data.message || 'Save failed. Please try again.'}</div>`;
        }

        saveBtn.disabled = false;
    });

    document.getElementById('mobileVerifyBtn').addEventListener('click', () => {
        const mobile = document.getElementById('mobileVerifyInput').value.trim();
        const msg = document.getElementById('mobileVerifyMsg');

        if (!selectedClientId) {
            msg.innerHTML = "<span class='text-danger'>Please select your name first.</span>";
            return;
        }

        if (!/^09\d{9}$/.test(mobile)) {
            msg.innerHTML = "<span class='text-danger'>Please enter a valid 11-digit mobile number (starts with 09).</span>";
            return;
        }

        fetch(mobileVerifyUrl + '?id=' + selectedClientId + '&mobile_no=' + encodeURIComponent(mobile))
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    if (data.skipped) {
                        msg.innerHTML = "<span class='text-warning'>No mobile number on file — you may continue by selecting your correct municipality, but please update your mobile number in the form.</span>";
                    } else {
                        msg.innerHTML = "<span class='text-success'>Mobile number verified successfully. You may now select your municipality.</span>";
                    }
                    document.getElementById('municipalitySelect').disabled = false;
                    document.getElementById('verifyBtn').disabled = false;
                    document.getElementById('mobileVerifyBtn').disabled = true;
                    document.getElementById('mobileVerifyInput').disabled = true;
                } else {
                    msg.innerHTML = "<span class='text-danger'>Mobile number does not match our records for this grantee.</span>";
                }
            })
            .catch(() => {
                msg.innerHTML = "<span class='text-danger'>Error verifying mobile number. Please try again.</span>";
            });
    });

    document.getElementById('forgotMobileLink').addEventListener('click', e => {
        e.preventDefault();
        const confirmBypass = confirm(
            "If you forgot your registered mobile number, you may continue, but please update your number in the form before submitting.\n\nProceed?"
        );
        if (confirmBypass) {
            const msg = document.getElementById('mobileVerifyMsg');
            msg.innerHTML = "<span class='text-warning'>Mobile number verification skipped. Please update your number in the form before submitting.</span>";

            document.getElementById('municipalitySelect').disabled = false;
            document.getElementById('verifyBtn').disabled = false;
        }
    });
</script>
</body>
</html>
