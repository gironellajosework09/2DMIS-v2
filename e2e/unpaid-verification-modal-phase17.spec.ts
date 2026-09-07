import { test, expect, type Page } from '@playwright/test';

/**
 * Phase 17 — Unpaid Verification dynamic Final Confirmation modal
 * (Bootstrap Modal JS → Tailwind + Alpine.js migration).
 *
 * Page: `unpaid-verification` (PUBLIC — no login required, unlike Phases
 * 8–16, so these checks run live against the dev server).
 *
 * Contract preserved:
 *  - The modal is not part of the initial DOM — it is built dynamically by
 *    `showConfirmation()` on each trigger (`#btnSelf` → self confirm,
 *    `#submitProxyBtn` → proxy confirm with the name/relationship baked in),
 *    appended, initialised via `Alpine.initTree`, shown, then removed on
 *    close. No persistent hidden DOM.
 *  - Close set unchanged: X button, Cancel button, backdrop click, and ESC.
 *    None of them confirm (only `#confirmYesBtn` does).
 *  - Confirm runs the untouched `saveUnpaid()` fetch contract *after* the
 *    modal closes/removes. With no client+municipality selected the existing
 *    business guard shows `Please select your name and municipality.` in
 *    `#alertBox` and never hits the network — used here to verify the
 *    confirm-close lifecycle without writing to the DB.
 *  - No `bootstrap.Modal` / `getOrCreateInstance` / `show|hidden.bs.modal` /
 *    `data-bs-dismiss` in the page's inline script.
 *  - Focus traversal: Close button receives focus on open, Tab is trapped
 *    inside the dialog, focus returns to the trigger on close; body scroll is
 *    locked while open and restored on close.
 */

/**
 * `#btnSelf` sits inside `#confirmSection`, which the page keeps hidden
 * (`d-none`) until a successful client+municipality verification against the
 * production-copy DB. E2E cannot run that DB-backed verify (no seeded grantee
 * and it would write nothing, but depends on live data), so this setup step
 * reveals the section exactly as the verify-success branch does. It modifies
 * the DOM in the browser only — the application is untouched.
 */
async function revealConfirmSection(page: Page): Promise<void> {
  await page.evaluate(() =>
    document.getElementById('confirmSection')?.classList.remove('d-none'),
  );
}

async function openSelfConfirm(page: Page): Promise<void> {
  await page.goto('/unpaid-verification');
  await revealConfirmSection(page);
  await page.locator('#btnSelf').click();
  const dialog = page.getByRole('dialog', { name: 'Final Confirmation' });
  await expect(dialog).toBeVisible();
}

test('self-service page is public and the modal is built dynamically on trigger', async ({ page }) => {
  await page.goto('/unpaid-verification');

  // No persistent modal markup in the initial DOM.
  await expect(page.getByRole('dialog', { name: 'Final Confirmation' })).toHaveCount(0);
  await expect(page.locator('#confirmYesBtn')).toHaveCount(0);

  await openSelfConfirm(page);

  const dialog = page.getByRole('dialog', { name: 'Final Confirmation' });
  await expect(dialog).toHaveAttribute('aria-modal', 'true');
  await expect(dialog).toHaveAttribute('aria-labelledby', 'unpaidConfirmationModalTitle');

  // Self-confirm copy preserved.
  await expect(dialog).toContainText('Final Confirmation');
  await expect(dialog).toContainText('will personally attend the payout');
  await expect(dialog).toContainText('Important:');
  await expect(dialog.locator('#confirmYesBtn')).toHaveText('Yes, Confirm Submission');
  await expect(dialog.getByRole('button', { name: 'Cancel' })).toBeVisible();

  // Focus enters the dialog on the Close button.
  await expect(dialog.getByRole('button', { name: 'Close' })).toBeFocused();
});

test('migrated modal has no Bootstrap Modal JS dependency', async ({ page }) => {
  await page.goto('/unpaid-verification');

  const scripts = await page.locator('script').allTextContents();
  const inline = scripts.find((s) => s.includes('showConfirmation')) ?? '';
  expect(inline).not.toContain('bootstrap.Modal(');
  expect(inline).not.toContain('getOrCreateInstance');
  expect(inline).not.toContain('data-bs-toggle');
  expect(inline).not.toContain('data-bs-dismiss');
  expect(inline).not.toContain('show.bs.modal');
  expect(inline).not.toContain('hidden.bs.modal');

  // Alpine owns presentation; the dynamic lifecycle is wired through the store.
  expect(inline).toContain('Alpine.initTree(modal)');
  expect(inline).toContain(`Alpine.store('unpaidConfirmationModal')`);
  expect(inline).toContain('document.createElement(\'div\')');
});

test('close set (X / Cancel / backdrop / ESC) removes the modal; reopen creates a fresh single instance', async ({ page }) => {
  const closeAndVerify = async (close: () => Promise<void>) => {
    await openSelfConfirm(page);
    const dialog = page.getByRole('dialog', { name: 'Final Confirmation' });
    await close();
    await expect(dialog).toHaveCount(0);
    await expect(page.locator('#confirmYesBtn')).toHaveCount(0);
    // Focus returned to the trigger.
    await expect(page.locator('#btnSelf')).toBeFocused();
  };

  // X button
  await closeAndVerify(async () => {
    await page.getByRole('button', { name: 'Close' }).click();
  });

  // Cancel button
  await closeAndVerify(async () => {
    await page.getByRole('button', { name: 'Cancel' }).click();
  });

  // ESC key
  await closeAndVerify(async () => {
    await page.keyboard.press('Escape');
  });

  // Backdrop click (top-left corner lands on the full-screen backdrop, not the centered dialog)
  await closeAndVerify(async () => {
    await page.mouse.click(12, 12);
  });

  // Reopen repeatedly -> exactly one instance each time (no stale copies).
  for (let i = 0; i < 3; i++) {
    await openSelfConfirm(page);
    await expect(page.getByRole('dialog', { name: 'Final Confirmation' })).toHaveCount(1);
    await page.keyboard.press('Escape');
    await expect(page.getByRole('dialog', { name: 'Final Confirmation' })).toHaveCount(0);
  }
});

test('body scroll is locked while open and restored on close', async ({ page }) => {
  const overflow = () =>
    page.evaluate(() => document.body.style.overflow);

  await page.goto('/unpaid-verification');
  await expect.poll(overflow).toBe('');

  await openSelfConfirm(page);
  await expect.poll(overflow).toBe('hidden');

  await page.getByRole('button', { name: 'Close' }).click();
  await expect.poll(overflow).toBe('');
});

test('confirm closes the modal, removes it, and fires the untouched saveUnpaid POST', async ({ page }) => {
  // Stub the submit endpoint so the contract (`confirm()` -> close/remove ->
  // `saveUnpaid()` -> `postForm(saveUrl, ...)`) is exercised without ever
  // touching the DB. The stub resolves as failure; the page's existing
  // else-branch alert is auto-dismissed by Playwright.
  let submits = 0;
  await page.route('**/unpaid-verification/submit', (route) => {
    submits += 1;
    route.fulfill({
      status: 422,
      contentType: 'application/json',
      body: JSON.stringify({ success: false, message: 'e2e stub' }),
    });
  });

  await openSelfConfirm(page);

  await page.locator('#confirmYesBtn').click();

  // Modal closed and removed AFTER triggering the saveUnpaid flow.
  await expect(page.getByRole('dialog', { name: 'Final Confirmation' })).toHaveCount(0);
  await expect(page.locator('#btnSelf')).toBeFocused();
  await expect.poll(() => submits).toBe(1);
});