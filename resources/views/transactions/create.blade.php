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
    {{-- Batch F migration: token vocabulary around the untouched
         beneficiary/search/TUPAD workflows (ids and script below are
         byte-identical to the pre-migration view). --}}

    @include('partials.page-header', [
        'title' => 'Add Transaction',
        'subtitle' => 'For client: '.$client->displayFullName(),
    ])

    <div class="data-card p-[1.25rem]">
        @if ($errors->any())
            <div class="ui-notice mb-[16px]" role="alert">
                <span aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 h-4 w-4 shrink-0"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                </span>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <form method="POST" action="{{ route('transactions.store') }}">
            @csrf
            <input type="hidden" name="client_id" value="{{ $client->id }}">

            <div class="flex flex-col gap-[16px]">

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <label class="field-label" for="program">Program <span class="text-danger">*</span></label>
                        <select name="program" id="program" class="form-select" required>
                            <option value="">-- Select Program --</option>
                            @foreach ($programs as $program)
                                <option value="{{ $program }}" @selected(old('program') === $program)>{{ $program }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="position-relative">
                    <span class="field-label">Beneficiary <span class="text-danger">*</span></span>
                    <div class="form-check mt-[4px]">
                        <input class="form-check-input" type="radio" name="patient_option" id="patient_self" value="self" checked>
                        <label class="form-check-label" for="patient_self">
                            Self ({{ $client->lastname }}, {{ $client->firstname }} {{ $client->middlename }})
                        </label>
                    </div>

                    <div class="form-check mt-[4px]">
                        <input class="form-check-input" type="radio" name="patient_option" id="patient_custom" value="custom">
                        <label class="form-check-label" for="patient_custom">Enter Name</label>
                    </div>
                    <input type="text" name="patient_name_custom" id="patient_name_custom_input" class="form-control mt-[8px] uppercase" placeholder="Enter patient name" disabled>

                    <div class="form-check mt-[4px]">
                        <input class="form-check-input" type="radio" name="patient_option" id="patient_existing" value="existing">
                        <label class="form-check-label" for="patient_existing">Select Existing Client</label>
                    </div>
                    <input type="text" id="existing_search" class="form-control mt-[8px]" placeholder="Search existing client" disabled>
                    <input type="hidden" name="existing_client_id" id="existing_client_id">
                    <ul id="search_results" class="list-group position-absolute bg-white border" style="width:min(24rem,100%);"></ul>
                </div>

                <div class="grid grid-cols-1 gap-x-[16px] gap-y-[12px] sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <label class="field-label" for="date_applied">Date Applied <span class="text-danger">*</span></label>
                        <input type="date" name="date_applied" id="date_applied" class="form-control" required value="{{ old('date_applied', date('Y-m-d')) }}">
                    </div>
                    <div>
                        <label class="field-label" for="type">Type <span class="text-danger">*</span></label>
                        <select name="type" id="type" class="form-select" required>
                            <option value="">-- Select Type --</option>
                            @foreach (\App\Services\TransactionService::TYPES as $type)
                                <option value="{{ $type }}" @selected(old('type') === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="field-label" for="status">Status <span class="text-danger">*</span></label>
                        <select name="status" id="status" class="form-select" required>
                            @foreach (\App\Services\TransactionService::STATUSES as $status)
                                <option value="{{ $status }}" @selected(old('status', 'PENDING PAYOUT') === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-x-[16px] gap-y-[12px] sm:grid-cols-2">
                    <div>
                        <label class="field-label" for="remarks">Remarks</label>
                        <input type="text" name="remarks" id="remarks" class="form-control uppercase" value="{{ old('remarks') }}">
                    </div>
                    <div>
                        <label class="field-label" for="comments">Comments</label>
                        <input type="text" name="comments" id="comments" class="form-control uppercase" value="{{ old('comments') }}">
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-x-[16px] gap-y-[12px] sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <label class="field-label" for="suggested_amount">Suggested Amount</label>
                        <input type="number" step="0.01" name="suggested_amount" id="suggested_amount" class="form-control" value="{{ old('suggested_amount') }}">
                    </div>
                    <div>
                        <label class="field-label" for="amount_paid">Amount Paid</label>
                        <input type="number" step="0.01" name="amount_paid" id="amount_paid" class="form-control" value="{{ old('amount_paid') }}">
                    </div>
                    <div>
                        <label class="field-label" for="payout_date">Pay Out Date</label>
                        <input type="date" name="payout_date" id="payout_date" class="form-control" value="{{ old('payout_date') }}">
                    </div>
                    <div>
                        <label class="field-label" for="date_paid">Date Paid</label>
                        <input type="date" name="date_paid" id="date_paid" class="form-control" value="{{ old('date_paid') }}">
                    </div>
                    <div>
                        <label class="field-label" for="gwa">GWA</label>
                        <input type="number" step="0.0001" name="gwa" id="gwa" class="form-control" value="{{ old('gwa') }}">
                    </div>
                    <div>
                        <label class="field-label" for="units">Units</label>
                        <input type="number" step="0.0001" name="units" id="units" class="form-control" value="{{ old('units') }}">
                    </div>
                </div>

                <div class="flex justify-end gap-[8px]">
                    <a href="{{ route('clients.show', $client) }}" class="btn-subtle no-underline">Cancel / Return</a>
                    <button type="submit" class="btn-navy">Save Transaction</button>
                </div>

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
                        const fullName = c.display_name || (c.lastname + ', ' + c.firstname + ' ' + (c.middlename ?? '') + ' ' + (c.extensionname ?? ''));
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
