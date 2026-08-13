@if ($hasGipTransaction)
    <div class="accordion mt-3" id="gipAccordion">
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingGIP">
                <button class="accordion-button collapsed fw-bold" type="button"
                    data-bs-toggle="collapse" data-bs-target="#collapseGIP"
                    aria-expanded="false" aria-controls="collapseGIP">
                    GIP Details
                </button>
            </h2>

            <div id="collapseGIP" class="accordion-collapse collapse"
                aria-labelledby="headingGIP" data-bs-parent="#gipAccordion">
                <div class="accordion-body p-3">

                    @if ($gip)
                        <div class="row mb-2">
                            <div class="col-md-6">
                                <strong>Valid Government ID:</strong><br>
                                {{ $gip->valid_govt_id ?: '(N/A)' }}
                            </div>
                            <div class="col-md-6">
                                <strong>ID Number:</strong><br>
                                {{ $gip->id_number ?: '(N/A)' }}
                            </div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-6">
                                <strong>Insurance Beneficiary:</strong><br>
                                {{ $gip->insurance_beneficiary ?: '(N/A)' }}
                            </div>
                            <div class="col-md-6">
                                <strong>Emergency Contact:</strong><br>
                                {{ $gip->emergency_contact ?: '(N/A)' }}
                            </div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-6">
                                <strong>Emergency Contact Number:</strong><br>
                                {{ $gip->ecp_contact_number ?: '(N/A)' }}
                            </div>
                            <div class="col-md-6">
                                <strong>Emergency Contact Address:</strong><br>
                                {{ $gip->ecp_address ?: '(N/A)' }}
                            </div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-6">
                                <strong>College:</strong><br>
                                {{ $gip->college ?: '(N/A)' }}
                            </div>
                            <div class="col-md-6">
                                <strong>Course:</strong><br>
                                {{ $gip->course ?: '(N/A)' }}
                            </div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-6">
                                <strong>Year Graduated:</strong><br>
                                {{ $gip->year_graduated ?: '(N/A)' }}
                            </div>
                            <div class="col-md-6">
                                <strong>High School:</strong><br>
                                {{ $gip->high_school ?: '(N/A)' }}
                            </div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-6">
                                <strong>Elementary School:</strong><br>
                                {{ $gip->elementary_school ?: '(N/A)' }}
                            </div>
                            <div class="col-md-6">
                                <strong>Latest Work Experience:</strong><br>
                                {!! nl2br(e($gip->latest_work_experience)) ?: '(N/A)' !!}
                            </div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-6">
                                <strong>Position:</strong><br>
                                {{ $gip->position ?: '(N/A)' }}
                            </div>
                            <div class="col-md-6">
                                <strong>Period of Engagement:</strong><br>
                                {{ $gip->period_of_engagement ?: '(N/A)' }}
                            </div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-6">
                                <strong>Special Skills:</strong><br>
                                {!! nl2br(e($gip->special_skills)) ?: '(N/A)' !!}
                            </div>
                            <div class="col-md-6">
                                <strong>Achievements:</strong><br>
                                {!! nl2br(e($gip->achievements)) ?: '(N/A)' !!}
                            </div>
                        </div>

                        <div class="text-end mt-3">
                            <button class="btn btn-warning" data-bs-toggle="modal"
                                data-bs-target="#gipModal">
                                <i class="fas fa-edit"></i> Edit GIP Details
                            </button>
                        </div>
                    @else
                        <div class="alert alert-warning">
                            This client has a <strong>GIP</strong> transaction but no GIP details have been recorded.
                        </div>
                        <div class="text-end mt-3">
                            <button class="btn btn-success" data-bs-toggle="modal"
                                data-bs-target="#gipModal">
                                <i class="fas fa-plus"></i> Add GIP Details
                            </button>
                        </div>
                    @endif

                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="gipModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('gip.store', $client) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">
                            {{ $gip ? 'Edit GIP Details' : 'Add GIP Details' }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <input type="hidden" name="client_id" value="{{ $client->id }}">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Valid Government ID</label>
                                <input type="text" name="valid_govt_id" class="form-control"
                                    value="{{ old('valid_govt_id', $gip->valid_govt_id ?? '') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ID Number</label>
                                <input type="text" name="id_number" class="form-control"
                                    value="{{ old('id_number', $gip->id_number ?? '') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Insurance Beneficiary</label>
                                <input type="text" name="insurance_beneficiary" class="form-control"
                                    value="{{ old('insurance_beneficiary', $gip->insurance_beneficiary ?? '') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Emergency Contact</label>
                                <input type="text" name="emergency_contact" class="form-control"
                                    value="{{ old('emergency_contact', $gip->emergency_contact ?? '') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Emergency Contact Number</label>
                                <input type="text" name="ecp_contact_number" class="form-control"
                                    value="{{ old('ecp_contact_number', $gip->ecp_contact_number ?? '') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Emergency Contact Address</label>
                                <input type="text" name="ecp_address" class="form-control"
                                    value="{{ old('ecp_address', $gip->ecp_address ?? '') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">College</label>
                                <input type="text" name="college" class="form-control"
                                    value="{{ old('college', $gip->college ?? '') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Course</label>
                                <input type="text" name="course" class="form-control"
                                    value="{{ old('course', $gip->course ?? '') }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Year Graduated</label>
                                <input type="number" min="1900" max="{{ date('Y') }}"
                                    name="year_graduated" class="form-control"
                                    value="{{ old('year_graduated', $gip->year_graduated ?? '') }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">High School</label>
                                <input type="text" name="high_school" class="form-control"
                                    value="{{ old('high_school', $gip->high_school ?? '') }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Elementary School</label>
                                <input type="text" name="elementary_school" class="form-control"
                                    value="{{ old('elementary_school', $gip->elementary_school ?? '') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Latest Work Experience</label>
                                <textarea name="latest_work_experience" class="form-control" rows="3">{{ old('latest_work_experience', $gip->latest_work_experience ?? '') }}</textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Position</label>
                                <input type="text" name="position" class="form-control"
                                    value="{{ old('position', $gip->position ?? '') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Period of Engagement</label>
                                <input type="text" name="period_of_engagement" class="form-control"
                                    placeholder="Ex. January 2025 - June 2025"
                                    value="{{ old('period_of_engagement', $gip->period_of_engagement ?? '') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Special Skills</label>
                                <textarea name="special_skills" class="form-control" rows="3">{{ old('special_skills', $gip->special_skills ?? '') }}</textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Achievements</label>
                                <textarea name="achievements" class="form-control" rows="3">{{ old('achievements', $gip->achievements ?? '') }}</textarea>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            {{ $gip ? 'Update GIP Details' : 'Save GIP Details' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
