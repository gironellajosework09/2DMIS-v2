{{-- Grantee Self-Update tab partial (adapted for authenticated scholars module) --}}
<div class="data-card" aria-label="Grantee self-update">
    <div class="data-card-body">
        <h2 class="mb-3 text-lg font-semibold text-ink">Scholarship Grantee Self-Update</h2>
        <p class="text-dense text-ink-muted mb-4">
            Update your scholarship details. Changes will be reviewed by our office before being applied.
        </p>

        <div class="mb-3">
            <label for="selfNameInput" class="field-label">Search your name</label>
            <div class="position-relative">
                <input id="selfNameInput" class="form-control uppercase" placeholder="TYPE YOUR FULL NAME..." autocomplete="off">
                <div id="selfSuggestList" class="suggestions-list d-none"></div>
            </div>
        </div>

        <div class="mb-3 d-none" id="selfMobileVerifyWrap">
            <label for="selfMobileVerifyInput" class="field-label">Enter your registered Mobile Number (first verification)</label>
            <div class="input-group">
                <input id="selfMobileVerifyInput" class="form-control" placeholder="e.g. 09XXXXXXXXX" maxlength="11" disabled>
                <button id="selfMobileVerifyBtn" class="btn-subtle" type="button" disabled>Verify Mobile No.</button>
            </div>
            <div id="selfMobileVerifyMsg" class="mt-2 text-dense"></div>
            <a href="#" id="selfForgotMobileLink" class="text-dense no-underline" style="color: var(--color-red);">
                Forgot your registered mobile number?
            </a>
        </div>

        <div class="row g-2 mb-3">
            <div class="col-md-6">
                <label for="selfMunicipalitySelect" class="field-label">Municipality (for verification)</label>
                <select id="selfMunicipalitySelect" class="form-select" required>
                    <option value="">-- Select Municipality --</option>
                </select>
            </div>
            <div class="col-md-6 d-flex align-items-end">
                <button id="selfVerifyBtn" class="btn-navy w-full" disabled>Verify & Load My Details</button>
            </div>
        </div>

        <div id="selfAlertBox"></div>

        <div id="selfUpdateFormWrap" class="d-none">
            <hr>
            <h2 class="text-base font-semibold text-ink">Personal Details <span class="text-dense" style="font-size: 15px; color: var(--color-red);">(Leave it BLANK if not applicable)</span></h2>
            <form id="selfUpdateForm">
                <input type="hidden" name="client_id" id="selfClientId">

                <div class="row">
                    <div class="col-md-3 mb-2">
                        <label for="selfLastname" class="form-label">Last name</label>
                        <input name="lastname" id="selfLastname" class="form-control uppercase" readonly>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label for="selfFirstname" class="form-label">First name</label>
                        <input name="firstname" id="selfFirstname" class="form-control uppercase" readonly>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label for="selfMiddlename" class="form-label">Middle name</label>
                        <input name="middlename" id="selfMiddlename" class="form-control uppercase" readonly>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label for="selfExtensionname" class="form-label">Extension name</label>
                        <input name="extensionname" id="selfExtensionname" class="form-control uppercase" readonly>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-2">
                        <label for="selfCityMunicipality" class="form-label">Municipality</label>
                        <select name="city_municipality" id="selfCityMunicipality" class="form-select" disabled></select>
                    </div>
                    <div class="col-md-4 mb-2">
                        <label for="selfBarangay" class="form-label">Barangay</label>
                        <select name="barangay" id="selfBarangay" class="form-select" disabled></select>
                    </div>
                    <div class="col-md-4 mb-2">
                        <label for="selfHouseNo" class="form-label">House No.</label>
                        <input name="house_no" id="selfHouseNo" class="form-control uppercase">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3 mb-2">
                        <label for="selfMobileNo" class="form-label">Mobile No. <span class="text-danger">*</span></label>
                        <input name="mobile_no" id="selfMobileNo" class="form-control" required>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label for="selfEmail" class="form-label">Email <span class="text-danger">*</span></label>
                        <input name="email" id="selfEmail" class="form-control" required>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label for="selfBirthdate" class="form-label">Birthdate <span class="text-danger">*</span></label>
                        <input type="date" name="birthdate" id="selfBirthdate" class="form-control" required>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label for="selfAge" class="form-label">Age</label>
                        <input type="number" name="age" id="selfAge" readonly class="form-control">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3 mb-2">
                        <label for="selfSex" class="form-label">Sex <span class="text-danger">*</span></label>
                        <select name="sex" id="selfSex" class="form-select" required>
                            <option value="">--Select--</option>
                            <option value="MALE">MALE</option>
                            <option value="FEMALE">FEMALE</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label for="selfCivilStatus" class="form-label">Civil Status <span class="text-danger">*</span></label>
                        <select name="civil_status" id="selfCivilStatus" class="form-select" required>
                            <option value="">--Select--</option>
                            <option value="SINGLE">SINGLE</option>
                            <option value="MARRIED">MARRIED</option>
                            <option value="WIDOWED">WIDOWED</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label for="selfPwd" class="form-label">PWD <span class="text-danger">*</span></label>
                        <select name="pwd" id="selfPwd" class="form-select" required>
                            <option value="">--Select--</option>
                            <option value="NO">NO</option>
                            <option value="YES">YES</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label for="selfIp" class="form-label">IP</label>
                        <input name="ip" id="selfIp" class="form-control uppercase">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-2">
                        <label for="selfIpGroup" class="form-label">IP Group</label>
                        <input name="ip_group" id="selfIpGroup" class="form-control uppercase">
                    </div>
                    <div class="col-md-6 mb-2">
                        <label for="selfOccupation" class="form-label">Occupation</label>
                        <input name="occupation" id="selfOccupation" class="form-control uppercase">
                    </div>
                </div>

                <hr>
                <h2 class="text-base font-semibold text-ink">Scholarship Details</h2>

                <div class="row">
                    <div class="col-md-4 mb-2">
                        <label for="selfSchProgram" class="form-label">Program</label>
                        <input name="sch_program" id="selfSchProgram" class="form-control" readonly>
                    </div>
                    <div class="col-md-4 mb-2">
                        <label for="selfSchool" class="form-label">School <span class="text-danger">*</span></label>
                        <input name="school" id="selfSchool" class="form-control uppercase" required>
                    </div>
                    <div class="col-md-4 mb-2">
                        <label for="selfCollegeDepartment" class="form-label">College Department <span class="text-danger">*</span></label>
                        <input name="college_department" id="selfCollegeDepartment" class="form-control uppercase" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-2">
                        <label for="selfCourse" class="form-label">Course <span class="text-danger">*</span></label>
                        <input name="course" id="selfCourse" class="form-control uppercase" required>
                    </div>
                    <div class="col-md-4 mb-2">
                        <label for="selfYearLevel" class="form-label">Year Level <span class="text-danger">*</span></label>
                        <select name="year_level" id="selfYearLevel" class="form-select" required>
                            <option value="">-- Select Year Level --</option>
                            <option value="1ST YEAR">1ST YEAR</option>
                            <option value="2ND YEAR">2ND YEAR</option>
                            <option value="3RD YEAR">3RD YEAR</option>
                            <option value="4TH YEAR">4TH YEAR</option>
                            <option value="5TH YEAR">5TH YEAR</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-2">
                        <label for="selfIsRegular" class="form-label">Is Regular <span class="text-danger">*</span></label>
                        <select name="is_regular" id="selfIsRegular" class="form-select" required>
                            <option value="0">NO</option>
                            <option value="1">YES</option>
                        </select>
                    </div>
                </div>

                <div class="mt-3 d-flex gap-2">
                    <button id="selfSaveBtn" class="btn-navy">Save Updates</button>
                    <button id="selfCancelBtn" type="button" class="btn-subtle">Cancel</button>
                </div>
                <div id="selfSaveMsg" class="mt-3"></div>
            </form>
        </div>
    </div>
