import { test, expect, type Page } from '@playwright/test';

/**
 * Phase 7 — GIP modal Alpine.js migration verification.
 *
 * Environment limitations:
 *  - `smoke_superadmin` test user does not exist → signIn() fails
 *  - No GIP transactions in the production-copy database → GIP section not rendered
 *
 * These tests document the GIP modal contract and will pass once the test
 * environment is seeded with the appropriate user and GIP transaction data.
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

/**
 * Helper: navigate to a client's full-page profile that has a GIP transaction.
 * Requires test data with a GIP transaction to exist.
 */
async function goToClientWithGip(page: Page): Promise<void> {
  // Find a client with a GIP transaction via the client feed
  await page.goto('/clients');
  await page.waitForSelector('#clientsTable tbody tr');

  // Search for a client known to have a GIP transaction.
  // The test seed must include at least one client with program='GIP'.
  // If no GIP client exists, this test will fail with a clear message.
  const rows = page.locator('#clientsTable tbody tr');
  const count = await rows.count();
  expect(count, 'No clients found in the table — check test data').toBeGreaterThan(0);

  // Click the first client to open the details panel, then navigate to full page
  await rows.first().click();
  await page.waitForTimeout(500);

  // Look for the GIP accordion section in the details panel
  const gipSection = page.locator('#headingGIP');
  if (await gipSection.count() === 0) {
    // No GIP section visible — try navigating to a specific client with GIP
    // This requires knowledge of the test data
    test.skip(true, 'No GIP transaction found in test data — skipping GIP modal tests');
    return;
  }
}

test('GIP accordion renders and toggles via Alpine', async ({ page }) => {
  await signIn(page);
  await goToClientWithGip(page);

  const heading = page.locator('#headingGIP');
  await expect(heading).toBeVisible();

  // Accordion button should have aria-expanded="false" initially
  const button = heading.locator('button');
  await expect(button).toHaveAttribute('aria-expanded', 'false');

  // Content should be hidden initially
  const content = page.locator('#collapseGIP');
  await expect(content).not.toBeVisible();

  // Click to expand
  await button.click();
  await expect(button).toHaveAttribute('aria-expanded', 'true');
  await expect(content).toBeVisible();

  // Click again to collapse
  await button.click();
  await expect(button).toHaveAttribute('aria-expanded', 'false');
  await expect(content).not.toBeVisible();
});

test('GIP modal opens when Edit/Add button is clicked', async ({ page }) => {
  await signIn(page);
  await goToClientWithGip(page);

  // Expand the accordion
  const button = page.locator('#headingGIP button');
  await button.click();
  await expect(page.locator('#collapseGIP')).toBeVisible();

  // Click the Edit or Add button to open the modal
  const editBtn = page.locator('#collapseGIP').getByRole('button', { name: /GIP Details/ });
  await editBtn.click();

  // Modal should be visible
  const modalTitle = page.locator('#gipModalTitle');
  await expect(modalTitle).toBeVisible();

  // Check accessibility attributes
  const dialog = page.locator('[role="dialog"][aria-modal="true"]');
  await expect(dialog).toBeVisible();
  await expect(dialog).toHaveAttribute('aria-labelledby', 'gipModalTitle');

  // Form should be present with correct action
  const form = dialog.locator('form');
  await expect(form).toHaveAttribute('method', 'POST');

  // client_id hidden input should be present
  await expect(dialog.locator('input[name="client_id"]')).toBeVisible();
});

test('GIP modal closes on ESC key', async ({ page }) => {
  await signIn(page);
  await goToClientWithGip(page);

  // Open accordion and modal
  await page.locator('#headingGIP button').click();
  await page.locator('#collapseGIP').getByRole('button', { name: /GIP Details/ }).click();
  await expect(page.locator('#gipModalTitle')).toBeVisible();

  // Press ESC
  await page.keyboard.press('Escape');

  // Modal should close
  await expect(page.locator('#gipModalTitle')).not.toBeVisible();
});

