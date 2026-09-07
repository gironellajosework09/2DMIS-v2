@php
    $client = $client ?? null;
    $barangays = $barangays ?? collect();
    $affOrgs = $affOrgs ?? [];
    $action = $action ?? route('clients.store');
    $method = $method ?? 'POST';
    $panel = $panel ?? false;
    $modal = $modal ?? false;
    $isEdit = filled($client);
    $primaryLabel = $isEdit ? 'Save Client' : 'Add Client';
@endphp

{{-- Batch E migration + Clients UX refinement: layout scaffolding uses the
     token vocabulary. Functional hooks preserved verbatim for the inline
     script below and the modal AJAX handler: element ids (municipality,
     barangay, birthdate, age, category, ipSelect, ipGroupDiv,
     aff-org-wrapper), the input.uppercase casing hook, the d-none toggle
     on #ipGroupDiv, all field names / old() bindings / validation error
     targets.

     Modal mode ($modal = true): rendered inside the Add/Edit client modal
     (clients.index > #clientFormModalBody). No native alert()/confirm() —
     duplicate handling is modal-native in the index script. Footer becomes
     Cancel (secondary) + gold primary (Add Client / Save Client).
     Panel mode ($panel = true): retained for legacy compatibility (the
     details panel is now view-only, so it is no longer the default). --}}
<form method="POST" action="{{ $action }}" @if($modal) id="clientForm" @endif @if($panel) data-panel-edit-form @endif novalidate>
    @csrf
    @method($method)

    @if ($modal)
        <div class="form-errors" role="alert">
            @if ($errors->any())
                <div class="mb-4 p-3 rounded-panel bg-danger/10 text-danger">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endif

    @if ($panel)
        <div class="panel-form-errors" role="alert"></div>
    @endif

    <div class="flex flex-col gap-[18px]">

        {{-- H. PROFILE PHOTO (edit modal only — sits ABOVE the identity/name
             fields in a dedicated photo container. The file input is carried on
             the same update form but ignored by clients.update; the index modal
             handler posts it to clients.photo.store after a successful save so
             the avatar can be set in the same pass.). The layout uses a
             dedicated photo container (not an inline image beside the fields). --}}
        @if ($isEdit && $modal)
            @php
                $photoRecord = $client->currentPhoto();
                $photoUrl = $photoRecord
                    ? asset('storage/uploads/client_photos/'.$photoRecord->photo_path)
                    : null;
            @endphp
            <section class="client-form-group" aria-labelledby="grp-photo">
                <h4 id="grp-photo" class="client-form-group-title">Profile Photo</h4>
                <div class="edit-photo-container">
                    <div class="edit-photo-frame">
                        <img id="photoCurrentPreview" class="edit-photo-preview"
                            src="{{ $photoUrl ?? '' }}"
                            @if (! $photoUrl) style="display:none" @endif
                            alt="Current profile photo">
                        <span id="photoPlaceholder" class="edit-photo-placeholder"
                            @if ($photoUrl) style="display:none" @endif
                        >No photo</span>
                    </div>
                    <div class="edit-photo-controls">
                        <label class="field-label" for="photo">Choose a new photo (JPG, PNG, or GIF &middot; max 1MB)</label>
                        <input type="file" name="photo" id="photo" class="form-control" accept="image/*">
                        <small id="photoFileError" class="field-hint text-danger" style="display:none"></small>
                        @if (! $client->photos->isEmpty())
                            <small class="field-hint text-ink-muted">A photo is already set — uploading a new one replaces it.</small>
                        @else
                            <small class="field-hint text-ink-muted">No photo set yet.</small>
                        @endif
                    </div>
                </div>
            </section>
            <script>
                (function () {
                    var input = document.getElementById('photo');
                    if (!input) return;
                    var errEl = document.getElementById('photoFileError');
                    function validate() {
                        var f = input.files && input.files[0];
                        if (!f) { errEl.style.display = 'none'; return true; }
                        var allowed = ['image/jpeg', 'image/png', 'image/gif'];
                        if (allowed.indexOf(f.type) === -1) {
                            errEl.textContent = 'Only JPG, PNG, or GIF images are allowed.';
                            errEl.style.display = 'block';
                            return false;
                        }
                        if (f.size > 1024 * 1024) {
                            errEl.textContent = 'Photo must be 1MB or smaller.';
                            errEl.style.display = 'block';
                            return false;
                        }
                        errEl.style.display = 'none';
                        return true;
                    }
                    input.addEventListener('change', validate);
                    input.form.addEventListener('submit', function (e) {
                        if (!validate()) e.preventDefault();
                    });
                })();
            </script>
        @endif

        {{-- A. PERSONAL INFORMATION --}}
        <section class="client-form-group" aria-labelledby="grp-personal">
            <h4 id="grp-personal" class="client-form-group-title">Personal Information</h4>
            <div class="grid grid-cols-1 gap-x-[16px] gap-y-[12px] sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label class="field-label" for="lastname">Last Name <span class="text-danger">*</span></label>
                    <input type="text" name="lastname" id="lastname" class="form-control uppercase"
                           value="{{ old('lastname', $client?->lastname ?? '') }}">
                    @error('lastname')<small class="field-error">{{ $message }}</small>@enderror
                </div>
                <div>
                    <label class="field-label" for="firstname">First Name <span class="text-danger">*</span></label>
                    <input type="text" name="firstname" id="firstname" class="form-control uppercase"
                           value="{{ old('firstname', $client?->firstname ?? '') }}">
                    @error('firstname')<small class="field-error">{{ $message }}</small>@enderror
                </div>
                <div>
                    <label class="field-label" for="middlename">Middle Name</label>
                    <input type="text" name="middlename" id="middlename" class="form-control uppercase"
                           value="{{ old('middlename', $client?->middlename ?? '') }}">
                </div>
                <div>
                    <label class="field-label" for="extensionname">Extension Name</label>
                    <input type="text" name="extensionname" id="extensionname" class="form-control uppercase"
                           value="{{ old('extensionname', $client?->extensionname ?? '') }}">
                </div>
            </div>
        </section>

        {{-- B. ADDRESS --}}
        <section class="client-form-group" aria-labelledby="grp-address">
            <h4 id="grp-address" class="client-form-group-title">Address</h4>
            <div class="grid grid-cols-1 gap-x-[16px] gap-y-[12px] sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label class="field-label">Region</label>
                    <input type="text" class="form-control" value="{{ \App\Services\ClientService::REGION }}" readonly>
                </div>
                <div>
                    <label class="field-label">Province</label>
                    <input type="text" class="form-control" value="{{ \App\Services\ClientService::PROVINCE }}" readonly>
                </div>
                <div>
                    <label class="field-label" for="municipality">Municipality <span class="text-danger">*</span></label>
                    <select name="city_municipality" id="municipality" class="form-select">
                        <option value="">-- Select Municipality --</option>
                        @foreach ($municipalities as $municipality)
                            <option value="{{ $municipality->id }}"
                                @selected(old('city_municipality', $client?->city_municipality) == $municipality->id)>
                                {{ $municipality->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('city_municipality')<small class="field-error">{{ $message }}</small>@enderror
                </div>
                <div>
                    <label class="field-label" for="barangay">Barangay <span class="text-danger">*</span></label>
                    <select name="barangay" id="barangay" class="form-select">
                        <option value="">-- Select Barangay --</option>
                        @foreach ($barangays as $barangay)
                            <option value="{{ $barangay->id }}"
                                @selected(old('barangay', $client?->barangay) == $barangay->id)>
                                {{ $barangay->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('barangay')<small class="field-error">{{ $message }}</small>@enderror
                </div>
                <div>
                    <label class="field-label" for="house_no">House No.</label>
                    <input type="text" name="house_no" id="house_no" class="form-control uppercase"
                           value="{{ old('house_no', $client?->house_no ?? '') }}">
                </div>
            </div>
        </section>

        {{-- C. CONTACT INFORMATION --}}
        <section class="client-form-group" aria-labelledby="grp-contact">
            <h4 id="grp-contact" class="client-form-group-title">Contact Information</h4>
            <div class="grid grid-cols-1 gap-x-[16px] gap-y-[12px] sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label class="field-label" for="mobile_no">Mobile No.</label>
                    <input type="text" name="mobile_no" id="mobile_no" class="form-control"
                           value="{{ old('mobile_no', $client?->mobile_no ?? '') }}">
                </div>
                <div>
                    <label class="field-label" for="email">Email</label>
                    <input type="email" name="email" id="email" class="form-control"
                           value="{{ old('email', $client?->email ?? '') }}">
                </div>
            </div>
        </section>

        {{-- D. PERSONAL DETAILS --}}
        <section class="client-form-group" aria-labelledby="grp-personal-details">
            <h4 id="grp-personal-details" class="client-form-group-title">Personal Details</h4>
            <div class="grid grid-cols-1 gap-x-[16px] gap-y-[12px] sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4">
                <div>
                    <label class="field-label" for="birthdate">Birthdate <span class="text-danger">*</span></label>
                    <input type="date" name="birthdate" id="birthdate" class="form-control"
                           value="{{ old('birthdate', $client?->birthdate ?? '') }}">
                    @error('birthdate')<small class="field-error">{{ $message }}</small>@enderror
                </div>
                <div>
                    <label class="field-label" for="age">Age</label>
                    <input type="number" name="age" id="age" class="form-control" readonly>
                </div>
                <div>
                    <label class="field-label" for="sex">Gender <span class="text-danger">*</span></label>
                    <select name="sex" id="sex" class="form-select">
                        <option value="">--Select--</option>
                        <option value="MALE" @selected(old('sex', $client?->sex) === 'MALE')>MALE</option>
                        <option value="FEMALE" @selected(old('sex', $client?->sex) === 'FEMALE')>FEMALE</option>
                    </select>
                    @error('sex')<small class="field-error">{{ $message }}</small>@enderror
                </div>
                <div>
                    <label class="field-label" for="civil_status">Civil Status <span class="text-danger">*</span></label>
                    <select name="civil_status" id="civil_status" class="form-select">
                        <option value="">--Select--</option>
                        <option value="SINGLE" @selected(old('civil_status', $client?->civil_status) === 'SINGLE')>SINGLE</option>
                        <option value="MARRIED" @selected(old('civil_status', $client?->civil_status) === 'MARRIED')>MARRIED</option>
                        <option value="WIDOWED" @selected(old('civil_status', $client?->civil_status) === 'WIDOWED')>WIDOWED</option>
                    </select>
                    @error('civil_status')<small class="field-error">{{ $message }}</small>@enderror
                </div>
            </div>
        </section>

        {{-- E. ADDITIONAL INFORMATION --}}
        <section class="client-form-group" aria-labelledby="grp-additional">
            <h4 id="grp-additional" class="client-form-group-title">Additional Information</h4>
            <div class="grid grid-cols-1 gap-x-[16px] gap-y-[12px] sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4">
                <div>
                    <label class="field-label" for="pwd">PWD</label>
                    <select name="pwd" id="pwd" class="form-select">
                        <option value="NO" @selected(old('pwd', $client?->pwd ?? 'NO') === 'NO')>NO</option>
                        <option value="YES" @selected(old('pwd', $client?->pwd) === 'YES')>YES</option>
                    </select>
                </div>
                <div>
                    <label class="field-label" for="ipSelect">IP</label>
                    <select name="ip" id="ipSelect" class="form-select">
                        <option value="NO" @selected(old('ip', $client?->ip ?? 'NO') === 'NO')>NO</option>
                        <option value="YES" @selected(old('ip', $client?->ip) === 'YES')>YES</option>
                    </select>
                </div>
                <div id="ipGroupDiv" @if (old('ip', $client?->ip ?? 'NO') !== 'YES') class="d-none" @endif>
                    <label class="field-label" for="ip_group">IP Group</label>
                    <select name="ip_group" id="ip_group" class="form-select">
                        <option value="">--Select Group--</option>
                        @foreach (['APPLAI', 'BAGO', 'BAGO-ITNEG', 'BAGO-KANKANAEY', 'BAGO-TINGUIAN', 'BONTOK', 'IBANAG', 'IGOROT', 'INLAUD-TINGGIAN', 'ITNEG', 'KANKANAEY', 'KANKANAEY-IBANAG', 'KANKANAEY-ITNEG', 'KANKANAEY-TINGUIAN', 'MARANAO', 'TINGUIAN', 'TINGUIAN-ITNEG'] as $group)
                            <option value="{{ $group }}" @selected(old('ip_group', $client?->ip_group) === $group)>{{ $group }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label" for="occupation">Occupation</label>
                    <input type="text" name="occupation" id="occupation" class="form-control uppercase"
                           value="{{ old('occupation', $client?->occupation ?? '') }}">
                </div>
                <div>
                    <label class="field-label" for="monthly_income">Monthly Income</label>
                    <input type="number" step="0.01" name="monthly_income" id="monthly_income" class="form-control"
                           value="{{ old('monthly_income', $client?->monthly_income ?? '') }}">
                </div>
            </div>
        </section>

        {{-- F. PROGRAMS & SERVICES (Affiliated Organizations multi-select) --}}
        {{-- Owners' clarification: the form's Programs & Services section is
             the Affiliated Organizations catalog (tbl_client_aff_orgs). The
             authoritative member list is the 7 v1 values below; the 4 category
             headings are a PRESENTATION-ONLY grouping (prototype visual
             language) — every option still submits as aff_org[] and persists
             through the existing syncAffiliations write path. No transaction
             program values are mixed in. --}}
        <section class="client-form-group" aria-labelledby="grp-programs">
            <h4 id="grp-programs" class="client-form-group-title">Programs &amp; Services</h4>
            <p class="mb-2 text-dense text-ink-muted">Select all that apply</p>
            @php
                $groupedOrganizations = [
                    'Livelihood & Employment' => ["FARMER'S ORGANIZATION", 'RIC'],
                    'Community Programs' => ['PUSO TI KABABAIHAN', 'PUSO TI MANNALON', 'PUSO TI AGTUTUBO', 'TALA', 'LCW'],
                ];
                $selectedOrgs = old('aff_org', $affOrgs ?: []);
            @endphp
            <div id="aff-org-wrapper" class="flex flex-col gap-[14px]">
                @foreach ($groupedOrganizations as $groupLabel => $organizations)
                    <div class="client-aff-group">
                        <p class="client-aff-group-title">{{ $groupLabel }}</p>
                        <div class="client-aff-grid">
                            @foreach ($organizations as $organization)
                                <label class="client-aff-check">
                                    <input type="checkbox" name="aff_org[]" value="{{ $organization }}"
                                           @checked(in_array($organization, $selectedOrgs, true))>
                                    <span>{{ $organization }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- G. REGISTRATION / IDENTIFICATION --}}
        <section class="client-form-group" aria-labelledby="grp-registration">
            <h4 id="grp-registration" class="client-form-group-title">Registration &amp; Identification</h4>
            <div class="grid grid-cols-1 gap-x-[16px] gap-y-[12px] sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="field-label" for="category">Category</label>
                    <input type="text" name="category" id="category" class="form-control"
                           value="{{ old('category', $client?->category ?? '') }}" readonly>
                    <small class="field-hint text-ink-muted">Auto-calculated from birthdate &mdash; not editable.</small>
                </div>
                <div>
                    <label class="field-label" for="precinct_no">Precinct No.</label>
                    <input type="text" name="precinct_no" id="precinct_no" class="form-control uppercase"
                           value="{{ old('precinct_no', $client?->precinct_no ?? '') }}">
                </div>
                <div>
                    <label class="field-label" for="voter_id">Voter's ID</label>
                    <input type="text" name="voter_id" id="voter_id" class="form-control uppercase"
                           value="{{ old('voter_id', $client?->voter_id ?? '') }}">
                </div>
            </div>
        </section>

        {{-- Footer is owned by the fixed modal-footer in modal mode (the
             submit is bound via form="clientForm"); full-page/panel modes
             render their own footer here. --}}
        @if (! $modal)
            <div class="flex items-center justify-end gap-[8px] border-t border-line-light pt-[1rem]">
                @if ($panel)
                    <button type="button" class="btn-subtle" data-edit-client-cancel="{{ $client->id }}">Cancel</button>
                    <button type="submit" class="btn-gold">Save Changes</button>
                @else
                    <a href="{{ route('clients.index') }}" class="btn-subtle no-underline">Cancel / Return</a>
                    <button type="submit" class="btn-gold">{{ $primaryLabel }}</button>
                @endif
            </div>
        @endif

    </div>
</form>

<script>
    (function () {
        'use strict';
        const MUNICIPALITY_SELECT = document.getElementById('municipality');
        const BARANGAY_SELECT = document.getElementById('barangay');
        const BIRTHDATE_INPUT = document.getElementById('birthdate');
        const AGE_INPUT = document.getElementById('age');
        const CATEGORY_INPUT = document.getElementById('category');
        const IP_SELECT = document.getElementById('ipSelect');
        const IP_GROUP_DIV = document.getElementById('ipGroupDiv');

        function barangayOption(b) {
            const option = document.createElement('option');
            option.value = b.id;
            option.textContent = b.name;
            return option;
        }

        function loadBarangays(municipalityId, target) {
            target.innerHTML = '<option value="">Loading...</option>';
            fetch('{{ route('geography.barangays') }}?municipality_id=' + municipalityId)
                .then(r => r.json())
                .then(data => {
                    target.innerHTML = '<option value="">-- Select Barangay --</option>';
                    data.forEach(b => {
                        target.appendChild(barangayOption(b));
                    });
                })
                .catch(() => {
                    target.innerHTML = '<option value="">Error loading barangays</option>';
                });
        }

        if (MUNICIPALITY_SELECT && BARANGAY_SELECT) {
            MUNICIPALITY_SELECT.addEventListener('change', function() {
                if (this.value) {
                    loadBarangays(this.value, BARANGAY_SELECT);
                } else {
                    BARANGAY_SELECT.innerHTML = '<option value="">-- Select Barangay --</option>';
                }
            });
        }

        if (BIRTHDATE_INPUT && AGE_INPUT && CATEGORY_INPUT) {
            BIRTHDATE_INPUT.addEventListener('change', function() {
                const birthdate = new Date(this.value);
                const today = new Date();
                let age = today.getFullYear() - birthdate.getFullYear();
                const monthDiff = today.getMonth() - birthdate.getMonth();
                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthdate.getDate())) {
                    age--;
                }

                AGE_INPUT.value = age >= 0 ? age : '';
                CATEGORY_INPUT.value = age <= 17 ? 'MINOR (0-17)'
                    : age <= 29 ? 'YOUTH (18-29)'
                    : age <= 59 ? 'ADULT (30-59)'
                    : age >= 60 ? 'SENIOR CITIZEN (60 AND ABOVE)'
                    : '';
            });
        }

        if (IP_SELECT && IP_GROUP_DIV) {
            IP_SELECT.addEventListener('change', function() {
                if (this.value === 'YES') {
                    IP_GROUP_DIV.classList.remove('d-none');
                } else {
                    IP_GROUP_DIV.classList.add('d-none');
                    IP_GROUP_DIV.querySelector('select').value = '';
                }
            });
        }

        document.querySelectorAll('input.uppercase').forEach(function(input) {
            input.addEventListener('input', function() {
                this.value = this.value.toUpperCase();
            });
        });
    })();
</script>
