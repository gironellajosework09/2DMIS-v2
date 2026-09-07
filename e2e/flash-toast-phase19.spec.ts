import { test, expect, type Page } from '@playwright/test';

/**
 * Phase 19 — Shared Layout Flash Toast (`#flashToast`) → Tailwind + Alpine.js.
 *
 * Page: `layouts/app.blade.php` — the toast renders on every layout page when
 * the server flashes `login_status` (middleware + controllers, contract
 * unchanged). It is a persistent toast: manual dismiss via its close button,
 * `autohide:false` — it must NOT disappear on a timer.
 *
 * Real-server flash needed. The zero-write trigger is the public admin
 * `session.force-logout` route with a non-existent `user_id`:
 *
 *   POST /session/force-logout  { user_id: 99999999 }
 *     → validate passes → User::find() → null → back()->with('login_status',
 *       'User not found.')   [no DB write: target null, no audit row]
 *
 * The flash lands in the session cookie (shared by `page.request` with the
 * browser context), then `page.goto('/')` renders the dashboard (a layout
 * page) with the toast. `page.request` mutates nothing and auto-follows
 * redirects with the same cookie jar.
 *
 * Scope note: the layout's inline script still contains
 * `bootstrap.Toast.getOrCreateInstance(...)` — by design it continues to
 * initialize the OTHER `.toast` surfaces rendered by child views (clients
 * Phase 9 feedback stack, transactions/households success toasts). The
 * migrated `#flashToast` no longer carries the `.toast` class, so the legacy
 * loop never touches it. The assertions below prove that behaviorally
 * (visible without any `.show()` call; zero legacy matches on a layout-only
 * page).
 */
const SMOKE_USER = process.env.SMOKE_USER ?? 'smoke_superadmin';
const SMOKE_PASS = process.env.SMOKE_PASS ?? 'SmokeAdmin2026!';

// The flash lands via a REAL same-URL form navigation; Laravel's file-session
// flock can hold the bind between the POST and the redirect-follow GET for a
// few seconds on the dev server, so toast assertions get a generous budget.
test.setTimeout(90000);
const TOAST_WAIT = { timeout: 20000 } as const;

async function signIn(page: Page): Promise<void> {
  await page.goto('/login');
  await page.getByLabel('Username').fill(SMOKE_USER);
  await page.getByLabel('Password').fill(SMOKE_PASS);
  await page.getByRole('button', { name: 'Login' }).click();
  await expect(page).toHaveURL(/\/$/);
}

/**
 * Trigger a flash-only `login_status` (no DB write) via a REAL browser form
 * navigation, then land back on the same page with the toast.
 *
 * Approach: inject a plain `<form>` (with NO `data-confirm`, so the custom
 * confirm modal never engages) targeting `session.force-logout` with a
 * non-existent `user_id`, and `form.submit()` it. The browser performs a
 * genuine POST navigation with its real cookie jar — no cross-context fetch
 * races, no redirect-manual cookie loss. `back()` resolves from the Referer
 * (the page the form was submitted from), so the 302 returns to the same
 * layout page with the flashed session and the toast is rendered there.
 * Subsequent assertions auto-wait on the toast.
 *
 * Zero DB writes anywhere: `User::find(99999999)` is null → the controller
 * only flashes 'User not found.' and redirects.
 */
async function flashLoginStatus(page: Page): Promise<void> {
  const csrf = await page.locator('meta[name="csrf-token"]').getAttribute('content') ?? '';
  await page.evaluate((token) => {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/session/force-logout';
    const tok = document.createElement('input');
    tok.type = 'hidden';
    tok.name = '_token';
    tok.value = token;
    const uid = document.createElement('input');
    uid.type = 'hidden';
    uid.name = 'user_id';
    uid.value = '99999999';
    form.append(tok, uid);
    document.body.append(form);
    form.submit();
  }, csrf);
}

const toast = (page: Page) => page.locator('#flashToast');

test('persistent layout flash toast renders with the flashed message', async ({ page }) => {
  await signIn(page);
  await flashLoginStatus(page);

  const t = toast(page);
  await expect(t).toBeVisible(TOAST_WAIT);
  await expect(t).toContainText('User not found.', TOAST_WAIT);
  await expect(t).toHaveAttribute('role', 'status');

  // Position + visual contract: fixed stack under the topbar (top-20),
  // right-aligned, rounded surface, teal check affordance.
  await expect(page.locator('.fixed.inset-x-0.top-20[aria-live="polite"]')).toHaveCount(1);
  await expect(t.locator('svg polyline')).toHaveCount(1);
  await expect(t).toHaveClass(/rounded-panel/);
  await expect(t).not.toHaveCSS('border-radius', '0px');

  // Persistence: autohide:false — still visible well past any default timer.
  await page.waitForTimeout(3200);
  await expect(t).toBeVisible();
});

test('layout toast is fully Alpine — visible without Bootstrap, closes by Alpine state', async ({ page }) => {
  await signIn(page);
  await flashLoginStatus(page);

  const t = toast(page);

  // Wait through the flash navigation before touching the element.
  await expect(t).toBeVisible(TOAST_WAIT);

  // No Bootstrap Toast attributes remain on the migrated toast.
  await expect(t).not.toHaveAttribute('data-bs-dismiss', expect.anything());
  await expect(t).not.toHaveAttribute('data-bs-autohide', expect.anything());
  await expect(t).not.toHaveAttribute('data-bs-toggle', expect.anything());

  // It is NOT a `.toast` any more — Bootstrap's `.toast:not(.show){display:none}`
  // gate is gone, so the toast is visible on server render with zero JS show call.
  await expect(t).toBeVisible(TOAST_WAIT);
  await expect(t).not.toHaveClass(/toast/);

  // Alpine has actually bound the toast element (x-data data stack present).
  await expect
    .poll(() => page.evaluate(() => {
      const el = document.querySelector('#flashToast') as unknown as { _x_dataStack?: unknown } | null;
      return Boolean(el?._x_dataStack);
    }), TOAST_WAIT)
    .toBe(true);

  // On a layout-only page (dashboard) the legacy bootstrap init has nothing left
  // to own: visible purely through Alpine `x-show` state.
  const legacyMatches = await page.evaluate(() => document.querySelectorAll('.toast').length);
  expect(legacyMatches).toBe(0);

  // Close via the Alpine-bound button (same .btn-close + aria-label="Close").
  // Retried until the Alpine handler is attached and the hide actually lands.
  const closeBtn = t.locator('button[aria-label="Close"]');
  await expect(closeBtn).toBeVisible(TOAST_WAIT);
  await expect(async () => {
    await closeBtn.click();
    await expect(t).toBeHidden({ timeout: 3000 });
  }).toPass({ timeout: 15000 });

  // Closing mutated Alpine state without any bootstrap call — no page error.
});

test('flash is one-shot: disappears on the next navigation like the old server flash', async ({ page }) => {
  await signIn(page);
  await flashLoginStatus(page);
  await expect(toast(page)).toBeVisible(TOAST_WAIT);

  // The flash is consumed by the load that displayed it; navigating on a new
  // request must not re-show the toast.
  await page.goto('/admin/audit-logs');
  await expect(toast(page)).toHaveCount(0);
});

test('message content mirrors the exact server-flashed value', async ({ page }) => {
  await signIn(page);
  await flashLoginStatus(page);

  // Same server channel, second independent render — value round-trips verbatim
  // from the controller through the Blade slot into the toast text node.
  await expect(toast(page)).toHaveText(/User not found\./, TOAST_WAIT);
});