{{-- Prototype-aligned persistent right-side details panel.
     Replaces per-module offcanvas/modal detail views.
     Desktop: fixed 480px right panel. Tablet: ~50vw. Mobile: full-width drawer.
     Single shared component; content loaded via AJAX per module. --}}
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
                <div style="flex:1;min-width:0">
                    <h2 id="detailsPanelTitle" class="text-base font-semibold text-white"></h2>
                    <div class="details-sub" id="detailsSub" style="color:rgba(255,255,255,0.7);font-size:0.8rem;margin-top:2px;"></div>
                    <div class="details-meta" id="detailsMeta" style="display:flex;align-items:center;gap:8px;margin-top:8px;flex-wrap:wrap;"></div>
                </div>
            </div>
        </header>

        <div class="details-actions" id="detailsActions" style="display:flex;flex-wrap:wrap;gap:8px;padding:14px 24px;border-bottom:1px solid var(--color-line-light);background:var(--color-surface);">
            {{-- Action buttons injected per module --}}
        </div>

        <div class="details-body" id="detailsBody" style="flex:1;overflow-y:auto;padding:20px 24px 40px;overscroll-behavior:contain;">
            <div class="p-6 text-center text-ink-muted">
                <p class="mb-0">Click a row to view details.</p>
            </div>
        </div>
    </div>
</div>

<style>
/* ── Details Panel (prototype-aligned) ── */
:root {
    --panel-w: 480px;
}

.details-panel {
    position: fixed;
    top: 0;
    right: 0;
    bottom: 0;
    width: var(--panel-w);
    max-width: 100%;
    background: var(--color-surface);
    z-index: 200;
    display: flex;
    flex-direction: column;
    transform: translateX(100%);
    visibility: hidden;
    transition: transform 0.34s cubic-bezier(0.4, 0, 0.2, 1), visibility 0s linear 0.34s;
    box-shadow: 0 20px 25px -5px rgba(15, 27, 45, 0.08), 0 8px 10px -6px rgba(15, 27, 45, 0.04);
}

.details-panel.open {
    transform: translateX(0);
    visibility: visible;
    transition: transform 0.34s cubic-bezier(0.4, 0, 0.2, 1);
}

.details-panel .details-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(12, 22, 34, 0.45);
    z-index: 150;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.details-panel.open .details-backdrop {
    opacity: 1;
    pointer-events: auto;
}

/* Desktop (≥1024px): panel overlays right edge, table stays fully visible, no backdrop */
@media (min-width: 1024px) {
    .details-panel .details-backdrop { display: none; }
}

/* Tablet (768–1023px): panel ~50vw */
@media (min-width: 768px) and (max-width: 1023px) {
    .details-panel { width: 50vw; }
}

/* Mobile (<768px): full-width drawer. Boundary (767.98px) is kept
   identical to the FilterChips bottom-sheet query so the whole app
   shares one consistent 768px tier: <768 = mobile, >=768 = tablet/desktop. */
@media (max-width: 767.98px) {
    .details-panel {
        left: 0;
        right: 0;
        width: auto;
        max-width: 100vw;
    }
    .details-header { padding: 18px 16px 14px; }
    .details-actions { padding: 12px 16px; }
    .details-body { padding: 18px 16px 36px; }
    /* Full-width drawer: information grids collapse to one column so long
       values wrap safely and text stays readable. */
    .details-grid { grid-template-columns: 1fr; }
}

.details-header {
    background: linear-gradient(135deg, var(--color-navy) 0%, var(--color-navy-light) 100%);
    padding: 22px 24px 18px;
    position: relative;
    flex-shrink: 0;
}

