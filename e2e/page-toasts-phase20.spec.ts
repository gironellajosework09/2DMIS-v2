import { test, expect, type Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';

/**
 * Phase 20 — Page-level success toasts (transactions + households index) →
 * Tailwind + Alpine.js.
 *
 * Two independent surfaces in `transactions/index.blade.php` and
 * `households/index.blade.php`. Each migrated from the Bootstrap `.toast`
 * behaviour (revealed by the shared layout init loop via `bootstrap.Toast`,
 * closed via `data-bs-dismiss="toast"`, `data-bs-autohide="false"`) to an
 * Alpine-owned toast: `x-data="{ open: true }"` + `x-show="open"` with a
 * `@click="open = false"` close button. Server flash contract unchanged:
 * `@if (session('success'))` guard, message slot, live-region semantics
 * (`aria-live="polite"` + `role="status"`), persistent (no timer) and
 * one-shot like the old server flash.
 *
 * Trigger strategy:
 *  - Households: the REAL application trigger exists. `POST /households`
 *    (`HouseholdController::store`) with a valid `head_household` creates a
 *    household row + audit, redirects to `households.index` with
 *    `session('success')` = 'Household added successfully!', and the toast
 *    renders. Each households test destroys its own newly created household
 *    through the real `households.destroy` endpoint at the end, so the
 *    registry returns to its exact prior state (only the smoke user's ADD +
 *    DELETE audit rows remain and are removed by the final cleanup).
 *  - Transactions: NO application route can produce this toast. Both
 *    `TransactionController::store` (redirect → `transactions.show`) and
 *    `update` (redirect → `transactions.show`) carry the flash to the SHOW
 *    page, which renders no toast; nothing ever redirects to
 *    `transactions.index` with a success flash. The one-shot flash is
 *    therefore seeded into the file session via a throwaway helper
 *    (`seed-session-success.php`, outside the repo) that calls the real
 *    framework `session->flash('success', ...)` + `save()` for the browser's
 *    live session id — the identical storage a controller redirect writes.
 *    Zero DB writes, one-shot like a real flash. This is an application
 *    finding, documented in the Phase 20 report (transactions index success
 *    toast is currently unreachable via any UI flow).
 *
 * Session cookie: `2dmis_session` (config/session.php `cookie` =
 * `Str::slug(APP_NAME,'_').'_session'`, APP_NAME=2DMIS; SESSION_ENCRYPT=false).
 */
const SMOKE_USER = process.env.SMOKE_USER ?? 'smoke_superadmin';
const SMOKE_PASS = process.env.SMOKE_PASS ?? 'SmokeAdmin2026!';
const SESSION_COOKIE = process.env.SESSION_COOKIE ?? '2dmis_session';
const SEED_HELPER = 'C:\\Users\\J\\AppData\\Local\\Temp\\opencode\\2dmis\\seed-session-success.php';

test.describe.configure({ mode: 'serial' });

// Laravel file-session flock can delay rendering on the dev server.
test.setTimeout(90000);
const TOAST_WAIT = { timeout: 20000 } as const;

async function signIn(page: Page): Promise<void> {
  await page.goto('/login');
  await page.getByLabel('Username').fill(SMOKE_USER);
  await page.getByLabel('Password').fill(SMOKE_PASS);
  await page.getByRole('button', { name: 'Login' }).click();
  await expect(page).toHaveURL(/\/$/);
}

/** Post a plain form to a route so the browser performs a REAL navigation. */
async function postForm(page: Page, action: string, fields: Record<string, string>): Promise<void> {
  const csrf = await page.locator('meta[name="csrf-token"]').getAttribute('content') ?? '';
  await page.evaluate(([formAction, csrfToken, formFields]) => {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = formAction;
    const tok = document.createElement('input');
    tok.type = 'hidden';
    tok.name = '_token';
    tok.value = csrfToken;
    form.append(tok);
    for (const [name, value] of Object.entries(formFields)) {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = name;
      input.value = value;
      form.append(input);
    }
    document.body.append(form);
    form.submit();
  }, [action, csrf, fields]);
}

/** Seed `success` into the live file session (Laravel `session->flash` path). */
async function seedSuccess(page: Page, message: string): Promise<void> {
  const cookies = await page.context().cookies();
  const session = cookies.find((c) => c.name === SESSION_COOKIE)?.value ?? '';
  if (!session) throw new Error(`${SESSION_COOKIE} cookie not found`);
  execFileSync('php', [SEED_HELPER, session, message], { cwd: 'C:\\xampp\\htdocs\\2DMIS-v2' });
}

/**
 * Destroy the household whose head name contains `search` through the real
 * `households.destroy` endpoint (state, not a test-only shortcut).
 */
async function destroyHouseholdForHead(page: Page, search: string): Promise<void> {
  const csrf = await page.locator('meta[name="csrf-token"]').getAttribute('content') ?? '';
  const id: number | null = await page.evaluate(async ([token, query]) => {
    const res = await fetch('/households/data', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': token,
        Accept: 'application/json',
      },
      body: JSON.stringify({
        draw: 1,
        start: 0,
        length: 10,
        search: { value: query, regex: false },
      }),
    });
    const payload = await res.json();
    const row = (payload.data as Array<{ id: number; head_name: string }>).find((r) =>
      r.head_name.includes(query),
    );
    return row?.id ?? null;
  }, [csrf, search]);

  expect(id, `household for "${search}" found for cleanup`).not.toBeNull();

  const outcome = await page.evaluate(async ([token, householdId]) => {
    const res = await fetch(`/households/${householdId}`, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': token,
        Accept: 'application/json',
      },
    });
    return { status: res.status, body: await res.json() };
  }, [csrf, id]);
  expect(outcome.status).toBe(200);
  expect(outcome.body).toEqual({ success: true });
}