test('GIP modal closes on backdrop click', async ({ page }) => {
  await signIn(page);
  await goToClientWithGip(page);

  // Open accordion and modal
  await page.locator('#headingGIP button').click();
  await page.locator('#collapseGIP').getByRole('button', { name: /GIP Details/ }).click();
  await expect(page.locator('#gipModalTitle')).toBeVisible();

  // Click the backdrop (outside the dialog)
  await page.locator('[role="dialog"]').locator('div').first().click({ position: { x: 5, y: 5 } });

  // Modal should close
  await expect(page.locator('#gipModalTitle')).not.toBeVisible();
});

test('GIP modal closes on Cancel button', async ({ page }) => {
  await signIn(page);
  await goToClientWithGip(page);

  // Open accordion and modal
  await page.locator('#headingGIP button').click();
  await page.locator('#collapseGIP').getByRole('button', { name: /GIP Details/ }).click();
  await expect(page.locator('#gipModalTitle')).toBeVisible();

  // Click Cancel
  await page.getByRole('button', { name: 'Cancel' }).click();

  // Modal should close
  await expect(page.locator('#gipModalTitle')).not.toBeVisible();
});

test('GIP modal form fields are populated with existing data', async ({ page }) => {
  await signIn(page);
  await goToClientWithGip(page);

  // Expand accordion — if GIP data exists, the Edit button should be present
  await page.locator('#headingGIP button').click();
  const editBtn = page.locator('#collapseGIP').getByRole('button', { name: 'Edit GIP Details' });

  if (await editBtn.count() === 0) {
    test.skip(true, 'No existing GIP data — Add mode only');
    return;
  }

  await editBtn.click();
  await expect(page.locator('#gipModalTitle')).toHaveText('Edit GIP Details');

  // Title should say "Edit GIP Details"
  // Submit button should say "Update GIP Details"
  await expect(page.getByRole('button', { name: 'Update GIP Details' })).toBeVisible();

  // Fields should have values (not empty)
  await expect(page.locator('#gip_valid_govt_id')).not.toBeEmpty();
});

test('GIP modal shows Add mode when no GIP data exists', async ({ page }) => {
  await signIn(page);
  await goToClientWithGip(page);

  // Expand accordion — if no GIP data, Add button should be present
  await page.locator('#headingGIP button').click();
  const addBtn = page.locator('#collapseGIP').getByRole('button', { name: 'Add GIP Details' });

  if (await addBtn.count() === 0) {
    test.skip(true, 'Existing GIP data found — Edit mode only');
    return;
  }

  await addBtn.click();
  await expect(page.locator('#gipModalTitle')).toHaveText('Add GIP Details');

  // Submit button should say "Save GIP Details"
  await expect(page.getByRole('button', { name: 'Save GIP Details' })).toBeVisible();

  // Fields should be empty
  await expect(page.locator('#gip_valid_govt_id')).toBeEmpty();
});

test('GIP modal traps focus within the dialog', async ({ page }) => {
  await signIn(page);
  await goToClientWithGip(page);

  // Open accordion and modal
  await page.locator('#headingGIP button').click();
  await page.locator('#collapseGIP').getByRole('button', { name: /GIP Details/ }).click();
  await expect(page.locator('#gipModalTitle')).toBeVisible();

  // Tab through focusable elements — focus should stay within the dialog
  const dialog = page.locator('[role="dialog"]');
  const focusableCount = await dialog.locator('input:not([type=hidden]), textarea, select, button').count();

  // Tab through all elements and verify focus stays in dialog
  for (let i = 0; i < focusableCount + 2; i++) {
    await page.keyboard.press('Tab');
    const focused = await page.evaluate(() => {
      const el = document.activeElement;
      return el ? el.closest('[role="dialog"]') !== null : false;
    });
    expect(focused, `Focus escaped dialog on Tab press #${i + 1}`).toBe(true);
  }
});
