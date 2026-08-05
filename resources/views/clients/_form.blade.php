@php
    $client = $client ?? null;
    $barangays = $barangays ?? collect();
    $affOrgs = $affOrgs ?? [];
    $action = $action ?? route('clients.store');
    $method = $method ?? 'POST';
@endphp

<form method="POST" action="{{ $action }}">
    @csrf
    @method($method)

    <div class="row">
        <div class="col-md-4 mb-3">
            <label>Last Name <span class="text-danger">*</span></label>
            <input type="text" name="lastname" class="form-control uppercase"
                   value="{{ old('lastname', $client?->lastname ?? '') }}">
            @error('lastname')<small class="text-danger">{{ $message }}</small>@enderror
        </div>
        <div class="col-md-4 mb-3">
            <label>First Name <span class="text-danger">*</span></label>
            <input type="text" name="firstname" class="form-control uppercase"
                   value="{{ old('firstname', $client?->firstname ?? '') }}">
            @error('firstname')<small class="text-danger">{{ $message }}</small>@enderror
        </div>
        <div class="col-md-4 mb-3">
            <label>Middle Name</label>
            <input type="text" name="middlename" class="form-control uppercase"
                   value="{{ old('middlename', $client?->middlename ?? '') }}">
        </div>
    </div>

    <div class="row">
        <div class="col-md-4 mb-3">
            <label>Extension Name</label>
            <input type="text" name="extensionname" class="form-control uppercase"
                   value="{{ old('extensionname', $client?->extensionname ?? '') }}">
        </div>
        <div class="col-md-4 mb-3">
            <label>Region</label>
            <input type="text" class="form-control" value="{{ \App\Services\ClientService::REGION }}" readonly>
        </div>
        <div class="col-md-4 mb-3">
            <label>Province</label>
            <input type="text" class="form-control" value="{{ \App\Services\ClientService::PROVINCE }}" readonly>
        </div>
    </div>

    <div class="row">
        <div class="col mb-3">
            <label>Municipality <span class="text-danger">*</span></label>
            <select name="city_municipality" id="municipality" class="form-select">
                <option value="">-- Select Municipality --</option>
                @foreach ($municipalities as $municipality)
                    <option value="{{ $municipality->id }}"
                        @selected(old('city_municipality', $client?->city_municipality) == $municipality->id)>
                        {{ $municipality->name }}
                    </option>
                @endforeach
            </select>
            @error('city_municipality')<small class="text-danger">{{ $message }}</small>@enderror
        </div>
        <div class="col mb-3">
            <label>Barangay <span class="text-danger">*</span></label>
            <select name="barangay" id="barangay" class="form-select">
                <option value="">-- Select Barangay --</option>
                @foreach ($barangays as $barangay)
                    <option value="{{ $barangay->id }}"
                        @selected(old('barangay', $client?->barangay) == $barangay->id)>
                        {{ $barangay->name }}
                    </option>
                @endforeach
            </select>
            @error('barangay')<small class="text-danger">{{ $message }}</small>@enderror
        </div>
        <div class="col mb-3">
            <label>House No.</label>
            <input type="text" name="house_no" class="form-control uppercase"
                   value="{{ old('house_no', $client?->house_no ?? '') }}">
        </div>
    </div>

    <div class="row">
        <div class="col-md-3 mb-3">
            <label>Mobile No.</label>
            <input type="text" name="mobile_no" class="form-control"
                   value="{{ old('mobile_no', $client?->mobile_no ?? '') }}">
        </div>
        <div class="col-md-3 mb-3">
            <label>Email</label>
            <input type="email" name="email" class="form-control"
                   value="{{ old('email', $client?->email ?? '') }}">
        </div>
        <div class="col-md-3 mb-3">
            <label>Birthdate <span class="text-danger">*</span></label>
            <input type="date" name="birthdate" id="birthdate" class="form-control"
                   value="{{ old('birthdate', $client?->birthdate ?? '') }}">
            @error('birthdate')<small class="text-danger">{{ $message }}</small>@enderror
        </div>
        <div class="col-md-3 mb-3">
            <label>Age</label>
            <input type="number" name="age" id="age" class="form-control" readonly>
        </div>
    </div>

    <div class="row">
        <div class="col mb-3">
            <label>Gender <span class="text-danger">*</span></label>
            <select name="sex" class="form-select">
                <option value="">--Select--</option>
                <option value="MALE" @selected(old('sex', $client?->sex) === 'MALE')>MALE</option>
                <option value="FEMALE" @selected(old('sex', $client?->sex) === 'FEMALE')>FEMALE</option>
            </select>
            @error('sex')<small class="text-danger">{{ $message }}</small>@enderror
        </div>
        <div class="col mb-3">
            <label>Civil Status <span class="text-danger">*</span></label>
            <select name="civil_status" class="form-select">
                <option value="">--Select--</option>
                <option value="SINGLE" @selected(old('civil_status', $client?->civil_status) === 'SINGLE')>SINGLE</option>
                <option value="MARRIED" @selected(old('civil_status', $client?->civil_status) === 'MARRIED')>MARRIED</option>
                <option value="WIDOWED" @selected(old('civil_status', $client?->civil_status) === 'WIDOWED')>WIDOWED</option>
            </select>
            @error('civil_status')<small class="text-danger">{{ $message }}</small>@enderror
        </div>
        <div class="col mb-3">
            <label>PWD</label>
            <select name="pwd" class="form-select">
                <option value="NO" @selected(old('pwd', $client?->pwd ?? 'NO') === 'NO')>NO</option>
                <option value="YES" @selected(old('pwd', $client?->pwd) === 'YES')>YES</option>
            </select>
        </div>
        <div class="col mb-3">
            <label>IP</label>
            <select name="ip" id="ipSelect" class="form-select">
                <option value="NO" @selected(old('ip', $client?->ip ?? 'NO') === 'NO')>NO</option>
                <option value="YES" @selected(old('ip', $client?->ip) === 'YES')>YES</option>
            </select>
        </div>
        <div class="col mb-3 @if (old('ip', $client?->ip ?? 'NO') !== 'YES') d-none @endif" id="ipGroupDiv">
            <label>IP Group</label>
            <select name="ip_group" class="form-select">
                <option value="">--Select Group--</option>
                @foreach (['APPLAI', 'BAGO', 'BAGO-ITNEG', 'BAGO-KANKANAEY', 'BAGO-TINGUIAN', 'BONTOK', 'IBANAG', 'IGOROT', 'INLAUD-TINGGIAN', 'ITNEG', 'KANKANAEY', 'KANKANAEY-IBANAG', 'KANKANAEY-ITNEG', 'KANKANAEY-TINGUIAN', 'MARANAO', 'TINGUIAN', 'TINGUIAN-ITNEG'] as $group)
                    <option value="{{ $group }}" @selected(old('ip_group', $client?->ip_group) === $group)>{{ $group }}</option>
                @endforeach
            </select>
        </div>
        <div class="col mb-3">
            <label>Occupation</label>
            <input type="text" name="occupation" class="form-control uppercase"
                   value="{{ old('occupation', $client?->occupation ?? '') }}">
        </div>
        <div class="col mb-3">
            <label>Monthly Income</label>
            <input type="number" step="0.01" name="monthly_income" class="form-control"
                   value="{{ old('monthly_income', $client?->monthly_income ?? '') }}">
        </div>
    </div>

    <div class="row">
        <div class="col-md-3 mb-3">
            <label>Category</label>
            <input type="text" name="category" id="category" class="form-control"
                   value="{{ old('category', $client?->category ?? '') }}" readonly>
        </div>
        <div class="col-md-3 mb-3">
            <label>Affiliated Organizations</label>
            <div id="aff-org-wrapper">
                @php($organizations = ['PUSO TI KABABAIHAN', 'PUSO TI MANNALON', 'PUSO TI AGTUTUBO', 'RIC', "FARMER'S ORGANIZATION", 'TALA', 'LCW'])
                @forelse ($affOrgs as $org)
                    <select name="aff_org[]" class="form-select mb-2">
                        <option value="">-- Select Organization --</option>
                        @foreach ($organizations as $organization)
                            <option value="{{ $organization }}" @selected($organization === $org)>{{ $organization }}</option>
                        @endforeach
                    </select>
                @empty
                    <select name="aff_org[]" class="form-select mb-2">
                        <option value="">-- Select Organization --</option>
                        @foreach ($organizations as $organization)
                            <option value="{{ $organization }}">{{ $organization }}</option>
                        @endforeach
                    </select>
                @endforelse
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addOrgField()">+ Add another</button>
        </div>
        <div class="col-md-3 mb-3">
            <label>Precinct No.</label>
            <input type="text" name="precinct_no" class="form-control uppercase"
                   value="{{ old('precinct_no', $client?->precinct_no ?? '') }}">
        </div>
        <div class="col-md-3 mb-3">
            <label>Voter's ID</label>
            <input type="text" name="voter_id" class="form-control uppercase"
                   value="{{ old('voter_id', $client?->voter_id ?? '') }}">
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2">
        <a href="{{ route('clients.index') }}" class="btn btn-secondary">Cancel / Return</a>
        <button type="submit" class="btn btn-primary">Save Client</button>
    </div>
