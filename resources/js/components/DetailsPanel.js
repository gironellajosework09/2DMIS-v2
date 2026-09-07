/* 2DMIS v2 — Shared Details Panel Controller
   Prototype-aligned: single reusable panel for all modules.
   Desktop: 480px right panel. Tablet: ~50vw. Mobile: full-width drawer.
   Focus management, ESC/backdrop/close, scroll lock, ARIA. */

const DetailsPanel = (function () {
    'use strict';

    let panelEl = null;
    let backdropEl = null;
    let closeBtn = null;
    let bodyEl = null;
    let titleEl = null;
    let avatarEl = null;
    let subEl = null;
    let metaEl = null;
    let actionsEl = null;
    let lastFocusedElement = null;
    let scrollLockCount = 0;
    let currentModule = null;
    let currentEntityId = null;

    const SELECTORS = {
        panel: '#detailsPanel',
        backdrop: '#detailsBackdrop',
        close: '#detailsClose',
        body: '#detailsBody',
        title: '#detailsPanelTitle',
        avatar: '#detailsAvatar',
        sub: '#detailsSub',
        meta: '#detailsMeta',
        actions: '#detailsActions',
    };

    function getElements() {
        panelEl = panelEl || document.querySelector(SELECTORS.panel);
        backdropEl = backdropEl || document.querySelector(SELECTORS.backdrop);
        closeBtn = closeBtn || document.querySelector(SELECTORS.close);
        bodyEl = bodyEl || document.querySelector(SELECTORS.body);
        titleEl = titleEl || document.querySelector(SELECTORS.title);
        avatarEl = avatarEl || document.querySelector(SELECTORS.avatar);
        subEl = subEl || document.querySelector(SELECTORS.sub);
        metaEl = metaEl || document.querySelector(SELECTORS.meta);
        actionsEl = actionsEl || document.querySelector(SELECTORS.actions);
    }

    function lockScroll() {
        scrollLockCount++;
        if (scrollLockCount === 1) {
            document.body.classList.add('no-scroll');
            document.body.style.overflow = 'hidden';
        }
    }

    function unlockScroll() {
        scrollLockCount = Math.max(0, scrollLockCount - 1);
        if (scrollLockCount === 0) {
            document.body.classList.remove('no-scroll');
            document.body.style.overflow = '';
        }
    }

    function setContent({ title, sub, avatarHtml, metaHtml, actionsHtml, bodyHtml }) {
        getElements();
        if (titleEl) titleEl.textContent = title || '';
        if (subEl) subEl.innerHTML = sub || '';
        if (avatarEl) avatarEl.innerHTML = avatarHtml || '';
        if (metaEl) metaEl.innerHTML = metaHtml || '';
        if (actionsEl) actionsEl.innerHTML = actionsHtml || '';
        if (bodyEl) bodyEl.innerHTML = bodyHtml || '<div class="p-6 text-center text-ink-muted"><p class="mb-0">Click a row to view details.</p></div>';
    }

    function open() {
        getElements();
        if (!panelEl) return;

        lastFocusedElement = document.activeElement;

        panelEl.classList.add('open');
        panelEl.setAttribute('aria-hidden', 'false');
        lockScroll();

        requestAnimationFrame(() => {
            if (closeBtn) closeBtn.focus();
        });
    }

    function close(returnFocus = true) {
        getElements();
        if (!panelEl || !panelEl.classList.contains('open')) return;

        panelEl.classList.remove('open');
        panelEl.setAttribute('aria-hidden', 'true');
        panelEl.removeAttribute('data-module');
        unlockScroll();

        if (returnFocus && lastFocusedElement && lastFocusedElement.isConnected) {
            lastFocusedElement.focus();
        }

        currentModule = null;
        currentEntityId = null;
    }

    function load(module, entityId, options = {}) {
        const { url, method = 'GET', onSuccess, onError } = options;

        // Panel content must always be fetched as an HTML GET view. Never let
        // a POST JSON DataTables feed be handed to the panel as HTML (this
        // used to render "No detail content available." instead of failing).
        if (method !== 'GET') {
            if (typeof onError === 'function') {
                onError(new Error('DetailsPanel only supports GET HTML panel loads'));
            }
            return;
        }

        currentModule = module;
        currentEntityId = entityId;

        getElements();
        // Tag the shell with the active module so CSS can scope header layout
        // per module (e.g. clients stacks the photo above the name) without
        // affecting other modules' shared details headers.
        if (panelEl) panelEl.setAttribute('data-module', module);

        setContent({ bodyHtml: '<div class="p-6 text-center text-ink-muted"><div class="spinner-border text-secondary" role="status"></div><p class="mt-3 mb-0">Loading details…</p></div>' });
        open();

        const fetchUrl = url || getDefaultUrl(module, entityId);

        fetch(fetchUrl, {
            method,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'text/html',
            },
            credentials: 'same-origin',
        })
            .then(response => {
                if (!response.ok) throw new Error('Failed to load details');
                return response.text();
            })
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');

                // Extract data from the panel-compatible response
                const title = doc.querySelector('[data-panel-title]')?.textContent || '';
                const sub = doc.querySelector('[data-panel-sub]')?.innerHTML || '';
                const avatar = doc.querySelector('[data-panel-avatar]')?.innerHTML || '';
                const meta = doc.querySelector('[data-panel-meta]')?.innerHTML || '';
                const actions = doc.querySelector('[data-panel-actions]')?.innerHTML || '';
                const body = doc.querySelector('[data-panel-body]')?.innerHTML || '';

                // Fallback: if no data-panel-body found, check for common content containers
                // but NEVER fall back to doc.body.innerHTML (which would dump entire HTML)
                let finalBody = body;
                if (!finalBody) {
                    // Try common content selectors as last resort
                    const contentEl = doc.querySelector('[data-panel-body], .data-card, .panel-content, main, .content');
                    if (contentEl) {
                        finalBody = contentEl.innerHTML;
                    } else {
                        finalBody = '<div class="p-6 text-center text-ink-muted">No detail content available.</div>';
                    }
                }

                setContent({ title, sub, avatarHtml: avatar, metaHtml: meta, actionsHtml: actions, bodyHtml: finalBody });

                executeScripts(doc);

                if (onSuccess) onSuccess(doc);
            })
            .catch(err => {
                setContent({ bodyHtml: '<div class="p-6 text-center text-danger">Failed to load details.</div>' });
                if (onError) onError(err);
            });
    }

    function getDefaultUrl(module, entityId) {
        let variant = document.body.dataset.payoutVariant || '';
        const routes = {
            clients: `/clients/${entityId}?panel=1`,
            households: `/households/${entityId}?panel=1`,
            transactions: `/transactions/${entityId}?panel=1`,
            scholars: `/scholars/${entityId}?panel=1`,
            payouts: `/payout-attendance/${variant ? variant + '/' : ''}${entityId}?panel=1`,
            users: `/admin/users/${entityId}?panel=1`,
            audit: `/admin/audit-logs/${entityId}?panel=1`,
            unpaid: `/unpaid-verifications/${entityId}?panel=1`,
        };
        return routes[module] || `/${module}/${entityId}?panel=1`;
    }

    function executeScripts(container) {
        const scripts = container.querySelectorAll('script');
        scripts.forEach(oldScript => {
            const fresh = document.createElement('script');
            fresh.textContent = oldScript.textContent;
            oldScript.parentNode.replaceChild(fresh, oldScript);
        });
    }

    function init() {
        getElements();

        if (closeBtn) {
            closeBtn.addEventListener('click', () => close());
        }
        if (backdropEl) {
            backdropEl.addEventListener('click', () => close());
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && panelEl?.classList.contains('open')) {
                close();
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key !== 'Tab' || !panelEl?.classList.contains('open')) return;

            const focusables = panelEl.querySelectorAll(
                'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
            );
            if (!focusables.length) return;

            const first = focusables[0];
            const last = focusables[focusables.length - 1];

            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        });
    }

    function isOpen() {
        return panelEl?.classList.contains('open') || false;
    }

    function getCurrentEntity() {
        return { module: currentModule, id: currentEntityId };
    }

    // UX-2: lightweight panel edit submit. Takes an HTMLFormElement already in
    // the panel body and intercepts its submit. Sends the payload as a standard
    // form (Laravel method-spoofed via _method) but with an Accept:
    // application/json so the additive expectsJson() branches on the existing
    // update routes respond as JSON. On success the current detail view is
    // reloaded; on 422 the field errors are rendered inline.
    function submitPanelForm(form) {
        if (!form || typeof form.submit !== 'function') return false;
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = form.querySelector('[type="submit"]');
            const original = btn ? btn.innerHTML : '';
            if (btn) { btn.disabled = true; btn.innerHTML = 'Saving…'; }

            const errorBox = form.querySelector('.panel-form-errors');
            if (errorBox) errorBox.innerHTML = '';

            try {
                const resp = await fetch(form.getAttribute('action'), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    body: new FormData(form),
                });

                const data = await resp.json().catch(() => ({}));

                if (resp.ok && data && data.success) {
                    const entity = getCurrentEntity();
                    if (entity && entity.module && entity.id) {
                        await load(entity.module, entity.id, {});
                    }
                    return; // load() replaced the panel body; no need to reset btn
                }

                if (resp.status === 422 && data && data.errors) {
                    if (errorBox) {
                        const list = document.createElement('ul');
                        Object.keys(data.errors).forEach((key) => {
                            data.errors[key].forEach((msg) => {
                                const li = document.createElement('li');
                                li.textContent = key + ': ' + msg;
                                list.appendChild(li);
                            });
                        });
                        errorBox.appendChild(list);
                    }
                } else if (errorBox && data && data.message) {
                    const p = document.createElement('p');
                    p.textContent = data.message;
                    errorBox.appendChild(p);
                }
            } catch (err) {
                if (errorBox) {
                    const p = document.createElement('p');
                    p.textContent = 'Could not save. Please try again.';
                    errorBox.appendChild(p);
                }
            } finally {
                if (btn) { btn.disabled = false; btn.innerHTML = original; }
            }
        });
        return true;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    return { open, close, load, isOpen, getCurrentEntity, init, submitPanelForm };
})();

window.DetailsPanel = DetailsPanel;