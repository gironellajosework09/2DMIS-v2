@extends('layouts.app')

@section('title', 'Add Family Member — 2D MIS')

{{-- Batch G migration (UI_UX_ANALYSIS §8.9 Group 3 remainder): family
     member creation. The client-search contract (debounced fetch to
     family-members.search, hidden existing_client_id binding,
     disabled-until-selected submit) is byte-preserved; the JS-built
     hover-bg result rows keep their class in the scoped CSS. --}}
@push('styles')
    <style>
        /* ── Family-member scope. Prefixed with #family-member-screen —
           nothing here can leak to other screens. ── */
        #family-member-screen #existing_client_results {
            max-height: 150px;
            overflow-y: auto;
            width: 100%;
            position: absolute;
            z-index: 10;
            border-color: var(--color-line);
            background: var(--color-surface);
        }

        #family-member-screen .hover-bg:hover {
            background-color: var(--color-surface-hover);
            cursor: pointer;
        }
    </style>
@endpush

@section('content')

    @include('partials.page-header', [
        'title' => 'Add Family Member',
        'subtitle' => 'For '.$parent->displayFullName().' — link an existing client as a family member.',
    ])

    <div id="family-member-screen">
        <section class="data-card" aria-label="Add family member form">
            <div class="data-card-body">
                @if ($errors->any())
                    <div class="mb-[16px] flex items-start justify-between gap-2 rounded-[var(--radius-control)] border-l-[3px] border-[var(--color-red)] bg-[rgb(206_17_38_/_0.06)] p-[13px_16px] text-dense text-ink" role="alert" x-data="{ open: true }" x-show="open">
                        {{ $errors->first() }}
                        <button type="button" class="btn-close" @click="open = false" aria-label="Close"></button>
                    </div>
                @endif

                <form method="POST" action="{{ route('family-members.store', $parent) }}">
                    @csrf

                    <div class="relative mb-[16px]">
                        <label for="existing_client_search" class="field-label">Search Existing Client</label>
                        <input type="text" id="existing_client_search" class="form-control" placeholder="Type to search..." autocomplete="off">
                        <div id="existing_client_results" class="border bg-white mt-1 hidden"></div>
                        <input type="hidden" name="existing_client_id" id="existing_client_id">
                    </div>

                    <div class="max-w-[420px]">
                        <label for="relationship" class="field-label">Relationship of {{ $parent->firstname }} to the family member <span class="text-danger">*</span></label>
                        <select name="relationship" id="relationship" class="form-select" required>
                            <option value="">--Select--</option>
                            @foreach (['FATHER', 'MOTHER', 'SON', 'DAUGHTER', 'SPOUSE', 'SIBLING', 'GRANDPARENT', 'GRANDCHILD'] as $role)
                                <option value="{{ $role }}">{{ $role }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mt-[16px] flex items-center justify-end gap-2 border-t border-line-light pt-[16px]">
                        <a href="{{ route('clients.show', $parent) }}" class="btn-subtle no-underline">Cancel / Return</a>
                        <button type="submit" class="btn-navy" id="submitBtn" disabled>Add Family Member</button>
                    </div>
                </form>
            </div>
        </section>
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
                resultsDiv.classList.add('hidden');
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
                                const fullName = client.display_name || (client.lastname + ', ' + client.firstname);
                                const loc = [client.barangay_name, client.municipality_name].filter(Boolean).join(', ');
                                div.textContent = fullName + (loc ? ' — ' + loc : '');
                                div.classList.add('p-1', 'hover-bg');
                                div.addEventListener('click', () => {
                                    searchInput.value = div.textContent;
                                    existingClientIdInput.value = client.id;
                                    submitBtn.disabled = false;
                                    resultsDiv.classList.add('hidden');
                                });
                                resultsDiv.appendChild(div);
                            });
                        }
                        resultsDiv.classList.remove('hidden');
                    });
            }, 300);
        });

        document.addEventListener('click', e => {
            if (e.target !== searchInput) resultsDiv.classList.add('hidden');
        });
    </script>
@endpush