</div>

<style>
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

<script>
(function () {
    const searchUrl = '{{ route('grantee-search', ['kind' => 'grantee']) }}';
    const verifyUrl = '{{ route('grantee-search.verify', ['kind' => 'grantee']) }}';
    const saveUrl = '{{ route('grantee-update.store') }}';
    const mobileVerifyUrl = '{{ route('grantee.verify-mobile') }}';
    const barangaysUrl = '{{ route('grantee.barangays') }}';
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    let selectedClientId = null;

    // Load municipalities
    fetch(searchUrl + '?munis=1').then(r => r.json()).then(data => {
        if (data.success) {
            const sel = document.getElementById('selfMunicipalitySelect');
            data.municipalities.forEach(m => {
                const o = document.createElement('option');
                o.value = m.id;
                o.textContent = m.name;
                sel.appendChild(o);
            });
        }
    });

    const nameInput = document.getElementById('selfNameInput');
    const suggestList = document.getElementById('selfSuggestList');
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

                            const wrap = document.getElementById('selfMobileVerifyWrap');
                            const mobileInput = document.getElementById('selfMobileVerifyInput');
                            const mobileBtn = document.getElementById('selfMobileVerifyBtn');
                            const msg = document.getElementById('selfMobileVerifyMsg');

                            wrap.classList.remove('d-none');
                            mobileInput.disabled = false;
                            mobileBtn.disabled = false;
                            msg.innerHTML = "<span class='text-info'>Please enter the registered mobile number for verification.</span>";

                            document.getElementById('selfMunicipalitySelect').disabled = true;
                            document.getElementById('selfVerifyBtn').disabled = true;
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

    document.getElementById('selfVerifyBtn').addEventListener('click', async () => {
        const municipalityId = document.getElementById('selfMunicipalitySelect').value;
        const alertBox = document.getElementById('selfAlertBox');
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

        document.getElementById('selfUpdateFormWrap').classList.remove('d-none');
        const c = data.client;
        const s = data.scholarship || {};
        document.getElementById('selfClientId').value = c.id;
        ['lastname', 'firstname', 'middlename', 'extensionname', 'house_no', 'mobile_no', 'email', 'birthdate', 'age', 'sex', 'civil_status', 'pwd', 'ip', 'ip_group', 'occupation']
            .forEach(k => {
                document.getElementById('self' + k.charAt(0).toUpperCase() + k.slice(1)).value = c[k] || '';
            });
        document.getElementById('selfCityMunicipality').value = c.city_municipality;
        loadSelfBarangays(c.city_municipality, c.barangay);
        document.getElementById('selfSchProgram').value = data.program || '';
        document.getElementById('selfSchool').value = s.school || '';
        document.getElementById('selfCourse').value = s.course || '';
        document.getElementById('selfCollegeDepartment').value = s.college_department || '';
        document.getElementById('selfYearLevel').value = s.year_level || '';
        document.getElementById('selfIsRegular').value = s.is_regular ? '1' : '0';
    });

    async function loadSelfBarangays(muni, selected = '') {
        const sel = document.getElementById('selfBarangay');
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

    document.getElementById('selfCityMunicipality').addEventListener('change', e => loadSelfBarangays(e.target.value));

    document.getElementById('selfBirthdate').addEventListener('change', function() {
        const d = new Date(this.value);
        if (!isNaN(d)) {
            const t = new Date();
            let age = t.getFullYear() - d.getFullYear();
            const m = t.getMonth() - d.getMonth();
            if (m < 0 || (m === 0 && t.getDate() < d.getDate())) age--;
            document.getElementById('selfAge').value = age;
        }
    });

    document.getElementById('selfCancelBtn').onclick = () => {
        document.getElementById('selfUpdateFormWrap').classList.add('d-none');
    };

    document.getElementById('selfUpdateForm').addEventListener('submit', async e => {
        e.preventDefault();
        const fd = new FormData(e.target);
        const saveBtn = document.getElementById('selfSaveBtn');
        saveBtn.disabled = true;
        const msg = document.getElementById('selfSaveMsg');
        msg.innerHTML = '';

        const resp = await fetch(saveUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken },
            body: fd
        });
        const data = await resp.json();

        if (data.success) {
            const lastname = (document.getElementById('selfLastname').value || '').trim().toUpperCase();
            const firstname = (document.getElementById('selfFirstname').value || '').trim().toUpperCase();
            const middlename = (document.getElementById('selfMiddlename').value || '').trim().toUpperCase();
            const fullName = (lastname + ', ' + firstname + (middlename ? ' ' + middlename : '')).replace(/\s+/g, ' ').trim();

            const size = '220x220';
            const qrURL = 'https://api.qrserver.com/v1/create-qr-code/?size=' + encodeURIComponent(size) + '&data=' + encodeURIComponent(fullName);
            const downloadName = fullName.replace(/\s+/g, '_') + '_qr.png';

            msg.innerHTML = `
                <div class="alert alert-success text-center">
                    <h5 class="mb-2">Your update has been saved successfully!</h5>
                    <p class="mb-2">Please take a screenshot of this QR Code.</p>
                    <img id="selfGeneratedQr" src="${qrURL}" alt="QR Code" class="img-thumbnail" width="220" height="220" style="display:block;margin:0 auto">
                    <div class="mt-2">
                        <a id="selfDownloadQr" class="btn btn-sm btn-outline-primary" href="${qrURL}" download="${downloadName}">Download QR</a>
                    </div>
                    <p class="mt-2 fw-bold">${fullName}</p>
                </div>
            `;

            const img = document.getElementById('selfGeneratedQr');
            img.onerror = function() {
                img.style.display = 'none';
                const dl = document.getElementById('selfDownloadQr');
                if (dl) dl.style.display = 'none';
                msg.querySelector('.alert').insertAdjacentHTML('beforeend', `<div class="mt-2 text-danger">QR generation failed — please copy this text instead: <br><strong>${fullName}</strong></div>`);
            };
        } else {
            msg.innerHTML = `<div class="alert alert-danger">${data.message || 'Save failed. Please try again.'}</div>`;
        }

        saveBtn.disabled = false;
    });

    document.getElementById('selfMobileVerifyBtn').addEventListener('click', () => {
        const mobile = document.getElementById('selfMobileVerifyInput').value.trim();
        const msg = document.getElementById('selfMobileVerifyMsg');

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
                    document.getElementById('selfMunicipalitySelect').disabled = false;
                    document.getElementById('selfVerifyBtn').disabled = false;
                    document.getElementById('selfMobileVerifyBtn').disabled = true;
                    document.getElementById('selfMobileVerifyInput').disabled = true;
                } else {
                    msg.innerHTML = "<span class='text-danger'>Mobile number does not match our records for this grantee.</span>";
                }
            })
            .catch(() => {
                msg.innerHTML = "<span class='text-danger'>Error verifying mobile number. Please try again.</span>";
            });
    });

    document.getElementById('selfForgotMobileLink').addEventListener('click', e => {
        e.preventDefault();
        const confirmBypass = confirm(
            "If you forgot your registered mobile number, you may continue, but please update your number in the form before submitting.\n\nProceed?"
        );
        if (confirmBypass) {
            const msg = document.getElementById('selfMobileVerifyMsg');
            msg.innerHTML = "<span class='text-warning'>Mobile number verification skipped. Please update your number in the form before submitting.</span>";

            document.getElementById('selfMunicipalitySelect').disabled = false;
            document.getElementById('selfVerifyBtn').disabled = false;
        }
    });
})();
</script>