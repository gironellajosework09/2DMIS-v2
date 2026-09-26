@extends('layouts.app')

@section('title', 'Add Household — 2D MIS')

{{-- Batch G migration (UI_UX_ANALYSIS §8.9 Group 3 remainder): household
     creation. The client-search contract (debounced fetch to
     households.clients.search, detail load via households.index/clients/{id},
     hidden head_household binding, disabled-until-selected submit and the
     fieldset#clientDetails fill state) is byte-preserved; the result
     popover items are JS-built custom classes kept in the scoped CSS. --}}
@push('styles')
    <style>
        /* ── Household-create scope. Prefixed with #household-create-screen —
           nothing here can leak to other screens. ── */
        #household-create-screen #clientResultsList {
            position: absolute;
            z-index: 10;
            width: 100%;
            max-height: 240px;
            overflow-y: auto;
            background: var(--color-surface);
            border: 1px solid var(--color-line);
            border-radius: 0 0 var(--radius-control) var(--radius-control);
            box-shadow: var(--shadow-lift);
        }

        #household-create-screen #clientResultsList .result-item {
            padding: 0.5rem 0.75rem;
            cursor: pointer;
            font-size: 0.85rem;
        }

        #household-create-screen #clientResultsList .result-item:hover {
            background-color: var(--color-surface-hover);
        }

        #household-create-screen fieldset#clientDetails {
            opacity: 0.6;
            transition: opacity 0.15s ease;
        }

        #household-create-screen fieldset#clientDetails.filled {
            opacity: 1;
        }
    </style>
@endpush

