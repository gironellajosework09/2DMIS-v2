{{-- Batch C stub — planned shared partial (analysis §8.5 Filters,
     §8.11 shared foundation list). Adopted by screen batches when a
     filter toolbar wires it in; both existing filter models (Apply+redraw
     and GET-intercept) can feed it the same shape.
     Contract: $activeFilters = array of ['label' => string, 'removeUrl'
     => string]; optional $clearUrl renders a clear-all chip. Removal
     links must point at the SAME query outcome as manually clearing the
     control (presentation only — no query logic here).
     Not scanned by Tailwind yet (app.css allowlist): add an @source line
     for this file in the same change that first includes it. The chip
     classes it uses (.filter-chip*) are static component classes and are
     already part of the built stylesheet. --}}
@if (! empty($activeFilters ?? []))
    <div class="mb-4 flex flex-wrap items-center gap-2">
        <span class="text-micro font-semibold uppercase tracking-caps text-ink-muted">Active filters</span>
        @foreach ($activeFilters as $activeFilter)
            <span class="filter-chip">
                {{ $activeFilter['label'] }}
                <a href="{{ $activeFilter['removeUrl'] }}"
                   class="filter-chip-remove"
                   aria-label="Remove filter: {{ $activeFilter['label'] }}">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3 w-3" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </a>
            </span>
        @endforeach
        @isset($clearUrl)
            <a href="{{ $clearUrl }}" class="text-micro font-semibold text-navy no-underline hover:text-navy-hover">Clear all</a>
        @endisset
    </div>
@endif