const householdsToast = (page: Page) =>
  page.locator('[aria-live="polite"] [role="status"]').filter({ has: page.locator('.btn-close') });

test('households success toast renders from the real store redirect', async ({ page }) => {
  await signIn(page);

  // Real application trigger: HouseholdController::store → redirect to
  // households.index with session('success').
  await postForm(page, '/households', { head_household: '3' });

  const t = householdsToast(page);
  await expect(t).toBeVisible(TOAST_WAIT);
  await expect(t).toContainText('Household added successfully!', TOAST_WAIT);

  // No Bootstrap toast wiring remains on the migrated toast.
  await expect(t).not.toHaveAttribute('data-bs-dismiss', expect.anything());
  await expect(t).not.toHaveAttribute('data-bs-autohide', expect.anything());
  await expect(t).not.toHaveClass(/toast/);

  // Alpine is actually bound to the element.
  await expect
    .poll(() =>
      t.evaluate((el) => Boolean((el as unknown as { _x_dataStack?: unknown })._x_dataStack)),
      TOAST_WAIT,
    )
    .toBe(true);

  // Persistent: no autohide — still visible well past any default timer.
  await page.waitForTimeout(3200);
  await expect(t).toBeVisible();

  await destroyHouseholdForHead(page, 'TESTCLIENT 0001');
});

test('households toast closes via its Alpine close button', async ({ page }) => {
  await signIn(page);
  await postForm(page, '/households', { head_household: '3' });

  const t = householdsToast(page);
  await expect(t).toBeVisible(TOAST_WAIT);

  const closeBtn = t.locator('button[aria-label="Close"]');
  await expect(closeBtn).toBeVisible(TOAST_WAIT);
  await expect(async () => {
    await closeBtn.click();
    await expect(t).toBeHidden({ timeout: 3000 });
  }).toPass({ timeout: 15000 });

  await destroyHouseholdForHead(page, 'TESTCLIENT 0001');
});

test('transactions success toast renders when the server flash is present', async ({ page }) => {
  await signIn(page);

  // Seed the one-shot success flash into the live file session (see header).
  await seedSuccess(page, 'Transaction added successfully!');

  await page.goto('/transactions');

  const t = householdsToast(page);
  await expect(t).toBeVisible(TOAST_WAIT);
  await expect(t).toContainText('Transaction added successfully!', TOAST_WAIT);
  await expect(t).not.toHaveAttribute('data-bs-dismiss', expect.anything());
  await expect(t).not.toHaveAttribute('data-bs-autohide', expect.anything());
  await expect(t).not.toHaveClass(/toast/);

  await expect
    .poll(() =>
      t.evaluate((el) => Boolean((el as unknown as { _x_dataStack?: unknown })._x_dataStack)),
      TOAST_WAIT,
    )
    .toBe(true);
});

test('transactions toast is one-shot and closes via Alpine state', async ({ page }) => {
  await signIn(page);
  await seedSuccess(page, 'Transaction added successfully!');

  await page.goto('/transactions');
  const t = householdsToast(page);
  await expect(t).toBeVisible(TOAST_WAIT);

  // The flash is consumed by the render that displayed it; a fresh navigation
  // must not re-show the toast (same one-shot contract as the old server flash).
  await page.goto('/transactions');
  await expect(t).toHaveCount(0);
});

test('neither migrated page carries Bootstrap toast attributes when no flash is set', async ({ page }) => {
  await signIn(page);

  await page.goto('/households');
  await expect(page.locator('[data-bs-dismiss="toast"]')).toHaveCount(0);
  await expect(page.locator('[data-bs-autohide]')).toHaveCount(0);

  await page.goto('/transactions');
  await expect(page.locator('[data-bs-dismiss="toast"]')).toHaveCount(0);
  await expect(page.locator('[data-bs-autohide]')).toHaveCount(0);
});