@section('content')

    @include('partials.page-header', [
        'title' => 'Add New Household',
        'subtitle' => 'Search the head of household, then save the record.',
    ])

    <div id="household-create-screen">
        <section class="data-card" aria-label="Add household form">
            <div class="data-card-body">
                @if ($errors->any())
                    <div class="mb-[16px] flex items-start justify-between gap-2 rounded-[var(--radius-control)] border-l-[3px] border-[var(--color-red)] bg-[rgb(206_17_38_/_0.06)] p-[13px_16px] text-dense text-ink" role="alert" x-data="{ open: true }" x-show="open">
                        {{ $errors->first() }}
                        <button type="button" class="btn-close" @click="open = false" aria-label="Close"></button>
                    </div>
                @endif

                <div class="relative mb-[16px]" id="clientResults">
                    <label for="clientSearch" class="field-label">Search Head of Household <span class="text-danger">*</span></label>
                    <input type="text" id="clientSearch" class="form-control" placeholder="Type a client's name..." autocomplete="off">
                    <div id="clientResultsList" class="hidden"></div>
                </div>

                <hr>

                <form method="POST" action="{{ route('households.store') }}">
                    @csrf
                    <input type="hidden" name="head_household" id="head_household">

                    <fieldset id="clientDetails">
                        <div class="grid grid-cols-1 gap-[12px] md:grid-cols-3">
                            <div><label for="lastname" class="field-label">Last Name</label><input type="text" id="lastname" class="form-control" readonly></div>
                            <div><label for="firstname" class="field-label">First Name</label><input type="text" id="firstname" class="form-control" readonly></div>
                            <div><label for="middlename" class="field-label">Middle Name</label><input type="text" id="middlename" class="form-control" readonly></div>
                        </div>
                        <div class="mt-[12px] grid grid-cols-1 gap-[12px] md:grid-cols-3">
                            <div><label for="extensionname" class="field-label">Extension Name</label><input type="text" id="extensionname" class="form-control" readonly></div>
                            <div><label for="region" class="field-label">Region</label><input type="text" id="region" class="form-control" readonly></div>
                            <div><label for="province" class="field-label">Province</label><input type="text" id="province" class="form-control" readonly></div>
                        </div>
                        <div class="mt-[12px] grid grid-cols-1 gap-[12px] md:grid-cols-3">
                            <div><label for="municipality" class="field-label">Municipality</label><input type="text" id="municipality" class="form-control" readonly></div>
                            <div><label for="barangay" class="field-label">Barangay</label><input type="text" id="barangay" class="form-control" readonly></div>
                            <div><label for="house_no" class="field-label">House No.</label><input type="text" id="house_no" class="form-control" readonly></div>
                        </div>
                        <div class="mt-[12px] grid grid-cols-1 gap-[12px] sm:grid-cols-2 md:grid-cols-4">
                            <div><label for="mobile_no" class="field-label">Mobile No.</label><input type="text" id="mobile_no" class="form-control" readonly></div>
                            <div><label for="email" class="field-label">Email</label><input type="text" id="email" class="form-control" readonly></div>
                            <div><label for="birthdate" class="field-label">Birthdate</label><input type="text" id="birthdate" class="form-control" readonly></div>
                            <div><label for="age" class="field-label">Age</label><input type="text" id="age" class="form-control" readonly></div>
                        </div>
                        <div class="mt-[12px] grid grid-cols-1 gap-[12px] sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
                            <div><label for="sex" class="field-label">Gender</label><input type="text" id="sex" class="form-control" readonly></div>
                            <div><label for="civil_status" class="field-label">Civil Status</label><input type="text" id="civil_status" class="form-control" readonly></div>
                            <div><label for="pwd" class="field-label">PWD</label><input type="text" id="pwd" class="form-control" readonly></div>
                            <div><label for="ip" class="field-label">IP</label><input type="text" id="ip" class="form-control" readonly></div>
                            <div><label for="ip_group" class="field-label">IP Group</label><input type="text" id="ip_group" class="form-control" readonly></div>
                            <div><label for="occupation" class="field-label">Occupation</label><input type="text" id="occupation" class="form-control" readonly></div>
                            <div><label for="monthly_income" class="field-label">Monthly Income</label><input type="text" id="monthly_income" class="form-control" readonly></div>
                        </div>
                        <div class="mt-[12px] grid grid-cols-1 gap-[12px] sm:grid-cols-2 md:grid-cols-4">
                            <div><label for="category" class="field-label">Category</label><input type="text" id="category" class="form-control" readonly></div>
                            <div><label for="aff_org" class="field-label">Affiliated Organizations</label><input type="text" id="aff_org" class="form-control" readonly></div>
                            <div><label for="precinct_no" class="field-label">Precinct No.</label><input type="text" id="precinct_no" class="form-control" readonly></div>
                            <div><label for="voter_id" class="field-label">Voter's ID</label><input type="text" id="voter_id" class="form-control" readonly></div>
                        </div>
                    </fieldset>

                    <div class="mt-[16px] flex items-center justify-end gap-2 border-t border-line-light pt-[16px]">
                        <a href="{{ route('households.index') }}" class="btn-subtle no-underline">Cancel / Return</a>
                        <button type="submit" class="btn-navy" id="submitBtn" disabled>Save Household</button>
                    </div>
                </form>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        const searchInput = document.getElementById('clientSearch');
        const resultsList = document.getElementById('clientResultsList');
        const hiddenField = document.getElementById('head_household');
        const submitBtn = document.getElementById('submitBtn');
        const detailsFieldset = document.getElementById('clientDetails');

        const fieldMap = {
            lastname: 'lastname', firstname: 'firstname', middlename: 'middlename',
            extensionname: 'extensionname', region: 'region', province: 'province',
            municipality: 'municipality_name', barangay: 'barangay_name', house_no: 'house_no',
            mobile_no: 'mobile_no', email: 'email', birthdate: 'birthdate', age: 'age',
            sex: 'sex', civil_status: 'civil_status', pwd: 'pwd', ip: 'ip',
            ip_group: 'ip_group', occupation: 'occupation', monthly_income: 'monthly_income',
            category: 'category', precinct_no: 'precinct_no', voter_id: 'voter_id'
        };

        function clearFields() {
            Object.keys(fieldMap).forEach(id => document.getElementById(id).value = '');
            document.getElementById('aff_org').value = '';
            detailsFieldset.classList.remove('filled');
        }

        function fillFields(client) {
            Object.entries(fieldMap).forEach(([id, key]) => {
                document.getElementById(id).value = client[key] ?? '';
            });
            document.getElementById('aff_org').value = (client.aff_orgs || []).join(', ');
            detailsFieldset.classList.add('filled');
        }

        let debounceTimer;

        searchInput.addEventListener('input', function() {
            hiddenField.value = '';
            submitBtn.disabled = true;
            clearFields();

            clearTimeout(debounceTimer);
            const query = this.value.trim();
            if (query.length < 2) {
                resultsList.classList.add('hidden');
                resultsList.innerHTML = '';
                return;
            }

            debounceTimer = setTimeout(() => {
                fetch('{{ route('households.clients.search') }}?q=' + encodeURIComponent(query))
                    .then(res => res.json())
                    .then(data => {
                        resultsList.innerHTML = '';
                        if (data.length === 0) {
                            resultsList.innerHTML = '<div class="result-item text-muted">No matching clients found</div>';
                        } else {
                            data.forEach(client => {
                                const item = document.createElement('div');
                                item.className = 'result-item';
                                const location = [client.barangay_name, client.municipality_name].filter(Boolean).join(', ');
                                const shownName = client.display_name || client.full_name;
                                item.textContent = shownName + (location ? ' — ' + location : '');
                                item.addEventListener('click', () => {
                                    searchInput.value = shownName;
                                    resultsList.classList.add('hidden');
                                    loadClientDetails(client.id);
                                });
                                resultsList.appendChild(item);
                            });
                        }
                        resultsList.classList.remove('hidden');
                    })
                    .catch(() => {
                        resultsList.innerHTML = '<div class="result-item text-danger">Error searching clients</div>';
                        resultsList.classList.remove('hidden');
                    });
            }, 300);
        });

        function loadClientDetails(id) {
            fetch('{{ route('households.index') }}/clients/' + id)
                .then(res => res.json())
                .then(client => {
                    if (client.error) {
                        alert(client.error);
                        return;
                    }
                    hiddenField.value = client.id;
                    fillFields(client);
                    submitBtn.disabled = false;
                })
                .catch(() => alert('Error loading client details.'));
        }

        document.addEventListener('click', function(e) {
            if (!document.getElementById('clientResults').contains(e.target)) {
                resultsList.classList.add('hidden');
            }
        });
    </script>
@endpush
