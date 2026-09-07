{{-- Add/Edit Client Modal — Tailwind + Alpine.js (Phase 8). Kept in its own
     partial so Blade compiles its Alpine @directives in isolation. --}}
<style>[x-cloak]{display:none}</style>

<div id="clientFormModal"
     x-data="clientFormModalComponent()"
     x-cloak
     role="dialog"
     aria-modal="true"
     aria-labelledby="cfmTitle"
     aria-describedby="clientFormModalBody"
     class="pointer-events-none fixed inset-0 z-[200]">
    <div x-show="$store.clientFormModal.open"
         x-transition.opacity.duration.200ms
         class="pointer-events-auto absolute inset-0 bg-ink/40"
         aria-hidden="true"></div>

    <div class="pointer-events-none absolute inset-0 flex items-center justify-center overflow-y-auto p-4">
        <div x-show="$store.clientFormModal.open"
             x-ref="dialog"
             x-transition.opacity.duration.200ms
             @keydown.tab.prevent.stop="handleTab($event)"
             class="pointer-events-auto flex w-full max-w-[800px] max-h-[90vh] flex-col rounded-panel bg-surface shadow-pop ring-1 ring-line">
            <div class="flex shrink-0 items-center justify-between gap-2 border-b border-line bg-navy px-[1.25rem] py-[1rem]">
                <div class="min-w-0">
                    <h5 id="cfmTitle" class="mb-0 text-dense font-heading font-semibold text-white" x-text="$store.clientFormModal.title"></h5>
                    <p id="cfmSubtitle" class="mb-0 mt-0.5 text-sm text-white/80" x-text="$store.clientFormModal.subtitle"></p>
                </div>
                <button type="button"
                    class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-btn text-white/70 transition duration-150 ease-standard hover:bg-white/10 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-gold"
                    @click="$store.clientFormModal.hide()"
                    aria-label="Close">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>

            <div id="clientFormModalBody" class="min-h-0 flex-1 overflow-y-auto p-[1.25rem]">
                <div class="flex items-center justify-center py-[2rem] text-ink-muted">
                    <span>Loading form...</span>
                </div>
            </div>

            <div class="flex shrink-0 items-center justify-end gap-2 border-t border-line bg-neutral-100 px-[1.25rem] py-[0.9rem]">
                <button type="button" class="btn-subtle" @click="$store.clientFormModal.hide()">Cancel</button>
                <button type="submit" form="clientForm" class="btn-gold" id="clientFormSubmit" x-text="$store.clientFormModal.submitLabel">Add Client</button>
            </div>
        </div>
    </div>
</div>
