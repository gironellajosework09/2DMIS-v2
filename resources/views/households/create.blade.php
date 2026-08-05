@extends('layouts.app')

@section('title', 'Add Household — 2D MIS')

@push('styles')
    <style>
        .card {
            font-size: 0.875rem;
        }

        #clientResultsList {
            position: absolute;
            z-index: 10;
            width: 100%;
            max-height: 240px;
            overflow-y: auto;
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 0 0 0.5rem 0.5rem;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.08);
        }

        #clientResultsList .result-item {
            padding: 0.5rem 0.75rem;
            cursor: pointer;
            font-size: 0.85rem;
        }

        #clientResultsList .result-item:hover {
            background-color: #f1f3f5;
        }

        fieldset#clientDetails {
            opacity: 0.6;
            pointer-events: none;
            transition: opacity 0.15s ease;
        }

        fieldset#clientDetails.filled {
            opacity: 1;
        }
    </style>
@endpush

@section('content')
    <div class="card shadow-lg border-0 p-4">
        <h3 class="mb-3 text-center">Add New Household</h3>

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ $errors->first() }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="mb-4 position-relative" id="clientResults">
            <label>Search Head of Household <span class="text-danger">*</span></label>
            <input type="text" id="clientSearch" class="form-control" placeholder="Type a client's name..." autocomplete="off">
            <div id="clientResultsList" class="d-none"></div>
        </div>

        <hr>

        <form method="POST" action="{{ route('households.store') }}">
            @csrf
            <input type="hidden" name="head_household" id="head_household">

            <fieldset id="clientDetails">
                <div class="row">
                    <div class="col-md-4 mb-3"><label>Last Name</label><input type="text" id="lastname" class="form-control" readonly></div>
                    <div class="col-md-4 mb-3"><label>First Name</label><input type="text" id="firstname" class="form-control" readonly></div>
                    <div class="col-md-4 mb-3"><label>Middle Name</label><input type="text" id="middlename" class="form-control" readonly></div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3"><label>Extension Name</label><input type="text" id="extensionname" class="form-control" readonly></div>
                    <div class="col-md-4 mb-3"><label>Region</label><input type="text" id="region" class="form-control" readonly></div>
                    <div class="col-md-4 mb-3"><label>Province</label><input type="text" id="province" class="form-control" readonly></div>
                </div>
                <div class="row">
                    <div class="col mb-3"><label>Municipality</label><input type="text" id="municipality" class="form-control" readonly></div>
                    <div class="col mb-3"><label>Barangay</label><input type="text" id="barangay" class="form-control" readonly></div>
                    <div class="col mb-3"><label>House No.</label><input type="text" id="house_no" class="form-control" readonly></div>
                </div>
                <div class="row">
                    <div class="col-md-3 mb-3"><label>Mobile No.</label><input type="text" id="mobile_no" class="form-control" readonly></div>
                    <div class="col-md-3 mb-3"><label>Email</label><input type="text" id="email" class="form-control" readonly></div>
                    <div class="col-md-3 mb-3"><label>Birthdate</label><input type="text" id="birthdate" class="form-control" readonly></div>
                    <div class="col-md-3 mb-3"><label>Age</label><input type="text" id="age" class="form-control" readonly></div>
                </div>
                <div class="row">
                    <div class="col mb-3"><label>Gender</label><input type="text" id="sex" class="form-control" readonly></div>
                    <div class="col mb-3"><label>Civil Status</label><input type="text" id="civil_status" class="form-control" readonly></div>
                    <div class="col mb-3"><label>PWD</label><input type="text" id="pwd" class="form-control" readonly></div>
                    <div class="col mb-3"><label>IP</label><input type="text" id="ip" class="form-control" readonly></div>
                    <div class="col mb-3"><label>IP Group</label><input type="text" id="ip_group" class="form-control" readonly></div>
                    <div class="col mb-3"><label>Occupation</label><input type="text" id="occupation" class="form-control" readonly></div>
                    <div class="col mb-3"><label>Monthly Income</label><input type="text" id="monthly_income" class="form-control" readonly></div>
                </div>
                <div class="row">
                    <div class="col-md-3 mb-3"><label>Category</label><input type="text" id="category" class="form-control" readonly></div>
                    <div class="col-md-3 mb-3"><label>Affiliated Organizations</label><input type="text" id="aff_org" class="form-control" readonly></div>
                    <div class="col-md-3 mb-3"><label>Precinct No.</label><input type="text" id="precinct_no" class="form-control" readonly></div>
                    <div class="col-md-3 mb-3"><label>Voter's ID</label><input type="text" id="voter_id" class="form-control" readonly></div>
                </div>
            </fieldset>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('households.index') }}" class="btn btn-secondary">Cancel / Return</a>
                <button type="submit" class="btn btn-primary" id="submitBtn" disabled>Save Household</button>
            </div>
        </form>
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
                resultsList.classList.add('d-none');
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
                                item.textContent = client.full_name + (location ? ' — ' + location : '');
                                item.addEventListener('click', () => {
                                    searchInput.value = client.full_name;
                                    resultsList.classList.add('d-none');
                                    loadClientDetails(client.id);
                                });
                                resultsList.appendChild(item);
                            });
                        }
                        resultsList.classList.remove('d-none');
                    })
                    .catch(() => {
                        resultsList.innerHTML = '<div class="result-item text-danger">Error searching clients</div>';
                        resultsList.classList.remove('d-none');
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
                resultsList.classList.add('d-none');
            }
        });
    </script>
@endpush
