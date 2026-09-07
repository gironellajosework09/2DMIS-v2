{{-- Client Feedback Modal — Tailwind + Alpine.js (Phase 9). A reusable
     INFO / WARNING / ERROR surface ($#clientFeedbackModal) that appears on
     top of the client form modal (duplicate warning, validation errors,
     photo errors, server errors). Kept in its own partial so Blade compiles
     its Alpine @directives in isolation (same rationale as the Phase 8
     client-form-modal partial).

     Contract (parity with the previous Bootstrap modal):
     * Default Bootstrap modal semantics (no backdrop:'static', no
       keyboard:false) → backdrop click AND ESC close the feedback modal.
     * Dynamic title (#clientFeedbackTitle), body (#clientFeedbackBody),
       and footer action area (#clientFeedbackActions) filled by callers.
     * Static "Back to form" footer button closes back to the still-editable
       form modal (masked underneath at a lower z-index).
     * Title highlight 'type' (info/warning/error) maps to a header tint.
     * On open, focus moves into the dialog (first focusable or the close
       button). On close, focus returns to the underlying form's first
       focusable when the form modal is still open.
     * Body scroll stays locked while ANY modal is open; this modal only
       restores overflow when the client form modal is closed too.
     * Imperative bridge preserved: window.showClientFeedback(options). --}}
<style>[x-cloak]{display:none}</style>

<div id="clientFeedbackModal"
     x-data="clientFeedbackModalComponent()"
     x-cloak
     role="dialog"
     aria-modal="true"
     aria-labelledby="clientFeedbackTitle"
     class="pointer-events-none fixed inset-0 z-[210]">
    <div x-show="$store.clientFeedbackModal.open"
         x-transition.opacity.duration.200ms
         @click="$store.clientFeedbackModal.hide()"
         class="pointer-events-auto absolute inset-0 bg-ink/40"
         aria-hidden="true"></div>

    <div class="pointer-events-none absolute inset-0 flex items-center justify-center p-4">
        <div x-show="$store.clientFeedbackModal.open"
             x-ref="dialog"
             x-transition.opacity.duration.200ms
             @keydown.escape="$store.clientFeedbackModal.hide()"
             @keydown.tab.prevent.stop="handleTab($event)"
             class="pointer-events-auto flex w-full max-w-[500px] flex-col rounded-panel bg-surface shadow-pop ring-1 ring-line">
            <div class="flex shrink-0 items-center justify-between gap-2 border-b border-line bg-navy px-[1.25rem] py-[1rem]">
                <h5 id="clientFeedbackTitle" class="text-dense font-heading font-semibold text-white" x-text="$store.clientFeedbackModal.title"></h5>
                <button type="button"
                    class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-btn text-white/70 transition duration-150 ease-standard hover:bg-white/10 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-gold"
                    @click="$store.clientFeedbackModal.hide()"
                    aria-label="Close">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>

            <div id="clientFeedbackBody" class="min-h-0 max-h-[60vh] overflow-y-auto p-[1.25rem] text-dense leading-snug text-ink"></div>

            <div class="flex shrink-0 flex-wrap items-center justify-end gap-2 border-t border-line bg-neutral-100 px-[1.25rem] py-[0.9rem]">
                <div id="clientFeedbackActions" class="flex flex-wrap items-center justify-end gap-[8px]"></div>
                <button type="button" class="btn-navy" @click="$store.clientFeedbackModal.hide()">Back to form</button>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        document.addEventListener('alpine:init', function () {
            if (Alpine.store('clientFeedbackModal')) return;
            Alpine.store('clientFeedbackModal', {
                open: false,
                title: 'Notice',
                type: 'info',
                _onHidden: null,

                show: function (options) {
                    options = options || {};
                    var type = options.type || 'info';
                    this.type = type;
                    this.title = options.title || (type === 'warning'
                        ? 'Review before continuing'
                        : 'Fix these before continuing');
                    this._onHidden = typeof options.onHidden === 'function' ? options.onHidden : null;
                    var body = document.getElementById('clientFeedbackBody');
                    var actions = document.getElementById('clientFeedbackActions');
                    if (body) {
                        body.innerHTML = '';
                        if (options.body) body.appendChild(options.body);
                    }
                    if (actions) {
                        actions.innerHTML = '';
                        if (options.actions) actions.appendChild(options.actions);
                    }
                    document.body.style.overflow = 'hidden';
                    // Return focus to the previously-active element on close
                    // (matches Bootstrap's default focus restoration).
                    this._prevFocus = document.activeElement;
                    this.open = true;
                    Alpine.nextTick(function () {
                        // Move focus into the dialog.
                        var dlg = document.getElementById('clientFeedbackModal');
                        if (!dlg) return;
                        var focusables = dlg.querySelectorAll('button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])');
                        var target = dlg.querySelector('#clientFeedbackActions button');
                        if (!target && focusables.length) target = focusables[0];
                        if (target && target.focus) target.focus();
                    });
                },

                hide: function () {
                    if (!this.open) return;
                    this.open = false;
                    // Only restore body scroll when no other modal layer keeps it
                    // locked (the client form modal drives its own scroll lock).
                    var formStore = Alpine.store('clientFormModal');
                    var formOpen = formStore && formStore.open;
                    if (!formOpen) {
                        document.body.style.overflow = '';
                    }
                    var prev = this._prevFocus;
                    var cb = this._onHidden;
                    this._onHidden = null;
                    this._prevFocus = null;
                    if (prev && typeof prev.focus === 'function' && document.contains(prev)) {
                        prev.focus();
                    }
                    if (typeof cb === 'function') cb();
                }
            });
        });

        window.clientFeedbackModalComponent = function () {
            return {
                handleTab: function (e) {
                    var dlg = this.$refs.dialog;
                    if (!dlg) return;
                    var focusables = dlg.querySelectorAll('button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])');
                    if (!focusables.length) return;
                    var first = focusables[0];
                    var last = focusables[focusables.length - 1];
                    if (e.shiftKey && document.activeElement === first) { last.focus(); }
                    else if (!e.shiftKey && document.activeElement === last) { first.focus(); }
                }
            };
        };

        window.showClientFeedback = function (options) {
            Alpine.store('clientFeedbackModal').show(options || {});
        };
    })();
</script>
