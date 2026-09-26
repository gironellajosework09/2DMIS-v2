{{-- Scholar profile details (full page or panel partial) --}}
@php
    $isPanel = $panel ?? false;
    $client = $scholar->client;
    $exam = \App\Models\ExamResult::where('client_id', $client->id ?? 0)->first();
@endphp

@if (! $isPanel)
@extends('layouts.app')
@section('title', 'Scholar Profile — 2D MIS')
@section('content')

@endif

<div class="data-card p-[1.25rem]" data-panel-body>
    <div class="mb-[16px] flex flex-wrap items-center justify-between gap-[12px]">
        @if ($isPanel)
            <h2 class="m-0 text-dense font-heading font-semibold text-ink" data-panel-title>Scholar Profile</h2>
        @else
            <h1 class="m-0 text-page-title font-heading font-bold text-ink">Scholar Profile</h1>
        @endif
        <div class="flex flex-wrap items-center gap-[8px]">
            @if (! $isPanel)
                <a href="{{ route('scholars.index') }}" class="btn-subtle no-underline">Back</a>
            @else
                <a href="{{ route('scholars.show', $scholar) }}" class="btn-subtle no-underline">Open full page</a>
            @endif
            <button type="button" class="btn-subtle">Photo</button>
            <a href="{{ route('scholars.edit', $scholar) }}" class="btn-gold no-underline">Edit</a>
        </div>
    </div>

    <div class="grid items-start gap-[16px] md:grid-cols-[220px_minmax(0,1fr)]">
        <div class="text-center" data-panel-avatar>
            @if ($scholar->photo)
                <img src="{{ asset('storage/uploads/scholar_photos/'.$scholar->photo) }}" alt="Scholar photo"
                    class="mx-auto max-w-full rounded-panel ring-1 ring-line" style="width:180px;">
            @else
                <div class="mx-auto grid place-items-center rounded-panel ring-1 ring-line text-dense text-ink-muted"
                    style="width:180px;height:200px;">No photo</div>
            @endif
            <div class="mt-[8px] font-semibold text-ink">{{ $scholar->full_name }}</div>
        </div>

        <dl class="m-0 grid grid-cols-1 gap-x-[16px] gap-y-[10px] sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                'Scholar ID' => $scholar->id,
                'Client ID' => $client->id ?? '—',
                'Program' => $scholar->program,
                'School' => $scholar->school,
                'School Type' => $scholar->school_type,
                'Campus' => $scholar->campus,
                'College / Department' => $scholar->college_department,
                'Course' => $scholar->course,
                'Year Level' => $scholar->year_level,
                'Regular' => $scholar->is_regular ? 'Yes' : 'No',
                'Year Started' => $scholar->year_started,
                'Landbank Account' => $scholar->landbank_no,
                'Status' => $scholar->status,
                'Birthdate' => $client->birthdate ?? '—',
                'Sex' => $client->sex ?? '—',
                'Civil Status' => $client->civil_status ?? '—',
                'Municipality' => $client->municipality->name ?? '—',
                'Barangay' => $client->barangayInfo->name ?? '—',
                'Mobile No.' => $client->mobile_no ?? '—',
                'Email' => $client->email ?? '—',
            ] as $label => $value)
                <div class="min-w-0" data-panel-field>
                    <dt class="ui-micro-label">{{ $label }}</dt>
                    <dd class="m-0 truncate text-dense text-ink" title="{{ $value }}">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </div>

    @if ($exam)
    <hr class="my-[24px] border-line-light">
    <h3 class="mb-[12px] text-dense font-heading font-semibold text-ink">Exam Result</h3>
    <dl class="m-0 grid grid-cols-1 gap-x-[16px] gap-y-[10px] sm:grid-cols-2">
        @foreach([
            'Exam Date' => $exam->exam_date,
            'Venue' => $exam->venue,
            'Result' => $exam->result,
            'Score' => $exam->score,
        ] as $label => $value)
            <div class="min-w-0">
                <dt class="ui-micro-label">{{ $label }}</dt>
                <dd class="m-0 truncate text-dense text-ink" title="{{ $value }}">
                    @if($label === 'Result')
                        <span class="status-badge {{ $value === 'Passed' ? 'is-paid' : ($value === 'Failed' ? 'is-neutral' : 'is-pending') }}">
                            {{ $value }}
                        </span>
                    @else
                        {{ $value }}
                    @endif
                </dd>
            </div>
        @endforeach
    </dl>
    @endif

    @if ($scholar->gipInfo)
    <hr class="my-[24px] border-line-light">
    <h3 class="mb-[12px] text-dense font-heading font-semibold text-ink">GIP Profile</h3>
    @include('scholars._gip', ['gip' => $scholar->gipInfo])
    @endif

    @php
        $ysParts = array_values(array_filter(array_map('trim', explode('-', (string) $scholar->year_started))));
        $ysStart = $ysParts[0] ?? '';
        $ysEnd = $ysParts[1] ?? '';
    @endphp
    {{-- UX-2: lightweight panel edit (scholar academic fields; client_id +
         program preserved and unchanged in the panel) --}}
    <div id="scholarPanelEdit" class="panel-edit-form mt-[20px] border-t border-line-light pt-[16px]" hidden>
        <form method="POST" action="{{ route('scholars.update', $scholar->id) }}" data-panel-edit-form>
            @csrf
            @method('PUT')
            <div class="panel-form-errors" role="alert"></div>
            <input type="hidden" name="client_id" value="{{ $client->id ?? $scholar->client_id }}">
            <input type="hidden" name="program" value="{{ $scholar->program }}">
            <div class="grid grid-cols-1 gap-x-[16px] gap-y-[12px] sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="field-label" for="seSchool">School</label>
                    <input type="text" id="seSchool" name="school" class="field-control" value="{{ old('school', $scholar->school) }}">
                </div>
                <div>
                    <label class="field-label" for="seSchoolType">School type</label>
                    <input type="text" id="seSchoolType" name="school_type" class="field-control" value="{{ old('school_type', $scholar->school_type) }}">
                </div>
                <div>
                    <label class="field-label" for="seCampus">Campus</label>
                    <input type="text" id="seCampus" name="campus" class="field-control" value="{{ old('campus', $scholar->campus) }}">
                </div>
                <div>
                    <label class="field-label" for="seDept">College / Department</label>
                    <input type="text" id="seDept" name="college_department" class="field-control" value="{{ old('college_department', $scholar->college_department) }}">
                </div>
                <div>
                    <label class="field-label" for="seCourse">Course</label>
                    <input type="text" id="seCourse" name="course" class="field-control" value="{{ old('course', $scholar->course) }}">
                </div>
                <div>
                    <label class="field-label" for="seYearLevel">Year level</label>
                    <input type="text" id="seYearLevel" name="year_level" class="field-control" value="{{ old('year_level', $scholar->year_level) }}">
                </div>
                <div>
                    <label class="field-label" for="seLandbank">Landbank account</label>
                    <input type="text" id="seLandbank" name="landbank_no" class="field-control" value="{{ old('landbank_no', $scholar->landbank_no) }}">
                </div>
                <div>
                    <label class="field-label" for="seYearStart">Year started (start)</label>
                    <input type="text" id="seYearStart" name="year_start" class="field-control" value="{{ old('year_start', $ysStart) }}">
                </div>
                <div>
                    <label class="field-label" for="seYearEnd">Year started (end)</label>
                    <input type="text" id="seYearEnd" name="year_end" class="field-control" value="{{ old('year_end', $ysEnd) }}">
                </div>
                <div class="sm:col-span-2 form-check form-switch mt-2 mb-0">
                    <input type="hidden" name="is_regular" value="0">
                    <input type="checkbox" id="seRegular" name="is_regular" value="1"
                        class="form-check-input" {{ old('is_regular', $scholar->is_regular) ? 'checked' : '' }}>
                    <label class="form-check-label text-dense text-ink" for="seRegular">Regular</label>
                </div>
            </div>
            <div class="mt-[16px] flex items-center gap-2">
                <button type="button" class="btn-subtle" data-edit-scholar-cancel="{{ $scholar->id }}">Cancel</button>
                <button type="submit" class="btn-navy">Save Scholar</button>
            </div>
        </form>
    </div>

    {{-- UX-2: scholar panel-edit toggle + submit (inline so it runs inside panel) --}}
    <script>
        (function () {
            'use strict';
            var editId = Number('{{ $scholar->id }}');
            var editBox = document.getElementById('scholarPanelEdit');
            if (!editBox) return;

            document.querySelectorAll('[data-edit-scholar="' + editId + '"]').forEach(function (b) {
                b.addEventListener('click', function () { editBox.hidden = !editBox.hidden; });
            });
            document.querySelectorAll('[data-edit-scholar-cancel="' + editId + '"]').forEach(function (c) {
                c.addEventListener('click', function () { editBox.hidden = true; });
            });

            var form = editBox.querySelector('[data-panel-edit-form]');
            if (form && window.DetailsPanel && window.DetailsPanel.submitPanelForm) {
                window.DetailsPanel.submitPanelForm(form);
            }
        })();
    </script>
</div>

<div data-panel-title style="display:none;">{{ $scholar->full_name }}</div>
<div data-panel-sub style="display:none;">Scholar ID: {{ $scholar->id }} &middot; {{ $scholar->program }}</div>
<div data-panel-meta style="display:none;">
    <span class="status-badge {{ $scholar->status === 'Active' ? 'is-paid' : ($scholar->status === 'Graduated' ? 'is-approved' : 'is-neutral') }}">{{ $scholar->status }}</span>
    <span class="program-tag">{{ $scholar->program }}</span>
</div>
<div data-panel-actions style="display:none;">
    <button type="button" class="btn btn-gold" data-edit-scholar="{{ $scholar->id }}">Edit</button>
    <button type="button" class="btn btn-outline" data-qr-scholar="{{ $scholar->id }}">View QR</button>
    <button type="button" class="btn btn-outline" data-sim="Scholarship certificate generated (mock)">Certificate</button>
</div>

@if (! $isPanel)
@endsection
@endif