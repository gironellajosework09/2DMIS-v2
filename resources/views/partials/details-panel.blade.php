{{-- Prototype-aligned persistent right-side details panel.
     Replaces per-module offcanvas/modal detail views.
     Desktop: fixed 480px right panel. Tablet: ~50vw. Mobile: full-width drawer.
     Single shared component; content loaded via AJAX per module.
     All static styling is owned by resources/css/app.css (see the
     "DetailsPanel" section, delimited by MARKER-BEGIN/END: details-panel).
     The 5 inline style="" declarations this shell used to carry were
     consolidated into the .details-* rules there at their rendered values. --}}
<div id="detailsPanel" class="details-panel" role="dialog" aria-modal="true" aria-labelledby="detailsPanelTitle" aria-hidden="true">
    <div class="details-backdrop" id="detailsBackdrop" tabindex="-1" aria-hidden="true"></div>

    <div class="details-panel-inner">
        <header class="details-header">
            <button type="button" class="details-close" id="detailsClose" aria-label="Close details panel">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
            <div class="details-identity">
                <div class="details-avatar" id="detailsAvatar" aria-hidden="true"></div>
                <div>
                    <h2 id="detailsPanelTitle" class="text-base font-semibold text-white"></h2>
                    <div class="details-sub" id="detailsSub"></div>
                    <div class="details-meta" id="detailsMeta"></div>
                </div>
            </div>
        </header>

        <div class="details-actions" id="detailsActions">
            {{-- Action buttons injected per module --}}
        </div>

        <div class="details-body" id="detailsBody">
            <div class="p-6 text-center text-ink-muted">
                <p class="mb-0">Click a row to view details.</p>
            </div>
        </div>
    </div>
</div>