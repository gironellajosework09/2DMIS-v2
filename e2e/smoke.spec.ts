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

test('sidebar groups + hubs render and navigate for a super admin', async ({ page }) => {
  await signIn(page);

  const sidebar = page.locator('#appSidebar');
  await expect(sidebar).toBeVisible();

  await expect(sidebar.getByText('Scanner Engine', { exact: true })).toBeVisible();
  await expect(sidebar.getByText('Payouts', { exact: true })).toBeVisible();
  await expect(sidebar.getByText('Scholars', { exact: true })).toBeVisible();

  const accessToggle = sidebar.locator('button.sidebar-group-toggle');
  await expect(accessToggle).toContainText('Access Control');
  await expect(accessToggle).toHaveAttribute('aria-expanded', 'false');

  await accessToggle.click();
  await expect(sidebar.getByText('Manage Permissions', { exact: true })).toBeVisible();
  await expect(accessToggle).toHaveAttribute('aria-expanded', 'true');
});

test('scanner engine hub page loads', async ({ page }) => {
  await signIn(page);

  await page.goto('/scanners');
  await expect(page.getByRole('heading', { name: 'Scanner Engine' })).toBeVisible();
});

test('payouts hub links every destination for a super admin', async ({ page }) => {
  await signIn(page);

  await page.goto('/payouts');
  await expect(page.getByRole('heading', { name: 'Payouts' })).toBeVisible();

  // UX-4: the hub is a tabbed workspace (one tab per destination) rather than
  // the previous card grid; every destination must still be present + linked.
  for (const label of [
    'Attendance',
    'Attendance 2',
    'Attendance Unpaid',
    'Unpaid Grantees',
    'Open Payout Scanner',
    'Open Unpaid Scanner',
  ]) {
    const tab = page.getByRole('tab', { name: label, exact: true });
    await expect(tab).toHaveCount(1);
    await expect(tab).toBeVisible();
    await expect(tab).toHaveAttribute('href', /\S/);
  }
});

test('scholars page opens with sidebar intact', async ({ page }) => {
  await signIn(page);

  await page.goto('/scholars');
  await expect(page.getByRole('heading', { name: 'Scholars' })).toBeVisible();
  await expect(page.locator('#appSidebar')).toBeVisible();
});