.details-close {
    position: absolute;
    top: 14px;
    right: 14px;
    width: 36px;
    height: 36px;
    border: none;
    border-radius: 8px;
    background: rgba(255,255,255,0.08);
    color: rgba(255,255,255,0.85);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 200ms cubic-bezier(0.4, 0, 0.2, 1);
}
.details-close:hover { background: rgba(255,255,255,0.18); color: #fff; }
.details-close svg { width: 16px; height: 16px; stroke: currentColor; fill: none; stroke-width: 2; }
.details-close:focus-visible { outline: 2px solid var(--color-gold); outline-offset: 2px; }

.details-identity { display: flex; align-items: flex-start; gap: 14px; }

/* Clients panel: photo sits BESIDE the client's name (per requirement #6),
   not above it. The photo-above-name layout applies ONLY to the Edit modal.
   The shared row layout keeps the 64px avatar to the left with the name,
   ID, and category to its right. */
.details-panel[data-module="clients"] .details-header { padding: 22px 24px 18px; }
.details-panel[data-module="clients"] .details-identity {
    flex-direction: row;
    align-items: flex-start;
    text-align: left;
    gap: 14px;
}
.details-panel[data-module="clients"] .details-identity > div { width: auto; flex: 1; min-width: 0; }
.details-panel[data-module="clients"] .details-meta { justify-content: flex-start; }
@media (max-width: 767.98px) {
    .details-panel[data-module="clients"] .details-header { padding: 16px 14px 12px; }
    .details-panel[data-module="clients"] .details-avatar,
    .details-panel[data-module="clients"] .details-avatar-photo,
    .details-panel[data-module="clients"] .details-avatar-initials {
        width: 56px; height: 56px; min-width: 56px; border-radius: 14px;
    }
}
.details-avatar {
    width: 64px; height: 64px; min-width: 64px;
    border-radius: 16px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem; font-weight: 800; color: #fff;
    box-shadow: 0 4px 12px rgba(0,0,0,0.25);
}
.details-identity h2 { color: #fff; font-size: 1.05rem; line-height: 1.25; word-break: break-word; }
.details-sub { color: rgba(255,255,255,0.55); font-size: 0.75rem; margin-top: 2px; }
.details-meta { display: flex; align-items: center; gap: 10px; margin-top: 12px; flex-wrap: wrap; }

.details-actions {
    flex-shrink: 0;
}

.details-actions .btn {
    flex: 1 1 calc(33.33% - 8px);
    min-width: 96px;
}

/* Clients panel: four actions (Add Transaction / Open Full Page / Edit /
   Delete) laid out as a 2×2 grid — Add Transaction + Open Full Page on the
   first row, Edit + Delete on the second — so every action keeps a full-width
   tap target on both desktop drawers and narrow/mobile widths. */
.details-actions-line {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    width: 100%;
}
.details-actions-line > .btn,
.details-actions-line > form {
    flex: 1 1 calc(50% - 4px);
    min-width: 0;
}
.details-actions-line form .btn {
    width: 100%;
    flex: none;
    min-width: 0;
}

.details-body { flex: 1; overflow-y: auto; padding: 20px 24px 40px; overscroll-behavior: contain; min-height: 0; }

.details-section { margin-bottom: 28px; }
.details-section-title {
    display: flex; align-items: center; gap: 8px;
    font-size: 0.72rem; font-weight: 700;
    color: var(--color-ink-muted);
    text-transform: uppercase; letter-spacing: 0.08em;
    margin-bottom: 12px;
}
.details-section-title::after { content: ''; flex: 1; height: 1px; background: var(--color-line-light); }

.details-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 16px; }
.details-field.wide { grid-column: 1 / -1; }
.details-field label {
    display: block;
    font-size: 0.68rem; font-weight: 600;
    color: var(--color-ink-muted);
    text-transform: uppercase; letter-spacing: 0.04em;
    margin-bottom: 2px;
}
.details-field .value {
    font-size: 0.86rem; font-weight: 600;
    color: var(--color-ink);
    word-break: break-word;
}
.details-field .value.muted { font-weight: 500; color: var(--color-ink-secondary); }

.details-note {
    padding: 12px 14px;
    border-radius: var(--radius-control);
    background: rgba(232, 158, 45, 0.06);
    border-left: 3px solid var(--color-gold);
    font-size: 0.82rem;
    color: var(--color-ink-secondary);
    line-height: 1.55;
}

.details-timeline { position: relative; padding-left: 22px; }
.details-timeline::before {
    content: '';
    position: absolute; left: 6px; top: 6px; bottom: 6px;
    width: 2px; background: var(--color-line);
}
.tl-item { position: relative; padding-bottom: 18px; }
.tl-item:last-child { padding-bottom: 0; }
.tl-item::before {
    content: '';
    position: absolute; left: -22px; top: 4px;
    width: 13px; height: 13px;
    border-radius: 50%;
    background: var(--color-surface);
    border: 2.5px solid var(--color-gold);
}
.tl-item h5 { font-size: 0.82rem; font-weight: 600; }
.tl-item p { font-size: 0.78rem; color: var(--color-ink-secondary); margin-top: 1px; }
.tl-item time { font-size: 0.7rem; color: var(--color-ink-muted); display: block; margin-top: 2px; }

.details-doc {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 12px;
    border: 1px solid var(--color-line-light);
    border-radius: var(--radius-control);
    margin-bottom: 8px;
    transition: all 200ms cubic-bezier(0.4, 0, 0.2, 1);
}
.details-doc:hover { border-color: var(--color-line); background: var(--color-surface-hover); }
.doc-icon {
    width: 34px; height: 34px; min-width: 34px;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    background: rgba(15, 27, 45, 0.05); color: var(--color-navy);
}
.doc-icon svg { width: 16px; height: 16px; stroke: currentColor; fill: none; stroke-width: 1.8; }
.details-doc .doc-name { flex: 1; min-width: 0; }
.details-doc .doc-name h5 { font-size: 0.82rem; font-weight: 600; }
.details-doc .doc-name p { font-size: 0.7rem; color: var(--color-ink-muted); }
.doc-more { border: none; background: none; color: var(--color-ink-muted); cursor: pointer; padding: 4px; border-radius: 6px; display: flex; }
.doc-more:hover { color: var(--color-navy); background: var(--color-bg); }
.doc-more svg { width: 15px; height: 15px; stroke: currentColor; fill: none; stroke-width: 2; }

/* Status badges in panel (dark header needs light-on-dark) */
.details-meta .status-badge.active   { background: var(--color-teal); color: #fff; }
.details-meta .status-badge.active .dot { background: #fff; }
.details-meta .status-badge.archived { background: rgba(255,255,255,0.16); color: rgba(255,255,255,0.9); border: 1px solid rgba(255,255,255,0.3); }
.details-meta .status-badge.archived .dot { background: currentColor; }

/* Category as its own distinct line below the ID in the fixed header.
   flex-basis:100% forces a wrap so the category pill sits on its own row,
   with any trailing tag (e.g. household id) flowing onto the line below. */
.details-meta .details-category {
    flex-basis: 100%;
    background: rgba(255,255,255,0.16);
    color: rgba(255,255,255,0.92);
    border: 1px solid rgba(255,255,255,0.28);
}
.details-meta .details-category::before { background: var(--color-gold); }

/* Program tags */
.program-tag {
    display: inline-block;
    padding: 3px 9px;
    border-radius: var(--radius-control);
    font-size: 0.72rem;
    font-weight: 600;
    background: rgba(15, 27, 45, 0.05);
    color: var(--color-gold);
}
.prog-empty { color: var(--color-ink-muted); font-size: 0.85rem; }

/* Keep the panel a true flex column so the header/actions stay fixed while
   the body scrolls, and let long content shrink instead of widening the
   drawer (min-width: 0 on the column allows the overflow-x containers
   inside the body to scroll on their own). */
.details-panel-inner {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-width: 0;
    min-height: 0;
}

/* Identity avatar: the photo or initials placeholder must fit the shell's
   64px rounded avatar box without overflowing the header. */
.details-avatar-photo {
    width: 64px;
    height: 64px;
    min-width: 64px;
    border-radius: 16px;
    object-fit: cover;
    display: block;
}
.details-avatar-initials {
    width: 64px;
    height: 64px;
    min-width: 64px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    font-weight: 800;
    color: #fff;
    background: linear-gradient(135deg, var(--color-gold) 0%, var(--color-gold-light) 100%);
}

/* Accessible collapsible sections (Family Composition / Transactions). */
.details-accordion-toggle {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 0;
    border: none;
    background: none;
    font: inherit;
    letter-spacing: inherit;
    color: inherit;
    cursor: pointer;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}
.details-accordion-toggle:hover { color: var(--color-navy); }
.details-accordion-toggle:focus-visible {
    outline: 2px solid var(--color-gold);
    outline-offset: 2px;
    border-radius: 4px;
}
.details-chevron {
    width: 14px;
    height: 14px;
    stroke: currentColor;
    fill: none;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
    transition: transform 200ms cubic-bezier(0.4, 0, 0.2, 1);
}
.details-accordion-toggle.is-open .details-chevron { transform: rotate(180deg); }
.details-accordion-panel { padding-top: 4px; }

/* Focus trap styles */
.details-panel:focus-within { outline: none; }
</style>