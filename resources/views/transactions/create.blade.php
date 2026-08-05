@extends('layouts.app')

@section('title', 'Add Transaction — 2D MIS')

@push('styles')
    <style>
        #search_results {
            max-height: 150px;
            overflow-y: auto;
            z-index: 1000;
        }

        input.uppercase {
            text-transform: uppercase;
        }
    </style>
@endpush

@section('content')
    <div class="card shadow-lg border-0 p-4">
        <h3 class="mb-3 text-center">Add Transaction for {{ $client->full_name }}</h3>

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ $errors->first() }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <form method="POST" action="{{ route('transactions.store') }}">
            @csrf
            <input type="hidden" name="client_id" value="{{ $client->id }}">

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label>Program <span class="text-danger">*</span></label>
                    <select name="program" id="program" class="form-select" required>
                        <option value="">-- Select Program --</option>
                        @foreach ($programs as $program)
                            <option value="{{ $program }}" @selected(old('program') === $program)>{{ $program }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mb-3 position-relative">
                <label>Beneficiary <span class="text-danger">*</span></label>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="patient_option" id="patient_self" value="self" checked>
                    <label class="form-check-label" for="patient_self">
                        Self ({{ $client->lastname }}, {{ $client->firstname }} {{ $client->middlename }})
                    </label>
                </div>

                <div class="form-check mt-1">
                    <input class="form-check-input" type="radio" name="patient_option" id="patient_custom" value="custom">
                    <label class="form-check-label" for="patient_custom">Enter Name</label>
                </div>
                <input type="text" name="patient_name_custom" id="patient_name_custom_input" class="form-control mt-2 uppercase" placeholder="Enter patient name" disabled>

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
                    <input type="date" name="date_applied" class="form-control" required value="{{ old('date_applied', date('Y-m-d')) }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label>Type <span class="text-danger">*</span></label>
                    <select name="type" class="form-select" required>
                        <option value="">-- Select Type --</option>
                        @foreach (\App\Services\TransactionService::TYPES as $type)
                            <option value="{{ $type }}" @selected(old('type') === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label>Status <span class="text-danger">*</span></label>
                    <select name="status" class="form-select" required>
                        @foreach (\App\Services\TransactionService::STATUSES as $status)
                            <option value="{{ $status }}" @selected(old('status', 'PENDING PAYOUT') === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label>Remarks</label>
                <input type="text" name="remarks" class="form-control uppercase" value="{{ old('remarks') }}">
            </div>

            <div class="mb-3">
                <label>Comments</label>
                <input type="text" name="comments" id="comments" class="form-control uppercase" value="{{ old('comments') }}">
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label>Suggested Amount</label>
                    <input type="number" step="0.01" name="suggested_amount" id="suggested_amount" class="form-control" value="{{ old('suggested_amount') }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label>Amount Paid</label>
                    <input type="number" step="0.01" name="amount_paid" class="form-control" value="{{ old('amount_paid') }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label>Pay Out Date</label>
                    <input type="date" name="payout_date" id="payout_date" class="form-control" value="{{ old('payout_date') }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label>Date Paid</label>
                    <input type="date" name="date_paid" class="form-control" value="{{ old('date_paid') }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label>GWA</label>
                    <input type="number" step="0.0001" name="gwa" id="gwa" class="form-control" value="{{ old('gwa') }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label>Units</label>
                    <input type="number" step="0.0001" name="units" id="units" class="form-control" value="{{ old('units') }}">
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('clients.show', $client) }}" class="btn btn-secondary">Cancel / Return</a>
                <button type="submit" class="btn btn-primary">Save Transaction</button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
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

        customInput.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });

        const programSelect = document.getElementById('program');
        const commentsInput = document.getElementById('comments');
        const suggestedInput = document.getElementById('suggested_amount');
        const payoutDateInput = document.getElementById('payout_date');
        const gwaInput = document.getElementById('gwa');
        const unitsInput = document.getElementById('units');

        function toggleFields() {
            const isTupad = programSelect.value === 'TUPAD';
            [commentsInput, suggestedInput, payoutDateInput, gwaInput, unitsInput].forEach(input => {
                input.disabled = isTupad;
                if (isTupad) input.value = '';
            });
        }

        toggleFields();
        programSelect.addEventListener('change', toggleFields);
    </script>
@endpush
