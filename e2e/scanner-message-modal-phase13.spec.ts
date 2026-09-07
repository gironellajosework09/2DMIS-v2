import { test, expect, type Page } from '@playwright/test';

/**
 * Phase 13 — Scanner message modal (#messageModal) Tailwind + Alpine migration.
 *
 * Behavior contract (parity with the previous Bootstrap modal in
 * resources/views/scanners/scan.blade.php):
 *  - #messageModal is a notification dialog opened imperatively via
 *    showModal(msg, type, title, onOk):
 *      * #modalTitle.textContent = title || 'Notification'
 *      * #modalMessage.textContent = msg   (plain text — newlines preserved)
 *      * callback = onOk || reloadPage
 *  - OK (#modalOkBtn) runs the callback synchronously on click, then closes.
 *  - Backdrop click AND ESC close the modal without firing the callback.
 *  - Dialog semantics: role=dialog, aria-modal=true, labelled by the title,
 *    Tab trapped inside, body scroll locked while open, focus enters the
 *    dialog on open.
 *  - The modal no longer depends on Bootstrap JS:
 *      * no data-bs-toggle / data-bs-target / data-bs-dismiss for it
 *      * no bootstrap.Modal / new bootstrap.Modal / show.bs.modal for it.
 *
 * Environment limitations:
 *  - `smoke_superadmin` test user does not exist → signIn() fails, same known
 *    limitation as Phases 8-13. Opening via showModal() is exercised with the
 *    Alpine store directly once an authenticated session exists.
 *  - The scan page is auth + page-gated (page:scanner_ceap.php); the modal is
 *    page-agnostic and exercised at /scanners/ceap.
 */
const SMOKE_USER = process.env.SMOKE_USER ?? 'smoke_superadmin';
const SMOKE_PASS = process.env.SMOKE_PASS ?? 'SmokeAdmin2026!';
const SCANNER_URL = '/scanners/ceap';

async function signIn(page: Page): Promise<void> {
  await page.goto('/login');
  await page.getByLabel('Username').fill(SMOKE_USER);
  await page.getByLabel('Password').fill(SMOKE_PASS);
  await page.getByRole('button', { name: 'Login' }).click();
  await expect(page).toHaveURL(/\/$/);
}

test('Scanner #messageModal renders Alpine dialog semantics & hidden by default', async ({ page }) => {
  await signIn(page);
  await page.goto(SCANNER_URL);

  const dialog = page.locator('#messageModal');
  await expect(dialog).toHaveAttribute('role', 'dialog');
  await expect(dialog).toHaveAttribute('aria-modal', 'true');
  await expect(dialog).toHaveAttribute('aria-labelledby', 'modalTitle');
  await expect(dialog).toHaveAttribute('aria-describedby', 'modalMessage');

  // Hidden by default (x-cloak / closed state)
  await expect(dialog).not.toBeVisible();
  // OK + close controls present
  await expect(page.locator('#modalOkBtn')).toHaveText('OK');
  await expect(page.locator('[aria-label="Close"]')).toBeAttached();
});

test('showModal(...) opens #messageModal and populates title/body as plain text', async ({ page }) => {
  await signIn(page);
  await page.goto(SCANNER_URL);

  await page.evaluate(() => {
    (window as any).showModal('Hello scanner', 'error', 'Attention', null);
  });
  const dialog = page.locator('#messageModal');
  await expect(dialog).toBeVisible();
  await expect(page.locator('#modalTitle')).toHaveText('Attention');
  await expect(page.locator('#modalMessage')).toHaveText('Hello scanner');

  // Plain text insertion: HTML is not interpreted.
  await page.evaluate(() => {
    (window as any).showModal('<b>bold</b> & stuff', 'error', 'T2', null);
  });
  await expect(page.locator('#modalMessage')).toHaveText('<b>bold</b> & stuff');
});

test('OK invokes the callback once and closes the dialog', async ({ page }) => {
  await signIn(page);
  await page.goto(SCANNER_URL);

  await page.evaluate(() => {
    (window as any).__scannerTestCalls = 0;
    (window as any).showModal('msg', 'error', 'Title', () => {
      (window as any).__scannerTestCalls += 1;
    });
  });
  const dialog = page.locator('#messageModal');
  await expect(dialog).toBeVisible();

  await page.locator('#modalOkBtn').click();
  await expect(dialog).not.toBeVisible();
  const calls = await page.evaluate(() => (window as any).__scannerTestCalls);
  expect(calls).toBe(1);
});

test('Backdrop and ESC close without firing the callback; X closes too', async ({ page }) => {
  await signIn(page);
  await page.goto(SCANNER_URL);

  for (const closeMethod of ['backdrop', 'escape', 'x'] as const) {
    await page.evaluate(() => {
      (window as any).__scannerTestCalls = 0;
      (window as any).showModal('msg', 'error', 'Title', () => {
        (window as any).__scannerTestCalls += 1;
      });
    });
    await expect(page.locator('#messageModal')).toBeVisible();

    if (closeMethod === 'backdrop') {
      await page.mouse.click(10, 10);
    } else if (closeMethod === 'escape') {
      await page.keyboard.press('Escape');
    } else {
      await page.locator('[aria-label="Close"]').click();
    }

    await expect(page.locator('#messageModal')).not.toBeVisible();
    const calls = await page.evaluate(() => (window as any).__scannerTestCalls);
    expect(calls, `${closeMethod} close must NOT invoke the OK callback`).toBe(0);
  }
});

test('Focus enters the dialog on open (OK is the primary action)', async ({ page }) => {
  await signIn(page);
  await page.goto(SCANNER_URL);

  await page.evaluate(() => {
    (window as any).showModal('msg', 'error', 'Title', null);
  });
  await expect(page.locator('#messageModal')).toBeVisible();
  const activeId = await page.evaluate(() => document.activeElement?.id);
  expect(activeId).toBe('modalOkBtn');
});

test('Scanner #messageModal no longer depends on Bootstrap JS', async ({ page }) => {
  await signIn(page);
  await page.goto(SCANNER_URL);

  const html = await page.locator('body').innerHTML();
  expect(html).not.toContain('new bootstrap.Modal');
  expect(html).not.toContain('show.bs.modal');
  expect(html).not.toContain('hidden.bs.modal');
  // No data-API pointing at this modal and no Bootstrap modal structure.
  await expect(page.locator('#messageModal')).not.toHaveClass(/modal/);
});
