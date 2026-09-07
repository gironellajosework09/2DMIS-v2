{{-- Shared FilterChips component (Phase 2C).
     Companion to resources/js/components/FilterChips.js (delivered to
     public/js/components/FilterChips.js by the Vite build's copy step).

     Contract:
       $filterChips = [
         'id'         => string,             // component id (passed to FilterChips.init)
         'categories' => [ [
           'key'      => string,             // category key (== feed generat param unless overridden)
           'label'    => string,
           'searchable' => bool,
           'feedParam'=> string,             // server/feed param name
           'dependsOn'  => string|null,      // cascade: parent category key (e.g. 'municipality')
           'options'  => [ ['value'=>, 'label'=>, 'muni'=>?], ... ],  // ACL-scoped server-side
           'selected' => [ ...values... ],   // from the URL query (controller-provided)
         ], ... ],
         'dateRanges' => [ [
           'key'        => string,
           'label'      => string,
           'startParam' => string,           // existing feed start param
           'endParam'   => string,           // existing feed end param
           'start'      => string,           // '' or a date
           'end'        => string,
         ], ... ],
       ];

     Renders: applied-chip row (hydrated by JS), the Filter button with a
     live active-count badge, and the popover menu with searchable checkbox
     options + date-range inputs. The JS owns selection/URL/none; the module
     provides FilterChips.init(...).onApply to reload its DataTables feed.

     TIP: because this partial emits Tailwind utility classes, the source
     allowlist in resources/css/app.css must list this file (add an @source
     line in the same change that first includes it).
--}}
@php
    $fcId = e($filterChips['id'] ?? 'filters');
    $fcCategories = $filterChips['categories'] ?? [];
    $fcDateRanges = $filterChips['dateRanges'] ?? [];
    $fcActive = collect($fcCategories)->reduce(function ($carry, $cat) {
        return $carry + count($cat['selected'] ?? []);
    }, 0);
    foreach ($fcDateRanges as $dr) {
        if (! empty($dr['start']) || ! empty($dr['end'])) {
            $fcActive++;
        }
    }
@endphp

<div class="filter-chips" data-filter-host="{{ $fcId }}" data-filter-id="{{ $fcId }}">
    {{-- Applied chips row (live-rendered by FilterChips.js; sr-only live region) --}}
    <div class="filter-chips-row" data-filter-chips aria-live="polite" aria-relevant="additions text"></div>

    <div class="filter-chips-toolbar">
        <button type="button" class="filter-group-toggle" data-filter-toggle
                aria-haspopup="dialog" aria-expanded="false">
            <svg viewBox="0 0 24 24" aria-hidden="true" class="filter-toggle-icon">
                <path d="M3 6h18M6 12h12M10 18h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <span>Filters</span>
            <span class="filter-count-badge" data-filter-count @if($fcActive === 0) hidden @endif>{{ $fcActive }}</span>
        </button>
        <button type="button" class="filter-clear-all" data-filter-clear-all
                @if($fcActive === 0) hidden @endif>Clear All</button>
    </div>

    <div class="filter-multi-menu" data-filter-menu role="dialog" aria-modal="true"
         aria-label="Filters" hidden>
        <div class="filter-multi-scroll">
            @foreach ($fcCategories as $cat)
                @php
                    $catKey = $cat['key'];
                    $catLabel = $cat['label'];
                    $catFeed = $cat['feedParam'] ?? $catKey;
                    $catSearchable = ! empty($cat['searchable']);
                    $catDepends = $cat['dependsOn'] ?? null;
                    $catSelected = $cat['selected'] ?? [];
                @endphp
                <section class="filter-multi" data-filter-cat="{{ $catKey }}"
                         data-filter-feedparam="{{ $catFeed }}"
                         @if($catDepends) data-filter-depends="{{ $catDepends }}" @endif>
                    <header class="filter-multi-head">
                        <span class="filter-multi-title">{{ $catLabel }}</span>
                    </header>
                    @if ($catSearchable)
                        <input type="search" class="filter-multi-search" data-filter-search="{{ $catKey }}"
                               placeholder="Search {{ $catLabel }}" aria-label="Search {{ $catLabel }}"
                               autocomplete="off">
                    @endif
                    <div class="filter-multi-options" data-filter-options="{{ $catKey }}">
                        @foreach ($cat['options'] as $opt)
                            @php
                                $checked = in_array((string) $opt['value'], array_map('strval', $catSelected), true);
                            @endphp
                            <label class="filter-check" data-filter-option
                                   data-filter-cat="{{ $catKey }}"
                                   data-filter-value="{{ $opt['value'] }}"
                                   data-filter-label="{{ $opt['label'] }}"
                                   @if(isset($opt['muni'])) data-filter-muni="{{ $opt['muni'] }}" @endif>
                                <input type="checkbox"
                                       data-filter-check="{{ $catKey }}|{{ $opt['value'] }}"
                                       @if($checked) checked @endif>
                                <span class="filter-check-label">{{ $opt['label'] }}</span>
                            </label>
                        @endforeach
                    </div>
                    <div class="filter-multi-footer">
                        <button type="button" class="filter-clear-cat" data-filter-clear-cat="{{ $catKey }}"
                                @if (count($catSelected) === 0) hidden @endif>Clear</button>
                    </div>
                </section>
            @endforeach

            @foreach ($fcDateRanges as $dr)
                @php
                    $drKey = $dr['key'];
                    $drLabel = $dr['label'];
                    $drStartParam = $dr['startParam'] ?? ($drKey.'_start');
                    $drEndParam = $dr['endParam'] ?? ($drKey.'_end');
                    $drStart = $dr['start'] ?? '';
                    $drEnd = $dr['end'] ?? '';
                @endphp
                <section class="filter-multi filter-date" data-filter-datecat="{{ $drKey }}"
                         data-filter-startparam="{{ $drStartParam }}"
                         data-filter-endparam="{{ $drEndParam }}">
                    <header class="filter-multi-head">
                        <button type="button" class="filter-clear-cat" data-filter-date-clear="{{ $drKey }}"
                                @if(empty($drStart) && empty($drEnd)) hidden @endif>Clear</button>
                        <span class="filter-multi-title">{{ $drLabel }}</span>
                    </header>
                    <div class="filter-date-fields">
                        <label class="filter-date-field">
                            <span class="filter-date-label">From</span>
                            <input type="date" class="filter-date-input"
                                   data-filter-date-start="{{ $drKey }}" value="{{ $drStart }}">
                        </label>
                        <span class="filter-date-arrow" aria-hidden="true">→</span>
                        <label class="filter-date-field">
                            <span class="filter-date-label">To</span>
                            <input type="date" class="filter-date-input"
                                   data-filter-date-end="{{ $drKey }}" value="{{ $drEnd }}">
                        </label>
                    </div>
                </section>
            @endforeach
        </div>
    </div>
</div>
