{{-- Scholar GIP details panel section --}}
@if ($gip)
    <dl class="m-0 grid grid-cols-1 gap-x-[16px] gap-y-[10px] sm:grid-cols-2 lg:grid-cols-3">
        @foreach([
            'Valid Government ID' => $gip->valid_govt_id ?? '(N/A)',
            'ID Number' => $gip->id_number ?? '(N/A)',
            'Insurance Beneficiary' => $gip->insurance_beneficiary ?? '(N/A)',
            'Emergency Contact' => $gip->emergency_contact ?? '(N/A)',
            'Emergency Contact Number' => $gip->ecp_contact_number ?? '(N/A)',
            'Emergency Contact Address' => $gip->ecp_address ?? '(N/A)',
            'College' => $gip->college ?? '(N/A)',
            'Course' => $gip->course ?? '(N/A)',
            'Year Graduated' => $gip->year_graduated ?? '(N/A)',
            'High School' => $gip->high_school ?? '(N/A)',
            'Elementary School' => $gip->elementary_school ?? '(N/A)',
            'Latest Work Experience' => $gip->latest_work_experience ?? '(N/A)',
            'Position' => $gip->position ?? '(N/A)',
            'Period of Engagement' => $gip->period_of_engagement ?? '(N/A)',
            'Special Skills' => $gip->special_skills ?? '(N/A)',
            'Achievements' => $gip->achievements ?? '(N/A)',
        ] as $label => $value)
            <div class="min-w-0">
                <dt class="ui-micro-label">{{ $label }}</dt>
                <dd class="m-0 whitespace-pre-line text-dense text-ink">{{ $value }}</dd>
            </div>
        @endforeach
    </dl>
@endif