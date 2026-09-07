import { test, expect, type Page } from '@playwright/test';

/**
 * Phase 8 — Client form modal Alpine.js migration verification.
 *
 * Behavior contract (parity with the previous Bootstrap modal):
 *  - backdrop:'static', keyboard:false → ESC and backdrop/outside click do NOT close.
 *  - Opens via window.openAddClientModal() / window.openEditModal(id).
 *  - Form body is loaded via AJAX into #clientFormModalBody.
 *  - Footer submit (id=clientFormSubmit, form="clientForm") + Cancel close.
 *  - Tab is trapped inside the dialog; focus returns to the trigger on close.
 *
 * Environment limitations:
 *  - `smoke_superadmin` test user does not exist → signIn() fails.
 * These tests document the client form modal contract and will pass once the
 * test environment is seeded with the appropriate user.
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

test('Client form modal roles/attrs render in the clients index', async ({ page }) => {
  await signIn(page);
  await page.goto('/clients');

  const dialog = page.locator('#clientFormModal');
  await expect(dialog).toHaveAttribute('role', 'dialog');
  await expect(dialog).toHaveAttribute('aria-modal', 'true');
  await expect(dialog).toHaveAttribute('aria-labelledby', 'cfmTitle');

  // Header + footer contract preserved (submit wired to the in-body form)
  await expect(page.locator('#clientFormSubmit')).toHaveAttribute('form', 'clientForm');
  await expect(page.locator('#cfmTitle')).toBeVisible();

  // Hidden by default (x-cloak / closed store)
  await expect(dialog).not.toBeVisible();
});

test('Add Client modal opens with Add mode labels and loads the form via AJAX', async ({ page }) => {
  await signIn(page);
  await page.goto('/clients');

  await page.evaluate(() => (window as any).openAddClientModal());
  await expect(page.locator('#clientFormModal')).toBeVisible();

  await expect(page.locator('#cfmTitle')).toHaveText('Add Client');
  await expect(page.locator('#cfmSubtitle')).toContainText('Register a new client');
  await expect(page.locator('#clientFormSubmit')).toHaveText('Add Client');

  // Form is injected into the body via AJAX (wait for the form element)
  await expect(page.locator('#clientFormModalBody form')).toBeVisible();
});

test('Modal does NOT close on ESC (backdrop:static, keyboard:false parity)', async ({ page }) => {
  await signIn(page);
  await page.goto('/clients');

  await page.evaluate(() => (window as any).openAddClientModal());
  await expect(page.locator('#clientFormModal')).toBeVisible();

  await page.keyboard.press('Escape');
  await expect(page.locator('#clientFormModal')).toBeVisible();
});

test('Modal does NOT close on outside/backdrop click (backdrop:static parity)', async ({ page }) => {
  await signIn(page);
  await page.goto('/clients');

  await page.evaluate(() => (window as any).openAddClientModal());
  await expect(page.locator('#clientFormModal')).toBeVisible();

  // Click the backdrop region (outside the dialog)
  await page.mouse.click(10, 10);
  await expect(page.locator('#clientFormModal')).toBeVisible();
});

test('Modal closes on Cancel and restores body scroll', async ({ page }) => {
  await signIn(page);
  await page.goto('/clients');

  await page.evaluate(() => (window as any).openAddClientModal());
  await expect(page.locator('#clientFormModal')).toBeVisible();

  await page.getByRole('button', { name: 'Cancel' }).click();
  await expect(page.locator('#clientFormModal')).not.toBeVisible();

  const overflow = await page.evaluate(() => document.body.style.overflow);
  expect(overflow).toBe('');
});

test('Modal closes on the close (X) button', async ({ page }) => {
  await signIn(page);
  await page.goto('/clients');

  await page.evaluate(() => (window as any).openAddClientModal());
  await expect(page.locator('#clientFormModal')).toBeVisible();

  await page.locator('#clientFormModal [aria-label="Close"]').click();
  await expect(page.locator('#clientFormModal')).not.toBeVisible();
});

test('Edit modal sets Edit mode labels', async ({ page }) => {
  await signIn(page);
  await page.goto('/clients');

  await page.evaluate(() => (window as any).openEditModal(123));
  await expect(page.locator('#clientFormModal')).toBeVisible();

  // Edit labels applied synchronously on show('edit', id)
  await expect(page.locator('#cfmTitle')).toHaveText('Edit Client');
  await expect(page.locator('#cfmSubtitle')).toContainText('Update client information');
  await expect(page.locator('#clientFormSubmit')).toHaveText('Save Client');

  // Subtitle (small print) also reflects edit mode
  await expect(page.locator('#cfmSubtitle')).toHaveText('Update client information');
});

test('Edit modal loads the form and stores the client id', async ({ page }) => {
  await signIn(page);
  await page.goto('/clients');

  // Resolve a real client id from the table; skip if no clients exist.
  await page.waitForSelector('#clientsTable tbody tr');
  const clientId = await page.evaluate(() => {
    const row = document.querySelector('#clientsTable tbody tr');
    // client_id_label cell format: "Client ID: <id>"
    const cell = row?.querySelector('.client-cell-id')?.textContent ?? '';
    const m = cell.match(/Client ID:\s*(\d+)/);
    return m ? m[1] : null;
  });
  if (!clientId) {
    test.skip(true, 'No client id resolvable from the table — skipping edit form load test');
    return;
  }

  await page.evaluate((id: string) => (window as any).openEditModal(id), clientId);
  await expect(page.locator('#clientFormModal')).toBeVisible();

  // The loaded form carries the client id so the photo/save flow can reuse it
  await page.waitForFunction((id: string) => {
    const form = document.querySelector('#clientFormModalBody form') as HTMLFormElement | null;
    return form && form.dataset.clientId === id;
  }, clientId);
});

test('Modal traps focus within the dialog (Tab)', async ({ page }) => {
  await signIn(page);
  await page.goto('/clients');

  await page.evaluate(() => (window as any).openAddClientModal());
  await expect(page.locator('#clientFormModal form')).toBeVisible();

  const dialog = page.locator('#clientFormModal');
  const focusableCount = await dialog.locator('input:not([type=hidden]), textarea, select, button').count();
  expect(focusableCount).toBeGreaterThan(0);

  for (let i = 0; i < focusableCount + 2; i++) {
    await page.keyboard.press('Tab');
    const inside = await page.evaluate(() => {
      const el = document.activeElement;
      return el ? el.closest('#clientFormModal') !== null : false;
    });
    expect(inside, `Focus escaped dialog on Tab press #${i + 1}`).toBe(true);
  }
});
