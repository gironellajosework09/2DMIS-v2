{{-- Batch F — shared confirmation dialog (UI_UX_ANALYSIS §8.9 Group 4
     "delete confirm swap"; first real view-side consumers are the
     transactions delete flows, which previously used native confirm()).

     Contract:
     * window.uiConfirm({ title, message, confirmLabel }) → Promise<boolean>
       resolves true ONLY on the confirm button; Cancel / Esc / backdrop
       resolve false (same abort semantics as the native dialog).
     * Declarative form support: <form data-confirm="message"> — the
       submit is intercepted, and on confirm the form is submitted
       natively via HTMLFormElement.submit() (fires no submit event,
       keeps the form's own @csrf POST intact).

     Migration note (Tailwind + Alpine, Phase 1):
     * Alpine store drives the imperative/promise bridge — the shared
       state (open, title, message, labels, pending resolver) lives in
       Alpine.store('uiConfirm'); window.uiConfirm() sets it and returns
       a Promise, so callers stay framework-agnostic and unchanged.
     * uiConfirmDialog() Alpine component owns the Modal's keyboard
     * behaviour (ESC close + a Tab trap between the two actions),
     * mirroring the previous Bootstrap modal; focus-on-open, focus
     * restore and body scroll-lock are handled imperatively by
     * window.uiConfirm()/the store's dismiss() so the framework-agnostic
     * callers never touch Alpine internals.
     * Visual parity with the old Bootstrap modal: centred ~500px dialog
       on a dark backdrop, rounded-panel surface, ring/shadow, body
       scroll lock while open.

     Endpoints, ACL middleware and business rules stay wherever they
     were; this partial changes only the confirmation surface. --}}
<style>[x-cloak]{display:none}</style>

<div id="uiConfirmModal"
     x-data="uiConfirmDialog()"
     x-cloak
     role="dialog"
     aria-modal="true"
     aria-labelledby="uiConfirmTitle"
     aria-describedby="uiConfirmMessage"
     class="pointer-events-none fixed inset-0 z-[200]">
    <div x-show="open"
         x-transition.opacity.duration.200ms
         @click="dismiss(false)"
         class="pointer-events-auto absolute inset-0 bg-ink/40"
         aria-hidden="true"></div>
    <div class="pointer-events-none absolute inset-0 flex items-center justify-center p-4">
        <div x-show="open"
             x-ref="dialog"
             x-transition.opacity.duration.200ms
             @keydown.escape="dismiss(false)"
             @keydown.tab.prevent.stop="handleTab($event)"
             class="pointer-events-auto w-full max-w-[500px] rounded-panel bg-surface p-[1.25rem] shadow-pop ring-1 ring-line">
            <h5 id="uiConfirmTitle" class="mb-[8px] text-dense font-heading font-semibold text-ink" x-text="$store.uiConfirm.title"></h5>
            <p id="uiConfirmMessage" class="mb-0 text-dense leading-snug text-ink-muted" x-text="$store.uiConfirm.message"></p>
            <div class="mt-[1.25rem] flex items-center justify-end gap-2">
                <button type="button" class="btn-subtle" @click="dismiss(false)" x-text="$store.uiConfirm.cancelLabel"></button>
                <button type="button" class="btn-red" id="uiConfirmAccept" @click="dismiss(true)" x-text="$store.uiConfirm.confirmLabel"></button>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        document.addEventListener('alpine:init', function () {
            Alpine.store('uiConfirm', {
                open: false,
                title: 'Are you sure?',
                message: '',
                confirmLabel: 'Confirm',
                cancelLabel: 'Cancel',
                _resolver: null,
                _prevFocus: null,
                show: function (options) {
                    options = options || {};
                    this.title = options.title || 'Are you sure?';
                    this.message = options.message || '';
                    this.confirmLabel = options.confirmLabel || 'Confirm';
                    this.open = true;
                },
                dismiss: function (result) {
                    this.open = false;
                    document.body.style.overflow = '';
                    if (this._prevFocus && typeof this._prevFocus.focus === 'function') {
                        this._prevFocus.focus();
                    }
                    this._prevFocus = null;
                    if (typeof this._resolver === 'function') {
                        var r = this._resolver;
                        this._resolver = null;
                        r(result);
                    }
                }
            });
        });

        window.uiConfirmDialog = function () {
            return {
                get open() { return this.$store.uiConfirm.open; },
                dismiss: function (result) { this.$store.uiConfirm.dismiss(result); },
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

        window.uiConfirm = function (options) {
            return new Promise(function (resolve) {
                var store = Alpine.store('uiConfirm');
                store._resolver = resolve;
                store._prevFocus = document.activeElement;
                document.body.style.overflow = 'hidden';
                store.show(options || {});
                Alpine.nextTick(function () {
                    var dlg = document.getElementById('uiConfirmModal');
                    var accept = dlg && dlg.querySelector('#uiConfirmAccept');
                    if (accept) accept.focus();
                });
            });
        };

        document.addEventListener('submit', function (e) {
            var form = e.target;
            if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm')) {
                return;
            }
            e.preventDefault();
            window.uiConfirm({ message: form.getAttribute('data-confirm'), confirmLabel: 'Delete' })
                .then(function (ok) {
                    if (ok) {
                        HTMLFormElement.prototype.submit.call(form);
                    }
                });
        });
    })();
</script>
