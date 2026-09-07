import { test, expect, Page } from '@playwright/test';

// Phase 1 (2026-09-03) — confirm-modal.blade.php migrated from Bootstrap Modal to
// Tailwind + Alpine. This spec exercises the imperative `window.uiConfirm(...)`
// contract in a no-auth harness because the local `main_system` is a byte-identical
// production copy with no seed user and the project must not write to the DB, so the
// authenticated delete flows cannot be driven against the live app (documented env
// limitation — same reason the auth-gated smoke/clients specs cannot run here).
//
// The harness loads the REAL built app.js (Alpine bundle) + app.css and the exact
// migrated partial markup + inline script, then drives `window.uiConfirm()` to verify:
//   * resolve(true)  on Confirm; resolve(false) on Cancel / Esc / backdrop
//   * dynamic title/message/confirmLabel binding
//   * repeat-open leaves no stale state
//   * the <form data-confirm> interception calls HTMLFormElement.submit() natively
//   * no page/console errors

const BASE = 'http://127.0.0.1:8000';

// Resolve the current Vite-built asset filenames from the manifest so the harness is
// build-agnostic (hashed filenames change on every `npm run build`).
async function assetUrls(): Promise<{ css: string; js: string }> {
  const res = await fetch(`${BASE}/build/manifest.json`);
  const manifest = await res.json();
  return {
    css: `${BASE}/build/${manifest['resources/css/app.css'].file}`,
    js: `${BASE}/build/${manifest['resources/js/app.js'].file}`,
  };
}

// Mirrors resources/views/partials/confirm-modal.blade.php (body fragment + <script>).
const PARTIAL_MARKUP = `
<div id="uiConfirmModal" x-data="uiConfirmDialog()" x-cloak role="dialog" aria-modal="true"
     aria-labelledby="uiConfirmTitle" aria-describedby="uiConfirmMessage" class="pointer-events-none fixed inset-0 z-[200]">
  <div x-show="open" x-transition.opacity.duration.200ms @click="dismiss(false)"
       class="pointer-events-auto absolute inset-0 bg-ink/40" aria-hidden="true" data-testid="backdrop"></div>
  <div class="pointer-events-none absolute inset-0 flex items-center justify-center p-4">
    <div x-show="open" x-ref="dialog" x-transition.opacity.duration.200ms
         @keydown.escape="dismiss(false)" @keydown.tab.prevent.stop="handleTab($event)"
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
        if (this._prevFocus && typeof this._prevFocus.focus === 'function') this._prevFocus.focus();
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
        if (e.shiftKey && document.activeElement === first) last.focus();
        else if (!e.shiftKey && document.activeElement === last) first.focus();
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
    if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm')) return;
    e.preventDefault();
    window.uiConfirm({ message: form.getAttribute('data-confirm'), confirmLabel: 'Delete' })
      .then(function (ok) { if (ok) HTMLFormElement.prototype.submit.call(form); });
  });
})();
</script>
`;

async function harness(page: Page) {
  const errors: string[] = [];
  page.on('pageerror', (err) => errors.push(`pageerror: ${err.message}`));
  page.on('console', (msg) => { if (msg.type() === 'error') errors.push(`console.error: ${msg.text()}`); });
  page.on('requestfailed', (req) => errors.push(`requestfailed: ${req.url()} (${req.failure()?.errorText ?? 'unknown'})`));

  const { css, js } = await assetUrls();
  await page.setContent(`
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="utf-8">
      <link rel="stylesheet" href="${css}">
    </head>
    <body>
      <button id="trigger" type="button">Trigger</button>
      <form id="f1" data-confirm="Delete this record?">
        <button type="submit">Submit</button>
      </form>
      ${PARTIAL_MARKUP}
      <script src="${js}"></script>
    </body>
    </html>
  `);
  return errors;
}

function openUiPrompt(page: Page, opts: Record<string, string> = {}) {
  return page.evaluate(
    (o) => { window.__P = window.uiConfirm(o); },
    { title: 'Test title', message: 'Test message', confirmLabel: 'Proceed', ...opts },
  );
}

