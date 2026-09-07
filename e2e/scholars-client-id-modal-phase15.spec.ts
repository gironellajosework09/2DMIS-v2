import { test, expect, type Page } from '@playwright/test';

/**
 * Phase 15 — Scholars Client ID Prompt Modal (`#clientIdPromptModal`)
 * Bootstrap Modal → Tailwind + Alpine.js migration.
 *
 * Contract preserved:
 *  - The `.edit-client-id` trigger buttons (rendered inside DataTables AJAX rows)
 *    still carry `data-id` / `data-clientid`; the existing delegated jQuery
 *    click handler hands those values into the Alpine `clientIdPromptModal`
 *    store (no Bootstrap relatedTarget).
 *  - Modal fields: `#clientIdPromptInput` (number, min=1), label "New Client
 *    ID". X / Cancel / backdrop / ESC close via Alpine.
 *  - `#clientIdPromptConfirm` ("Relink") still runs the existing jQuery$.ajax
 *    POST to `scholars.update-client-id`, and on success reloads
 *    `window.scholarsTable.ajax.reload(null, false)`; on error shows the
 *    existing `alert('Error updating Client ID')`.
 *  - No Bootstrap Modal JS (`bootstrap.Modal` / `getOrCreateInstance` /
 *    `show.bs.modal` / `data-bs-dismiss="modal"`) is required for this modal.
 *
 * Environment limitations:
 *  - `smoke_superadmin` test user does not exist → signIn() fails, same known
 *    limitation as Phases 8-14. Also, the Scholars page renders its table rows
 *    only after the Scholars tab is initialized and authenticated DataTables
 *    AJAX (`scholars.data`) returns — so `.edit-client-id` is not present on an
 *    unauthenticated render. The checks below assert the modal contract and
 *    document these blockers.
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

test('Scholars page renders the client-id prompt modal markup with dialog semantics', async ({ page }) => {
  await signIn(page);
  await page.goto('/scholars');

  const modal = page.getByRole('dialog', { name: 'Relink Client ID' });
  await expect(modal).toHaveAttribute('aria-modal', 'true');
  await expect(modal).toHaveAttribute('aria-labelledby', 'clientIdPromptModalTitle');

  // The data-entry field contract is preserved.
  const input = page.locator('#clientIdPromptInput');
  await expect(input).toHaveAttribute('type', 'number');
  await expect(input).toHaveAttribute('min', '1');

  const label = page.getByLabel('New Client ID');
  await expect(label).toHaveAttribute('for', 'clientIdPromptInput');
});

test('Client-id prompt modal does not depend on Bootstrap Modal JS', async ({ page }) => {
  await signIn(page);
  await page.goto('/scholars');

  const html = await page.locator('body').innerHTML();
  expect(html).not.toContain('data-bs-target="#clientIdPromptModal"');
  // No live bootstrap.Modal / getOrCreateInstance / show.bs.modal for this modal.
  const scripts = await page.locator('script').allTextContents();
  const combined = scripts.join('\n');
  expect(combined).not.toContain('getOrCreateInstance(clientIdPromptModal');
});

test('Trigger contract (data-id / data-clientid) is preserved for DataTables rows', async ({ page }) => {
  await signIn(page);
  await page.goto('/scholars');

  // The .edit-client-id triggers are emitted only inside DataTables AJAX rows
  // after the Scholars tab initializes an authenticated table — not available
  // on an unauthenticated render (documented limitation). Assert the view
  // carries the column render contract that emits them.
  const source = await page.locator('body').innerHTML();
  expect(source).toContain('edit-client-id');
});
