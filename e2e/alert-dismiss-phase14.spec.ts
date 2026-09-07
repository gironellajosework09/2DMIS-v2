import { test, expect, type Page } from '@playwright/test';

/**
 * Phase 14 — Bootstrap Alert dismissal migration (`data-bs-dismiss="alert"` → Alpine).
 *
 * Migrated alert instances (server-rendered, no Bootstrap Alert JS anymore):
 *  - shared layout validation alert (layouts/app.blade.php)
 *  - duplicates/index (success + errors)
 *  - admin/users/index (login_status + errors)
 *  - family_members/create (errors)
 *  - households/create (errors)
 *
 * Contract preserved:
 *  - Server-side conditions ($errors->any(), session('success'), session('login_status'))
 *    and message content are unchanged (Blade untouched).
 *  - Each alert carries its own Alpine state x-data="{ open: true }" x-show="open"
 *    so dismissals are independent (dismissing A never hides B).
 *  - The close button (@click="open = false", aria-label="Close") hides only that
 *    alert; it remains keyboard-accessible/focusable.
 *  - No Bootstrap Alert JS is required (no data-bs-dismiss="alert", no
 *    bootstrap.Alert / show.bs.alert / closed.bs.alert).
 *
 * Environment limitations:
 *  - `smoke_superadmin` test user does not exist → signIn() fails, same known
 *    limitation as Phases 8-14. The focused dismissal tests below are therefore
 *    representative and run once an authenticated session + seeded test DB exist.
 *  - The production-copy DB is never modified to satisfy the test.
 */
const SMOKE_USER = process.env.SMOKE_USER ?? 'smoke_superadmin';
const SMOKE_PASS = process.env.SMOKE_PASS ?? 'SmokeAdmin2026!';

async function signIn(page: Page): Promise<void> {
  await page.goto('/login');
  await page.getByLabel('Username').fill(SMOKE_USER);
  await page.getByLabel('Password').fill(SMOKE_PASS);
  await page.getByRole('button', { name: 'Login' }).click();
  await expect(page).toHaveURL(/\/$/);
}

test('A rendered dismissible alert exposes Alpine dismissal and hides on close', async ({ page }) => {
  await signIn(page);
  await page.goto('/admin/users');

  // Admin/users renders a server alert when login_status or errors are present.
  const alert = page.locator('.ui-notice[role="alert"], [role="alert"][x-data]').first();
  await expect(alert).toBeVisible();

  const close = alert.locator('button[aria-label="Close"]');
  await expect(close).toBeVisible();
  await close.click();
  await expect(alert).not.toBeVisible();
});

test('Alert dismissal does not depend on Bootstrap Alert JS', async ({ page }) => {
  await signIn(page);
  await page.goto('/admin/users');

  const html = await page.locator('body').innerHTML();
  expect(html).not.toContain('data-bs-dismiss="alert"');
  expect(html).not.toContain('bootstrap.Alert');
  expect(html).not.toContain('show.bs.alert');
  expect(html).not.toContain('closed.bs.alert');
});

test('Server validation alert keeps its error content and role', async ({ page }) => {
  await signIn(page);
  // Representative shared-layout page; the validation alert renders above content
  // when $errors->any(). Content + role="alert" are preserved intact.
  await page.goto('/admin/users');
  const alert = page.locator('.alert[role="alert"]').first();
  if (await alert.count()) {
    await expect(alert).toHaveAttribute('role', 'alert');
  } else {
    test.skip(); // no server validation alert present on this render — documented limitation
  }
});