</form>

@push('scripts')
    <script>
        const MUNICIPALITY_SELECT = document.getElementById('municipality');
        const BARANGAY_SELECT = document.getElementById('barangay');
        const BIRTHDATE_INPUT = document.getElementById('birthdate');
        const AGE_INPUT = document.getElementById('age');
        const CATEGORY_INPUT = document.getElementById('category');
        const IP_SELECT = document.getElementById('ipSelect');
        const IP_GROUP_DIV = document.getElementById('ipGroupDiv');
        const AFF_ORG_WRAPPER = document.getElementById('aff-org-wrapper');

        const ORGANIZATIONS = [
            'PUSO TI KABABAIHAN', 'PUSO TI MANNALON', 'PUSO TI AGTUTUBO', 'RIC',
            "FARMER'S ORGANIZATION", 'TALA', 'LCW'
        ];

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

        MUNICIPALITY_SELECT.addEventListener('change', function() {
            if (this.value) {
                loadBarangays(this.value, BARANGAY_SELECT);
            } else {
                BARANGAY_SELECT.innerHTML = '<option value="">-- Select Barangay --</option>';
            }
        });

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

        IP_SELECT.addEventListener('change', function() {
            if (this.value === 'YES') {
                IP_GROUP_DIV.classList.remove('d-none');
            } else {
                IP_GROUP_DIV.classList.add('d-none');
                IP_GROUP_DIV.querySelector('select').value = '';
            }
        });

        function addOrgField() {
            const selects = AFF_ORG_WRAPPER.querySelectorAll('select');
            if (selects.length >= 5) {
                alert('You can only select up to 5 affiliated organizations.');
                return;
            }
            const select = document.createElement('select');
            select.name = 'aff_org[]';
            select.className = 'form-select mb-2';
            select.innerHTML = '<option value="">-- Select Organization --</option>';
            ORGANIZATIONS.forEach(function(o) {
                const option = document.createElement('option');
                option.value = o;
                option.textContent = o;
                select.appendChild(option);
            });
            AFF_ORG_WRAPPER.appendChild(select);
        }

        document.querySelectorAll('input.uppercase').forEach(function(input) {
            input.addEventListener('input', function() {
                this.value = this.value.toUpperCase();
            });
        });
    </script>
@endpush
