@props(['title' => 'Record Details'])

{{-- Shared record view modal (record-view-modal). Phase 2 of the
     Tailwind/Alpine migration: moved off `bootstrap.Modal`.

     Original Bootstrap contract preserved:
     * Markup surface: `#viewModal` root, `#viewBody` content body, a
       `<h5>` title (fed by the `title` prop), and Close affordances.
     * Consumers (payouts/attendance, unpaid_verifications/index) fetch a
       record, write HTML into `#viewBody` via jQuery `$('#viewBody').html(...)`,
       then open the dialog. They previously called
       `new bootstrap.Modal('#viewModal').show()`.
     * Behaviours preserved: centered ~800px (`modal-lg`) dialog, dark
       backdrop (click-to-close), ESC-to-close, focus moves into the dialog
       on open and is restored to the trigger on close, body scroll-lock
       while open, fade transition, and per-open content freshness (the
       body is replaced by the consumer on every open).

     Compatibility bridge (consumers must stay Alpine-agnostic):
       window.uiViewModal.show()  -> open
       window.uiViewModal.hide()  -> close
     Consumers changed ONLY at the single `bootstrap.Modal(...).show()` call
     site to use `window.uiViewModal.show()`; everything else is untouched.

     This mirrors the Phase 1 confirm-modal pattern: an Alpine store holds
     the shared state and the component exposes the behaviours; focus /
     scroll-lock are managed imperatively (Alpine.nextTick, not $nextTick)
     so callers never touch Alpine internals. --}}

<style>[x-cloak]{display:none}</style>

<div id="viewModal"
     x-data="uiRecordViewModal()"
     x-cloak
     role="dialog"
     aria-modal="true"
     aria-labelledby="viewModalTitle"
     aria-describedby="viewBody"
     class="pointer-events-none fixed inset-0 z-[200]">
    <div x-show="open"
         x-transition.opacity.duration.200ms
         @click="close()"
         class="pointer-events-auto absolute inset-0 bg-ink/40"
         aria-hidden="true"></div>
    <div class="pointer-events-none absolute inset-0 flex items-center justify-center p-4">
        <div x-show="open"
             x-ref="dialog"
             x-transition.opacity.duration.200ms
             @keydown.escape.window="close()"
             @keydown.tab.prevent.stop="handleTab($event)"
             class="pointer-events-auto flex max-h-[85vh] w-full max-w-[800px] flex-col rounded-panel bg-surface shadow-pop ring-1 ring-line">
            <div class="flex items-center justify-between gap-2 border-b border-line p-[1.25rem] pb-3">
                <h5 id="viewModalTitle" class="mb-0 text-dense font-heading font-semibold text-ink">{{ $title }}</h5>
                <button type="button"
                        class="rvm-close inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-btn text-ink-muted transition duration-150 ease-standard hover:bg-surface-hover hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-gold"
                        @click="close()"
                        aria-label="Close">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <div id="viewBody" class="overflow-y-auto p-[1.25rem] text-dense text-ink">Loading...</div>
            <div class="flex items-center justify-end gap-2 border-t border-line p-[1.25rem] pt-3">
                <button type="button" class="btn-subtle" @click="close()">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        document.addEventListener('alpine:init', function () {
            Alpine.store('uiViewModal', {
                open: false,
                _prevFocus: null,
                show: function () {
                    if (this.open) return;
                    this._prevFocus = document.activeElement;
                    document.body.style.overflow = 'hidden';
                    this.open = true;
                    Alpine.nextTick(function () {
                        var dlg = document.getElementById('viewModal');
                        var closeBtn = dlg && dlg.querySelector('.rvm-close');
                        if (closeBtn) closeBtn.focus();
                    });
                },
                hide: function () {
                    if (!this.open) return;
                    this.open = false;
                    document.body.style.overflow = '';
                    if (this._prevFocus && typeof this._prevFocus.focus === 'function') {
                        this._prevFocus.focus();
                    }
                    this._prevFocus = null;
                }
            });
        });

        window.uiRecordViewModal = function () {
            return {
                get open() { return this.$store.uiViewModal.open; },
                close: function () { this.$store.uiViewModal.hide(); },
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

        window.uiViewModal = {
            show: function () { Alpine.store('uiViewModal').show(); },
            hide: function () { Alpine.store('uiViewModal').hide(); }
        };
    })();
</script>
