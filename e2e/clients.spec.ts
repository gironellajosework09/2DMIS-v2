import { test, expect, type Page } from '@playwright/test';

const SMOKE_USER = process.env.SMOKE_USER ?? 'smoke_superadmin';
const SMOKE_PASS = process.env.SMOKE_PASS ?? 'SmokeAdmin2026!';

async function signIn(page: Page): Promise<void> {
  await page.goto('/login');
  await page.getByLabel('Username').fill(SMOKE_USER);
  await page.getByLabel('Password').fill(SMOKE_PASS);
  await page.getByRole('button', { name: 'Login' }).click();
  await expect(page).toHaveURL(/\/$/);
}

test('clients index renders the simplified table and Add modal', async ({ page }) => {
  await signIn(page);

  await page.goto('/clients');
  await expect(page.getByRole('heading', { name: 'Client Registry' })).toBeVisible();

  const table = page.locator('#clientsTable');
  await expect(table).toBeVisible();

  // Simplified visible columns: Client, Precinct No, Municipality, Barangay, Category, Actions
  const headers = table.locator('thead th');
  await expect(headers.nth(0)).toHaveText('Client');
  await expect(headers.nth(1)).toHaveText(/Precinct/);
  await expect(headers.nth(2)).toHaveText('Municipality');
  await expect(headers.nth(3)).toHaveText('Barangay');
  await expect(headers.nth(4)).toHaveText('Category');

  // Add Client opens the modal (no full-page navigation)
  await page.getByRole('button', { name: '+ Add Client' }).click();
  const modal = page.locator('#clientFormModal');
  await expect(modal).toBeVisible();
  await expect(modal.locator('#clientFormModalTitle')).toHaveText('Add Client');
  await expect(modal.getByLabel(/Last Name/)).toBeVisible();
  await expect(page).not.toHaveURL(/\/clients\/create$/);
});

test('each filter pill opens its own focused popover with options', async ({ page }) => {
  await signIn(page);
  await page.goto('/clients');

  const host = page.locator('[data-filter-host="clients-filters"]');
  const menu = host.locator('[data-filter-menu]');

  // Click Municipality -> its own popover opens and renders municipal options.
  await page.locator('[data-filter-segment="municipality"]').click();
  await expect(menu).toBeVisible();
  await expect(host.locator('section[data-filter-cat="municipality"]')).toBeVisible();
  await expect(host.locator('section[data-filter-cat="municipality"] [data-filter-option]').first()).toBeVisible();

  // Only the clicked category is shown (the others stay hidden).
  await expect(host.locator('section[data-filter-cat="category"]')).not.toBeVisible();

  // Switching to Category reveals that section instead.
  await page.locator('[data-filter-segment="category"]').click();
  await expect(host.locator('section[data-filter-cat="category"]')).toBeVisible();
  await expect(host.locator('section[data-filter-cat="municipality"]')).not.toBeVisible();

  // Close via Done.
  await page.locator('[data-filter-done]').click();
  await expect(menu).not.toBeVisible();
});

test('single search filters by precinct no and name', async ({ page }) => {
  await signIn(page);
  await page.goto('/clients');

  const table = page.locator('#clientsTable tbody');
  await expect(table.locator('tr').first()).toBeVisible();

  // One search control only (the toolbar owns #clientsSearch).
  await expect(page.locator('#clientsSearch')).toHaveCount(1);

  // Precinct search returns the matching client.
  await page.fill('#clientsSearch', 'P-0100');
  await expect(table.locator('tr')).toHaveCount(1);

  // Name search still works.
  await page.fill('#clientsSearch', 'GIRONELLA');
  await expect(table.locator('tr')).toHaveCount(1);
  await expect(table).toContainText('GIRONELLA');

  // Clearing restores the full set.
  await page.fill('#clientsSearch', '');
  await page.waitForTimeout(500);
  await expect(table.locator('tr').first()).toBeVisible();
});

test('pagination renders exactly once', async ({ page }) => {
  await signIn(page);
  await page.goto('/clients');

  await expect(page.locator('.dataTables_paginate')).toHaveCount(1);
  await expect(page.locator('.dataTables_length')).toHaveCount(1);
  await expect(page.locator('.dataTables_info')).toHaveCount(1);
});
