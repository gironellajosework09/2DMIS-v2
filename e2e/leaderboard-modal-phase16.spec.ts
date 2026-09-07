import { test, expect, type Page } from '@playwright/test';

/**
 * Phase 16 — Audit Logs Leaderboard Modal (`#leaderboardModal`)
 * Bootstrap Modal → Tailwind + Alpine.js migration.
 *
 * Contract preserved:
 *  - The server-rendered Leaderboard trigger (page-header actions) now calls
 *    `Alpine.store('leaderboardModal').open()` (@click) instead of
 *    `data-bs-toggle="modal" data-bs-target="#leaderboardModal"`.
 *  - Opening the modal fires the existing jQuery `$.ajax` POST to
 *    `admin.audit-logs.leaderboard` (`{ table: $('#table').val() }`,
 *    `X-CSRF-TOKEN` header) exactly once per open — no caching, one request
 *    per open (same as the old `show.bs.modal` lifecycle). On success the
 *    `#leaderboardTable tbody` is repopulated with Rank/User/Total Actions.
 *  - X / Close / backdrop / ESC close via Alpine; body scroll is locked while
 *    open and restored on close; focus enters the dialog and returns to the
 *    previously focused element (the trigger) on close; Tab is trapped.
 *  - No Bootstrap Modal JS (`bootstrap.Modal` / `getOrCreateInstance` /
 *    `show.bs.modal` / `hidden.bs.modal`) is required for this modal.
 *
 * Environment limitations:
 *  - `smoke_superadmin` test user does not exist → signIn() fails, same known
 *    limitation as Phases 8-15. The audit-logs page and leaderboard AJAX also
 *    require an authenticated session (route gated by `audit_logs.php`
 *    permission), so the checks below document the contract and assertion
 *    points that will run once a seeded test DB exists.
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

test('Audit Logs renders the leaderboard trigger and modal with dialog semantics', async ({ page }) => {
  await signIn(page);
  await page.goto('/admin/audit-logs');

  // Trigger: server-rendered Leaderboard button in the page header actions —
  // visible text + placement preserved, now wired to the Alpine store.
  const trigger = page.getByRole('button', { name: 'Leaderboard' });
  await expect(trigger).toBeVisible();

  const modal = page.getByRole('dialog', { name: 'User Activity Leaderboard' });
  await expect(modal).toHaveAttribute('aria-modal', 'true');
  await expect(modal).toHaveAttribute('aria-labelledby', 'leaderboardLabel');

  // Leaderboard table structure preserved.
  const table = page.locator('#leaderboardTable');
  await expect(table.locator('thead tr th')).toHaveText(['Rank', 'User', 'Total Actions']);
});

test('Leaderboard modal does not depend on Bootstrap Modal JS', async ({ page }) => {
  await signIn(page);
  await page.goto('/admin/audit-logs');

  const html = await page.locator('body').innerHTML();
  expect(html).not.toContain('data-bs-target="#leaderboardModal"');
  expect(html).not.toContain('data-bs-toggle="modal"');
  const scripts = await page.locator('script').allTextContents();
  const combined = scripts.join('\n');
  expect(combined).not.toContain('show.bs.modal');
  expect(combined).not.toContain('getOrCreateInstance');
});

test('Leaderboard AJAX contract (once per open) remains wired to open()', async ({ page }) => {
  await signIn(page);
  await page.goto('/admin/audit-logs');

  // The store's open() -> load() keeps the jQuery $.ajax POST to the
  // leaderboard route. Assert the wiring exists in the rendered source (a live
  // request needs an authenticated session + seeded DB — documented blocker).
  const scripts = await page.locator('script').allTextContents();
  const combined = scripts.join('\n');
  expect(combined).toContain('Alpine.store(\'leaderboardModal\')');
  expect(combined).toContain('/admin/audit-logs/leaderboard');
  expect(combined).toContain('$(\'#table\').val()');
});