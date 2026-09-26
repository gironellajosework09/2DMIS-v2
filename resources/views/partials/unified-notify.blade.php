{{-- Shared unified notification stack (Phase 23). ONE bottom-right stack across
     the whole app, four semantic types (info / success / warning / error), each
     drawn from the same component geometry + token icon system — no Bootstrap
     Toast JS, no per-screen toast stacks, no per-type implementations.

     Contract:
     * window.notify(options) — options: { type, title, message, actions,
       persistent }. type ∈ info|success|warning|error (default info).
       actions = array of { label, className(optional), onClick } — optional,
       rendered when present. persistent=true (or an actionable warning) disables
       auto-dismiss; otherwise info/success/error auto-dismiss after a delay.
     * Warnings that carry decisions (duplicate warning etc.) are ALWAYS
       persistent — never auto-dismissed, so an important choice can't vanish.
     * Stacking: toasts append top-down in one fixed bottom-right column; new
       toasts appear on top; each is individually dismissible via its Close
       button. No overlap, no position fights — single container owns geometry.
     * Positioning: fixed, viewport-anchored, bottom-right (page corner route —
       `bottom-[24px] right-[24px]` on desktop/tablet; on narrow screens stays
       in-viewport with standard horizontal margins). NOT relative to the
       trigger element and NOT a per-container float.
     * Icons per type (semantic accent on the same 24px token ring):
         info    → ring = teal, icon  = info circle
         success → ring = teal, icon  = check
         warning → ring = amber, icon  = alert triangle
         error   → ring = red,   icon  = X / octagon

     The store is created lazily on first use and the stack container is
     created once; repeated calls push onto the same stack. Guarded against
     redeclaration (safe to include from any layout/screen). --}}

@once
    <div id="unifiedNotifyStack"
         x-data="unifiedNotifyComponent()"
         class="pointer-events-none fixed bottom-[24px] left-[24px] right-[24px] ml-auto z-[1100] flex w-auto max-w-[420px] flex-col items-end gap-[10px]"
         aria-live="polite"
         aria-atomic="false">
        {{-- The renderer that draws the store's items. Every toast is the
             same card; the accent ring swaps per type and each close button
             removes only its own toast via the store's dismiss(). --}}
        <template x-for="item in $store.unifiedNotify.items" :key="item.id">
            <div class="toast pointer-events-auto flex w-full max-w-[420px] items-start gap-[12px] rounded-panel bg-surface p-[1rem] shadow-pop ring-1 ring-line"
                 x-transition.opacity.duration.250ms>
                <span class="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-pill"
                      :class="ringFor(item.type)"
                      aria-hidden="true"
                      x-html="iconFor(item.type)"></span>
                <div class="min-w-0 flex-1 text-dense leading-snug text-ink">
                    <template x-if="item.title">
                        <div class="mb-0.5 font-semibold" x-text="item.title"></div>
                    </template>
                    <div x-text="item.message"></div>
                </div>
                <template x-if="item.actions && item.actions.length">
                    <div class="flex shrink-0 flex-col items-center gap-[6px] self-center">
                        <template x-for="action in item.actions" :key="action.label">
                            <button type="button"
                                    class="btn btn-sm"
                                    :class="action.className || 'btn-navy'"
                                    @click.stop="action.onClick(item); $store.unifiedNotify.dismiss(item.id)"
                                    x-text="action.label"></button>
                        </template>
                    </div>
                </template>
                <button type="button" class="btn-close shrink-0" aria-label="Close"
                        @click.stop="$store.unifiedNotify.dismiss(item.id)"></button>
            </div>
        </template>
    </div>

    <script>
        (function () {
            'use strict';

            var ICONS = {
                info: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
                success: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>',
                warning: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5" aria-hidden="true"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
                error: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>'
            };

            function typeRing(type) {
                if (type === 'warning') return 'bg-amber/[0.12] text-amber';
                if (type === 'error') return 'bg-red/[0.12] text-red';
                return 'bg-teal/[0.12] text-teal';
            }

            function ensureStore() {
                if (!window.Alpine) return false;
                if (!Alpine.store('unifiedNotify')) {
                    Alpine.store('unifiedNotify', {
                        open: false,
                        items: [],
                        _timer: null,
                        notify: function (options) {
                            options = options || {};
                            var type = ['info', 'success', 'warning', 'error'].indexOf(options.type) !== -1
                                ? options.type : 'info';
                            var persistent = !!options.persistent
                                || (type === 'warning');
                            var item = {
                                id: 'uninv-' + Date.now() + '-' + (Math.random() * 1e6 | 0),
                                type: type,
                                title: options.title || '',
                                message: options.message || '',
                                actions: options.actions || [],
                                persistent: persistent
                            };
                            this.items.push(item);
                            this.open = true;
                            var self = this;
                            if (!persistent) {
                                var delay = type === 'error' ? 7000 : 4000;
                                clearTimeout(this._timer);
                                this._timer = setTimeout(function () {
                                    self.dismiss(item.id);
                                }, delay);
                            }
                            // Re-raise the aria-live announcement for stacking
                            // live regions inside one container.
                            var stack = document.getElementById('unifiedNotifyStack');
                            if (stack) {
                                stack.setAttribute('aria-atomic', 'false');
                            }
                        },
                        dismiss: function (id) {
                            var found = -1;
                            for (var i = 0; i < this.items.length; i++) {
                                if (this.items[i].id === id) { found = i; break; }
                            }
                            if (found === -1) return;
                            this.items.splice(found, 1);
                            if (!this.items.length) this.open = false;
                        }
                    });
                }
                return true;
            }

            window.unifiedNotifyComponent = function () {
                return {
                    iconFor: function (type) {
                        return ICONS[type] || ICONS.info;
                    },
                    ringFor: function (type) {
                        return typeRing(type);
                    }
                };
            };

            // Imperative API consumed by controllers' fetch paths and any
            // screen that previously hand-rolled its own toast stack.
            window.notify = function (options) {
                if (!ensureStore()) return;
                Alpine.store('unifiedNotify').notify(options);
            };

            document.addEventListener('alpine:init', function () {
                ensureStore();
            });
        })();
    </script>
@endonce
