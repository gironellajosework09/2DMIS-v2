@extends('layouts.app')

@section('title', 'Edit Transaction — 2D MIS')

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
    @php($client = $transaction->client)
    @php($clientName = $client ? trim($client->lastname.', '.$client->firstname.' '.$client->middlename.' '.$client->extensionname) : '')
    @php($isSelf = $client !== null && trim($transaction->patient_name ?? '') === trim($client->lastname.', '.$client->firstname.' '.$client->middlename))
    @php($isCustom = ! $isSelf && ! empty($transaction->patient_name))

    <div class="card shadow-lg border-0 p-4">
        <h3 class="mb-3 text-center">Edit Transaction for {{ $clientName }}</h3>

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ $errors->first() }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <form method="POST" action="{{ route('transactions.update', $transaction->id) }}">
            @csrf
            @method('PUT')

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label>Program <span class="text-danger">*</span></label>
                    <select name="program" class="form-select" required>
                        <option value="">-- Select Program --</option>
                        @foreach ($programs as $program)
                            <option value="{{ $program }}" @selected(old('program', $transaction->program) === $program)>{{ $program }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mb-3 position-relative">
                <label>Patient <span class="text-danger">*</span></label>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="patient_option" id="patient_self" value="self" @checked($isSelf)>
                    <label class="form-check-label" for="patient_self">Self ({{ $clientName }})</label>
                </div>

                <div class="form-check mt-1">
                    <input class="form-check-input" type="radio" name="patient_option" id="patient_custom" value="custom" @checked($isCustom)>
                    <label class="form-check-label" for="patient_custom">Enter Name</label>
                </div>
                <input type="text" name="patient_name_custom" id="patient_name_custom_input" class="form-control mt-2 uppercase"
                    placeholder="Enter patient name" value="{{ old('patient_name_custom', $transaction->patient_name) }}" @disabled(! $isCustom)>

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
                    <input type="date" name="date_applied" class="form-control" required value="{{ old('date_applied', $transaction->date_applied) }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label>Type <span class="text-danger">*</span></label>
                    <select name="type" class="form-select" required>
                        @foreach (\App\Services\TransactionService::TYPES as $type)
                            <option value="{{ $type }}" @selected(old('type', $transaction->type) === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label>Status <span class="text-danger">*</span></label>
                    <select name="status" class="form-select" required>
                        @foreach (\App\Services\TransactionService::STATUSES as $status)
                            <option value="{{ $status }}" @selected(old('status', $transaction->status) === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label>Remarks</label>
                <input type="text" name="remarks" class="form-control uppercase" value="{{ old('remarks', $transaction->remarks) }}">
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label>Suggested Amount</label>
                    <input type="number" step="0.01" name="suggested_amount" class="form-control" value="{{ old('suggested_amount', $transaction->suggested_amount) }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label>Amount Paid</label>
                    <input type="number" step="0.01" name="amount_paid" class="form-control" value="{{ old('amount_paid', $transaction->amount_paid) }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label>Pay Out Date</label>
                    <input type="date" name="payout_date" class="form-control" value="{{ old('payout_date', $transaction->payout_date) }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label>Date Paid</label>
                    <input type="date" name="date_paid" class="form-control" value="{{ old('date_paid', $transaction->date_paid) }}">
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('transactions.show', $transaction->id) }}" class="btn btn-secondary">Cancel / Return</a>
                <button type="submit" class="btn btn-primary">Update Transaction</button>
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

        selfRadio.addEventListener('change', () => {
            customInput.disabled = true;
            customInput.value = '';
            searchInput.disabled = true;
            searchInput.value = '';
            existingClientId.value = '';
            results.innerHTML = '';
        });
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
    </script>
@endpush
