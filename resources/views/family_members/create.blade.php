@extends('layouts.app')

@section('title', 'Add Family Member — 2D MIS')

@push('styles')
    <style>
        #existing_client_results {
            max-height: 150px;
            overflow-y: auto;
            width: 100%;
            position: absolute;
            z-index: 10;
        }

        .hover-bg:hover {
            background-color: #f1f1f1;
            cursor: pointer;
        }
    </style>
@endpush

@section('content')
    <div class="card shadow-lg border-0 p-4">
        <h3 class="mb-3 text-center">Add Family Member for {{ $parent->full_name }}</h3>

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ $errors->first() }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <form method="POST" action="{{ route('family-members.store', $parent) }}">
            @csrf

            <div class="row mb-3 position-relative">
                <div class="col-md-12">
                    <label>Search Existing Client</label>
                    <input type="text" id="existing_client_search" class="form-control" placeholder="Type to search..." autocomplete="off">
                    <div id="existing_client_results" class="border bg-white mt-1 d-none"></div>
                    <input type="hidden" name="existing_client_id" id="existing_client_id">
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label>Relationship of {{ $parent->firstname }} to the family member <span class="text-danger">*</span></label>
                    <select name="relationship" id="relationship" class="form-select" required>
                        <option value="">--Select--</option>
                        @foreach (['FATHER', 'MOTHER', 'SON', 'DAUGHTER', 'SPOUSE', 'SIBLING', 'GRANDPARENT', 'GRANDCHILD'] as $role)
                            <option value="{{ $role }}">{{ $role }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('clients.show', $parent) }}" class="btn btn-secondary">Cancel / Return</a>
                <button type="submit" class="btn btn-primary" id="submitBtn" disabled>Add Family Member</button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        const searchInput = document.getElementById('existing_client_search');
        const resultsDiv = document.getElementById('existing_client_results');
        const existingClientIdInput = document.getElementById('existing_client_id');
        const submitBtn = document.getElementById('submitBtn');
        let debounceTimer;

        searchInput.addEventListener('input', function() {
            existingClientIdInput.value = '';
            submitBtn.disabled = true;
            const query = this.value.trim();

            clearTimeout(debounceTimer);
            if (query.length < 2) {
                resultsDiv.classList.add('d-none');
                resultsDiv.innerHTML = '';
                return;
            }

            debounceTimer = setTimeout(() => {
                fetch('{{ route('family-members.search') }}?q=' + encodeURIComponent(query))
                    .then(res => res.json())
                    .then(data => {
                        resultsDiv.innerHTML = '';
                        if (data.length === 0) {
                            resultsDiv.innerHTML = '<div class="p-2 text-muted">No matching clients found</div>';
                        } else {
                            data.forEach(client => {
                                const div = document.createElement('div');
                                const loc = [client.barangay_name, client.municipality_name].filter(Boolean).join(', ');
                                div.textContent = client.lastname + ', ' + client.firstname + (loc ? ' — ' + loc : '');
                                div.classList.add('p-1', 'hover-bg');
                                div.addEventListener('click', () => {
                                    searchInput.value = div.textContent;
                                    existingClientIdInput.value = client.id;
                                    submitBtn.disabled = false;
                                    resultsDiv.classList.add('d-none');
                                });
                                resultsDiv.appendChild(div);
                            });
                        }
                        resultsDiv.classList.remove('d-none');
                    });
            }, 300);
        });

        document.addEventListener('click', e => {
            if (e.target !== searchInput) resultsDiv.classList.add('d-none');
        });
    </script>
@endpush
