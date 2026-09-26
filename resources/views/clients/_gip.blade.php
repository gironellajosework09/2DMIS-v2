<style>[x-cloak]{display:none}</style>

@if ($hasGipTransaction)
    {{-- Single Alpine scope owns both the accordion toggle and the GIP modal.
         The accordion trigger buttons call openModal() to show the form;
         the modal close/cancel/backdrop/ESC all call closeModal(). --}}
    <div x-data="gipModal()">

        {{-- ── ACCORDION (Phase 6 — Alpine x-collapse) ────────────── --}}
        <div class="accordion mt-[12px]">
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingGIP">
                    <button class="accordion-button font-bold" type="button"
                        :class="{ 'accordion-open': accordionOpen }"
                        @click="accordionOpen = !accordionOpen"
                        :aria-expanded="accordionOpen.toString()"
                        aria-controls="collapseGIP">
                        GIP Details
                    </button>
                </h2>

                <div id="collapseGIP" class="accordion-collapse"
                    x-show="accordionOpen" x-collapse
                    aria-labelledby="headingGIP">
                    <div class="accordion-body p-[1rem]">

                        @if ($gip)
                            <dl class="m-0 grid grid-cols-1 gap-x-[16px] gap-y-[10px] sm:grid-cols-2">
                                @foreach ([
                                    'Valid Government ID' => $gip->valid_govt_id ?: '(N/A)',
                                    'ID Number' => $gip->id_number ?: '(N/A)',
                                    'Insurance Beneficiary' => $gip->insurance_beneficiary ?: '(N/A)',
                                    'Emergency Contact' => $gip->emergency_contact ?: '(N/A)',
                                    'Emergency Contact Number' => $gip->ecp_contact_number ?: '(N/A)',
                                    'Emergency Contact Address' => $gip->ecp_address ?: '(N/A)',
                                    'College' => $gip->college ?: '(N/A)',
                                    'Course' => $gip->course ?: '(N/A)',
                                    'Year Graduated' => $gip->year_graduated ?: '(N/A)',
                                    'High School' => $gip->high_school ?: '(N/A)',
                                    'Elementary School' => $gip->elementary_school ?: '(N/A)',
                                    'Latest Work Experience' => $gip->latest_work_experience ?: '(N/A)',
                                    'Position' => $gip->position ?: '(N/A)',
                                    'Period of Engagement' => $gip->period_of_engagement ?: '(N/A)',
                                    'Special Skills' => $gip->special_skills ?: '(N/A)',
                                    'Achievements' => $gip->achievements ?: '(N/A)',
                                ] as $gipLabel => $gipValue)
                                    <div class="min-w-0">
                                        <dt class="ui-micro-label">{{ $gipLabel }}</dt>
                                        {{-- whitespace-pre-line renders stored newlines without nl2br,
                                             so the value stays escaped by {{ }} --}}
                                        <dd class="m-0 whitespace-pre-line text-dense text-ink">{{ $gipValue }}</dd>
                                    </div>
                                @endforeach
                            </dl>

                            <div class="mt-[12px] text-right">
                                <button class="btn-navy" @click="openModal()">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg>
                                    Edit GIP Details
                                </button>
                            </div>
                        @else
                            <p class="ui-notice m-0">
                                <span aria-hidden="true">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 h-4 w-4 shrink-0"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                </span>
                                <span>This client has a <strong>GIP</strong> transaction but no GIP details have been recorded.</span>
                            </p>
                            <div class="mt-[12px] text-right">
                                <button class="btn-gold" @click="openModal()">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                    Add GIP Details
                                </button>
                            </div>
                        @endif

                    </div>
                </div>
            </div>
        </div>

        {{-- ── GIP MODAL (Alpine.js — Phase 7) ─────────────────────
             Form posts to the existing GIP store endpoint with
             unchanged field names, CSRF, and validation contract. --}}
        <div x-show="modalOpen"
             x-cloak
             x-transition.opacity.duration.200ms
             @keydown.escape.window="modalOpen && closeModal()"
             role="dialog"
             aria-modal="true"
             aria-labelledby="gipModalTitle"
             class="pointer-events-none fixed inset-0 z-[200]">
            {{-- Backdrop --}}
            <div x-show="modalOpen"
                 x-transition.opacity.duration.200ms
                 @click="closeModal()"
                 class="pointer-events-auto absolute inset-0 bg-ink/40"
                 aria-hidden="true"></div>

            {{-- Dialog --}}
            <div class="pointer-events-none absolute inset-0 flex items-center justify-center p-4">
                <div x-ref="gipDialog"
                     x-show="modalOpen"
                     x-transition.opacity.duration.200ms
                     @keydown.tab.prevent.stop="handleTab($event)"
                     class="pointer-events-auto flex w-full max-w-[800px] flex-col overflow-hidden rounded-panel bg-surface shadow-pop ring-1 ring-line">
                    <form method="POST" action="{{ route('gip.store', $client) }}">
                        @csrf

                        <div class="flex items-center justify-between gap-2 border-b border-line p-[1.25rem] pb-3">
                            <h5 id="gipModalTitle" class="mb-0 text-dense font-heading font-semibold text-ink">
                                {{ $gip ? 'Edit GIP Details' : 'Add GIP Details' }}
                            </h5>
                            <button type="button"
                                class="gip-modal-close inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-btn text-ink-muted transition duration-150 ease-standard hover:bg-surface-hover hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-gold"
                                @click="closeModal()"
                                aria-label="Close">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            </button>
                        </div>

                        <div class="scrollbars-subtle max-h-[70vh] overflow-y-auto p-[1.25rem]">
                            <input type="hidden" name="client_id" value="{{ $client->id }}">

                            <div class="grid grid-cols-1 gap-[12px] md:grid-cols-2">
                                <div>
                                    <label class="field-label" for="gip_valid_govt_id">Valid Government ID</label>
                                    <input type="text" name="valid_govt_id" id="gip_valid_govt_id" class="form-control"
                                        value="{{ old('valid_govt_id', $gip->valid_govt_id ?? '') }}">
                                </div>
                                <div>
                                    <label class="field-label" for="gip_id_number">ID Number</label>
                                    <input type="text" name="id_number" id="gip_id_number" class="form-control"
                                        value="{{ old('id_number', $gip->id_number ?? '') }}">
                                </div>
                                <div>
                                    <label class="field-label" for="gip_insurance_beneficiary">Insurance Beneficiary</label>
                                    <input type="text" name="insurance_beneficiary" id="gip_insurance_beneficiary" class="form-control"
                                        value="{{ old('insurance_beneficiary', $gip->insurance_beneficiary ?? '') }}">
                                </div>
                                <div>
                                    <label class="field-label" for="gip_emergency_contact">Emergency Contact</label>
                                    <input type="text" name="emergency_contact" id="gip_emergency_contact" class="form-control"
                                        value="{{ old('emergency_contact', $gip->emergency_contact ?? '') }}">
                                </div>
                                <div>
                                    <label class="field-label" for="gip_ecp_contact_number">Emergency Contact Number</label>
                                    <input type="text" name="ecp_contact_number" id="gip_ecp_contact_number" class="form-control"
                                        value="{{ old('ecp_contact_number', $gip->ecp_contact_number ?? '') }}">
                                </div>
                                <div>
                                    <label class="field-label" for="gip_ecp_address">Emergency Contact Address</label>
                                    <input type="text" name="ecp_address" id="gip_ecp_address" class="form-control"
                                        value="{{ old('ecp_address', $gip->ecp_address ?? '') }}">
                                </div>
                                <div>
                                    <label class="field-label" for="gip_college">College</label>
                                    <input type="text" name="college" id="gip_college" class="form-control"
                                        value="{{ old('college', $gip->college ?? '') }}">
                                </div>
                                <div>
                                    <label class="field-label" for="gip_course">Course</label>
                                    <input type="text" name="course" id="gip_course" class="form-control"
                                        value="{{ old('course', $gip->course ?? '') }}">
                                </div>
                            </div>

                            <div class="mt-[12px] grid grid-cols-1 gap-[12px] md:grid-cols-3">
                                <div>
                                    <label class="field-label" for="gip_year_graduated">Year Graduated</label>
                                    <input type="number" min="1900" max="{{ date('Y') }}"
                                        name="year_graduated" id="gip_year_graduated" class="form-control"
                                        value="{{ old('year_graduated', $gip->year_graduated ?? '') }}">
                                </div>
                                <div>
                                    <label class="field-label" for="gip_high_school">High School</label>
                                    <input type="text" name="high_school" id="gip_high_school" class="form-control"
                                        value="{{ old('high_school', $gip->high_school ?? '') }}">
                                </div>
                                <div>
                                    <label class="field-label" for="gip_elementary_school">Elementary School</label>
                                    <input type="text" name="elementary_school" id="gip_elementary_school" class="form-control"
                                        value="{{ old('elementary_school', $gip->elementary_school ?? '') }}">
                                </div>
                            </div>

                            <div class="mt-[12px] grid grid-cols-1 gap-[12px] md:grid-cols-2">
                                <div>
                                    <label class="field-label" for="gip_latest_work_experience">Latest Work Experience</label>
                                    <textarea name="latest_work_experience" id="gip_latest_work_experience" class="form-control" rows="3">{{ old('latest_work_experience', $gip->latest_work_experience ?? '') }}</textarea>
                                </div>
                                <div>
                                    <label class="field-label" for="gip_position">Position</label>
                                    <input type="text" name="position" id="gip_position" class="form-control"
                                        value="{{ old('position', $gip->position ?? '') }}">
                                </div>
                                <div>
                                    <label class="field-label" for="gip_period_of_engagement">Period of Engagement</label>
                                    <input type="text" name="period_of_engagement" id="gip_period_of_engagement" class="form-control"
                                        placeholder="Ex. January 2025 - June 2025"
                                        value="{{ old('period_of_engagement', $gip->period_of_engagement ?? '') }}">
                                </div>
                                <div>
                                    <label class="field-label" for="gip_special_skills">Special Skills</label>
                                    <textarea name="special_skills" id="gip_special_skills" class="form-control" rows="3">{{ old('special_skills', $gip->special_skills ?? '') }}</textarea>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="field-label" for="gip_achievements">Achievements</label>
                                    <textarea name="achievements" id="gip_achievements" class="form-control" rows="3">{{ old('achievements', $gip->achievements ?? '') }}</textarea>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-2 border-t border-line p-[1.25rem] pt-3">
                            <button type="button" class="btn-subtle" @click="closeModal()">Cancel</button>
                            <button type="submit" class="btn-navy">
                                {{ $gip ? 'Update GIP Details' : 'Save GIP Details' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endif

<script>
    (function () {
        window.gipModal = function () {
            return {
                accordionOpen: false,
                modalOpen: false,
                _prevFocus: null,

                openModal: function () {
                    this._prevFocus = document.activeElement;
                    document.body.style.overflow = 'hidden';
                    this.modalOpen = true;
                    this.$nextTick(function () {
                        var dlg = this.$refs.gipDialog;
                        if (!dlg) return;
                        var first = dlg.querySelector('input:not([type=hidden]), textarea, select, button:not(.gip-modal-close)');
                        if (first) first.focus();
                    }.bind(this));
                },

                closeModal: function () {
                    this.modalOpen = false;
                    document.body.style.overflow = '';
                    if (this._prevFocus && typeof this._prevFocus.focus === 'function') {
                        this._prevFocus.focus();
                    }
                    this._prevFocus = null;
                },

                handleTab: function (e) {
                    var dlg = this.$refs.gipDialog;
                    if (!dlg) return;
                    var focusables = dlg.querySelectorAll('button:not([disabled]), [href], input:not([disabled]):not([type=hidden]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])');
                    if (!focusables.length) return;
                    var first = focusables[0];
                    var last = focusables[focusables.length - 1];
                    if (e.shiftKey && document.activeElement === first) { last.focus(); }
                    else if (!e.shiftKey && document.activeElement === last) { first.focus(); }
                }
            };
        };
    })();
</script>

<script>
    (function () {
        'use strict';
        // UX polish: when the details panel is open, submitting Add/Edit GIP
        // keeps the user inside the panel instead of causing a full-page
        // navigation to the redirect target. The full-page flow is untouched
        // (native POST + redirect + flash). GipController is NOT modified —
        // it still redirects; with Accept: application/json the fetch follows
        // that redirect, so response.ok is treated as success and the panel is
        // reloaded with the saved row (the client's GIP accordion state that
        // was open stays open after the reload).
        var form = document.querySelector('form[action*="/gip"]');
        if (!form) return;

        form.addEventListener('submit', function (ev) {
            var panelApi = window.DetailsPanel;
            if (!panelApi || !panelApi.isOpen || !panelApi.isOpen()) return;

            var accordionBtn = document.querySelector('#headingGIP .accordion-button');
            var wasOpen = accordionBtn ? accordionBtn.getAttribute('aria-expanded') === 'true' : false;
            var submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) submitBtn.disabled = true;
            ev.preventDefault();

            function notifyError(message) {
                if (window.notify && typeof window.notify === 'function') {
                    window.notify({ type: 'error', title: 'Could not save GIP details', message: message });
                }
            }

            fetch(form.getAttribute('action'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: new FormData(form)
            })
                .then(function (r) {
                    if (!r.ok) {
                        notifyError('Please check the form and try again.');
                        if (submitBtn) submitBtn.disabled = false;
                        return;
                    }
                    var entity = panelApi.getCurrentEntity();
                    var clientId = entity && entity.id;
                    if (!clientId) {
                        var m = (form.getAttribute('action') || '').match(/\/clients\/(\d+)/);
                        clientId = m ? m[1] : null;
                    }
                    if (!clientId) {
                        if (window.notify && typeof window.notify === 'function') {
                            window.notify({ type: 'success', title: 'Success', message: 'GIP details saved.' });
                        }
                        if (submitBtn) submitBtn.disabled = false;
                        return;
                    }
                    panelApi.load(entity.module || 'clients', clientId, {
                        url: '/clients/' + clientId + '?panel=1',
                        onSuccess: function () {
                            if (wasOpen) {
                                var toggle = document.querySelector('#headingGIP .accordion-button');
                                if (toggle && toggle.getAttribute('aria-expanded') !== 'true') toggle.click();
                            }
                        }
                    });
                    if (submitBtn) submitBtn.disabled = false;
                })
                .catch(function () {
                    notifyError('A network error occurred.');
                    if (submitBtn) submitBtn.disabled = false;
                });
        });
    })();
</script>
