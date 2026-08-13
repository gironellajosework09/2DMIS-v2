@php($yearStarted = $scholar->year_started ?? '')
@php($yearStartVal = $yearStarted !== '' ? explode(' - ', $yearStarted)[0] : '')
@php($yearEndVal = $yearStarted !== '' && str_contains($yearStarted, ' - ') ? explode(' - ', $yearStarted, 2)[1] : '')
@php($selectedClientId = $scholar->client_id ?? ($clientId ?? ''))
@php($selectedClientName = $selectedClientId !== '' ? (\App\Models\Client::find($selectedClientId)->full_name ?? '') : '')
<div class="row">
    <div class="col-md-6 mb-3 position-relative">
        <label for="client_search" class="form-label">Client <span class="text-danger">*</span></label>
        <input type="text" name="client_search" id="client_search" class="form-control" placeholder="Search client by name..." value="{{ old('client_search', $selectedClientName) }}" autocomplete="off">
        <input type="hidden" name="client_id" id="client_id" value="{{ old('client_id', $selectedClientId) }}">
        <ul id="client_search_results" class="list-group position-absolute w-100 bg-white border" style="max-height:150px; overflow-y:auto; z-index:1000;"></ul>
    </div>
    <div class="col-md-6 mb-3">
        <label for="program" class="form-label">Program</label>
        <select name="program" id="program" class="form-control" required>
            @foreach(['CEDSSG', 'CEAP', 'CEDSSG_NEW', 'CEAP_NEW', 'OTEA', 'OTCES'] as $prog)
                <option value="{{ $prog }}" {{ (isset($scholar) && $scholar->program == $prog) ? 'selected' : '' }}>{{ $prog }}</option>
            @endforeach
        </select>
    </div>
</div>

<div class="row">
    <div class="col-md-4 mb-3">
        <label for="school" class="form-label">School</label>
        <input type="text" name="school" id="school" class="form-control" value="{{ old('school', $scholar->school ?? '') }}">
    </div>
    <div class="col-md-4 mb-3">
        <label for="school_type" class="form-label">School Type</label>
        <input type="text" name="school_type" id="school_type" class="form-control" value="{{ old('school_type', $scholar->school_type ?? '') }}">
    </div>
    <div class="col-md-4 mb-3">
        <label for="campus" class="form-label">Campus</label>
        <input type="text" name="campus" id="campus" class="form-control" value="{{ old('campus', $scholar->campus ?? '') }}">
    </div>
</div>

<div class="row">
    <div class="col-md-4 mb-3">
        <label for="college_department" class="form-label">College/Department</label>
        <input type="text" name="college_department" id="college_department" class="form-control" value="{{ old('college_department', $scholar->college_department ?? '') }}">
    </div>
    <div class="col-md-4 mb-3">
        <label for="course" class="form-label">Course</label>
        <input type="text" name="course" id="course" class="form-control" value="{{ old('course', $scholar->course ?? '') }}">
    </div>
    <div class="col-md-4 mb-3">
        <label for="year_level" class="form-label">Year Level</label>
        <input type="text" name="year_level" id="year_level" class="form-control" value="{{ old('year_level', $scholar->year_level ?? '') }}">
    </div>
</div>

<div class="row">
    <div class="col-md-4 mb-3">
        <label for="year_start" class="form-label">Year Started</label>
        <div class="input-group">
            <input type="text" name="year_start" id="year_start" class="form-control" placeholder="e.g. 2025" maxlength="4" pattern="\d{4}" value="{{ old('year_start', $yearStartVal) }}">
            <span class="input-group-text">-</span>
            <input type="text" name="year_end" id="year_end" class="form-control" placeholder="e.g. 2026" maxlength="4" pattern="\d{4}" value="{{ old('year_end', $yearEndVal) }}">
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <label for="landbank_no" class="form-label">Landbank No</label>
        <input type="text" name="landbank_no" id="landbank_no" class="form-control" value="{{ old('landbank_no', $scholar->landbank_no ?? '') }}">
    </div>
    <div class="col-md-4 mb-3">
        <label for="is_regular" class="form-label">Regular / Irregular</label>
        <select name="is_regular" id="is_regular" class="form-control">
            <option value="1" {{ (isset($scholar) && $scholar->is_regular == 1) ? 'selected' : '' }}>REGULAR</option>
            <option value="0" {{ (isset($scholar) && $scholar->is_regular == 0) ? 'selected' : '' }}>IRREGULAR</option>
        </select>
    </div>
</div>

@push('scripts')
    <script>
        const clientSearch = document.getElementById('client_search');
        const clientIdHidden = document.getElementById('client_id');
        const clientResults = document.getElementById('client_search_results');

        clientSearch.addEventListener('input', () => {
            const val = clientSearch.value.trim();
            clientResults.innerHTML = '';
            if (val.length < 2) return;

            fetch('{{ route('scholars.clients-search') }}?q=' + encodeURIComponent(val))
                .then(res => res.json())
                .then(data => {
                    data.forEach(c => {
                        const fullName = c.lastname + ', ' + c.firstname + ' ' + (c.middlename ?? '') + ' ' + (c.extensionname ?? '');
                        const li = document.createElement('li');
                        li.classList.add('list-group-item', 'list-group-item-action');
                        li.textContent = fullName.trim();
                        li.style.cursor = 'pointer';
                        li.addEventListener('click', () => {
                            clientSearch.value = fullName.trim();
                            clientIdHidden.value = c.id;
                            clientResults.innerHTML = '';
                        });
                        clientResults.appendChild(li);
                    });
                });
        });

        document.addEventListener('click', (e) => {
            if (!clientResults.contains(e.target) && e.target !== clientSearch) {
                clientResults.innerHTML = '';
            }
        });
    </script>
@endpush