test('uiConfirm resolves true on Confirm and binds dynamic text', async ({ page }) => {
  const errors = await harness(page);

  await openUiPrompt(page, { title: 'Custom title', message: 'Custom message', confirmLabel: 'Go' });

  await expect(page.getByRole('heading', { name: 'Custom title' })).toBeVisible();
  await expect(page.getByText('Custom message')).toBeVisible();
  await expect(page.getByRole('button', { name: 'Go' })).toBeVisible();

  await page.getByRole('button', { name: 'Go' }).click();
  const result = await page.evaluate(() => window.__P);
  expect(result).toBe(true);
  expect(errors, JSON.stringify(errors)).toEqual([]);
});

test('uiConfirm resolves false on Cancel', async ({ page }) => {
  const errors = await harness(page);
  await openUiPrompt(page);

  await expect(page.getByRole('heading', { name: 'Test title' })).toBeVisible();
  await page.getByRole('button', { name: 'Cancel' }).click();
  const result = await page.evaluate(() => window.__P);
  expect(result).toBe(false);
  expect(errors, JSON.stringify(errors)).toEqual([]);
});

test('uiConfirm resolves false on Escape', async ({ page }) => {
  const errors = await harness(page);
  await openUiPrompt(page);

  await expect(page.getByRole('heading', { name: 'Test title' })).toBeVisible();
  await page.keyboard.press('Escape');
  const result = await page.evaluate(() => window.__P);
  expect(result).toBe(false);
  expect(errors, JSON.stringify(errors)).toEqual([]);
});

test('uiConfirm resolves false on backdrop click', async ({ page }) => {
  const errors = await harness(page);
  await openUiPrompt(page);

  await expect(page.getByRole('heading', { name: 'Test title' })).toBeVisible();
  await page.getByTestId('backdrop').click({ position: { x: 5, y: 5 } });
  const result = await page.evaluate(() => window.__P);
  expect(result).toBe(false);
  expect(errors, JSON.stringify(errors)).toEqual([]);
});

test('uiConfirm can be reopened without stale state', async ({ page }) => {
  const errors = await harness(page);

  await openUiPrompt(page, { message: 'First message' });
  await page.getByRole('button', { name: 'Proceed' }).click();
  expect(await page.evaluate(() => window.__P)).toBe(true);

  await openUiPrompt(page, { title: 'Second', message: 'Second message', confirmLabel: 'OK' });
  await expect(page.getByRole('heading', { name: 'Second' })).toBeVisible();
  await expect(page.getByText('Second message')).toBeVisible();
  await page.getByRole('button', { name: 'OK' }).click();
  expect(await page.evaluate(() => window.__P)).toBe(true);

  expect(errors, JSON.stringify(errors)).toEqual([]);
});

test('data-confirm form submits natively only on confirm', async ({ page }) => {
  const errors = await harness(page);

  // Stub native submit so the test does not navigate the page; proves the interception
  // calls HTMLFormElement.submit() (which fires no submit event) after confirm.
  await page.evaluate(() => {
    window.__submitted = null;
    HTMLFormElement.prototype.submit = function () { window.__submitted = this.id; };
  });

  // Cancel path: no submit.
  await page.locator('#f1 button[type=submit]').click();
  await expect(page.getByRole('heading', { name: 'Are you sure?' })).toBeVisible();
  await page.getByRole('button', { name: 'Cancel' }).click();
  expect(await page.evaluate(() => window.__submitted)).toBeNull();

  // Confirm path: native submit invoked with correct form.
  await page.locator('#f1 button[type=submit]').click();
  await expect(page.getByRole('heading', { name: 'Are you sure?' })).toBeVisible();
  await page.getByRole('button', { name: 'Delete' }).click();
  expect(await page.evaluate(() => window.__submitted)).toBe('f1');

  expect(errors, JSON.stringify(errors)).toEqual([]);
});
