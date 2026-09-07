import { test, expect, type Page } from '@playwright/test';

/**
 * Phase 9 — Client feedback modal + toast Alpine.js migration verification.
 *
 * Behavior contract (parity with the previous Bootstrap modal):
 *  - Default Bootstrap modal semantics (no backdrop:'static', no
 *    keyboard:false) → backdrop click AND ESC close the feedback modal.
 *  - Dynamic title (#clientFeedbackTitle), body (#clientFeedbackBody) and
 *    footer action area (#clientFeedbackActions) are filled by callers
 *    (duplicate warning, validation errors, photo errors).
 *  - Static "Back to form" footer button closes back to the form modal.
 *  - On open, focus moves into the dialog; on close, focus returns to the
 *    underlying form's first focusable.
 *  - Body scroll stays locked while any modal layer is open.
 *  - The client success toast (#clientsToastStack) is Bootstrap-JS-free:
 *    revealed with `.show`, dismissed manually, no auto-hide.
 *
 * Environment limitations:
 *  - `smoke_superadmin` test user does not exist → signIn() fails.
 * These tests document the feedback modal + toast contract and will pass once
 * the test environment is seeded with the appropriate user (same limitation as
 * Phase 8's client-form-modal-phase8.spec.ts).
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

test('Feedback modal roles/attrs render in the clients index', async ({ page }) => {
  await signIn(page);
  await page.goto('/clients');

  const dialog = page.locator('#clientFeedbackModal');
  await expect(dialog).toHaveAttribute('role', 'dialog');
  await expect(dialog).toHaveAttribute('aria-modal', 'true');
  await expect(dialog).toHaveAttribute('aria-labelledby', 'clientFeedbackTitle');

  // Dynamic body / actions containers present
  await expect(page.locator('#clientFeedbackBody')).toBeAttached();
  await expect(page.locator('#clientFeedbackActions')).toBeAttached();

  // Hidden by default (x-cloak / closed store)
  await expect(dialog).not.toBeVisible();
});

test('Feedback modal opens via window.showClientFeedback and populates title/body', async ({ page }) => {
  await signIn(page);
  await page.goto('/clients');

  await page.evaluate(() => {
    const p = document.createElement('p');
    p.textContent = 'A test message';
    (window as any).showClientFeedback({ title: 'Test title', type: 'info', body: p });
  });
  await expect(page.locator('#clientFeedbackModal')).toBeVisible();
  await expect(page.locator('#clientFeedbackTitle')).toHaveText('Test title');
  await expect(page.locator('#clientFeedbackBody')).toContainText('A test message');
});

test('Feedback modal closes on the close (X) button', async ({ page }) => {
  await signIn(page);
  await page.goto('/clients');

  await page.evaluate(() => {
    (window as any).showClientFeedback({ title: 'Notice', type: 'info', body: document.createElement('p') });
  });
  await expect(page.locator('#clientFeedbackModal')).toBeVisible();
  await page.locator('#clientFeedbackModal [aria-label="Close"]').click();
  await expect(page.locator('#clientFeedbackModal')).not.toBeVisible();
});

test('Feedback modal closes on the footer Back to form button', async ({ page }) => {
  await signIn(page);
  await page.goto('/clients');

  await page.evaluate(() => {
    (window as any).showClientFeedback({ title: 'Notice', type: 'info', body: document.createElement('p') });
  });
  await expect(page.locator('#clientFeedbackModal')).toBeVisible();
  await page.getByRole('button', { name: 'Back to form' }).click();
  await expect(page.locator('#clientFeedbackModal')).not.toBeVisible();
});

test('Feedback modal closes on ESC (default keyboard semantics)', async ({ page }) => {
  await signIn(page);
  await page.goto('/clients');

  await page.evaluate(() => {
    (window as any).showClientFeedback({ title: 'Notice', type: 'info', body: document.createElement('p') });
  });
  await expect(page.locator('#clientFeedbackModal')).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(page.locator('#clientFeedbackModal')).not.toBeVisible();
});

test('Feedback modal closes on backdrop/outside click (default backdrop semantics)', async ({ page }) => {
  await signIn(page);
  await page.goto('/clients');

  await page.evaluate(() => {
    (window as any).showClientFeedback({ title: 'Notice', type: 'info', body: document.createElement('p') });
  });
  await expect(page.locator('#clientFeedbackModal')).toBeVisible();
  await page.mouse.click(10, 10);
  await expect(page.locator('#clientFeedbackModal')).not.toBeVisible();
});

test('Feedback modal traps focus within the dialog (Tab)', async ({ page }) => {
  await signIn(page);
  await page.goto('/clients');

  await page.evaluate(() => {
    (window as any).showClientFeedback({ title: 'Notice', type: 'info', body: document.createElement('p') });
  });
  await expect(page.locator('#clientFeedbackModal')).toBeVisible();

  const dialog = page.locator('#clientFeedbackModal');
  const focusableCount = await dialog.locator('button').count();
  expect(focusableCount).toBeGreaterThan(0);

  for (let i = 0; i < focusableCount + 2; i++) {
    await page.keyboard.press('Tab');
    const inside = await page.evaluate(() => {
      const el = document.activeElement;
      return el ? el.closest('#clientFeedbackModal') !== null : false;
    });
    expect(inside, `Focus escaped dialog on Tab press #${i + 1}`).toBe(true);
  }
});

test('Toast stack contract is Bootstrap-JS-free', async ({ page }) => {
  await signIn(page);
  await page.goto('/clients');

  // The clients index defines showToast inside the ready callback (not on
  // window), so the toast is exercised via the real save/delete flows that
  // require an authenticated session + a client. Here we verify the static
  // toast-channel contract the module relies on.
  const stack = page.locator('#clientsToastStack');
  await expect(stack).toBeAttached();
  await expect(stack).toHaveAttribute('aria-live', 'polite');

  // No stale Bootstrap Toast data attributes on the channel container and no
  // server-rendered success flash carrying Bootstrap toast controls.
  const html = await page.locator('body').innerHTML();
  expect(html).not.toContain('data-bs-dismiss="toast"');
});
