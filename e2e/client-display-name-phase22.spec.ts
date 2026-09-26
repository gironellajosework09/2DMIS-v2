import { test, expect, type Page } from '@playwright/test';

// C3-B — canonical client display-name formatter (LAST, FIRST (EXT) MIDDLE).
// Display-only verification:
//   * the clients table + details panel render the canonical display format
//   * an extension client shows "LAST, FIRST (EXT) MIDDLE" (stored v1 order
//     is "LAST, FIRST MIDDLE EXT"; the new order must never appear)
//   * the real GIRONELLA pair (id 1 vs id 1003) is now distinguishable in the
//     table (display shows the middle name on id 1 only)
//   * details panel action buttons are unchanged (Add Transaction / Open Full
//     Page / Edit / Delete)
//   * C3-E shipped the QR payload switch after this spec (no change here),
//     and C3-F later added the QR identity card to the panel (its own spec:
//     client-details-qr-card-phase24.spec.ts) — so the pre-C3-F "no QR card"
//     assertion was intentionally replaced by the presence check below. The
//     C3-F UI cleanup turned the card into static explanatory text, so the
//     canonical name stays covered here via the panel title only.
//
// Fixtures are real rows in the byte-identical production copy:
//   id 16  lastname=TESTCLIENT 0014  firstname=MARIA  middlename=L  extensionname=JR
//   id 1   GIRONELLA, JOSE SOLIVIO   (middle present)
//   id 1003 GIRONELLA, JOSE          (middle blank)

const SMOKE_USER = process.env.SMOKE_USER ?? 'smoke_superadmin';
const SMOKE_PASS = process.env.SMOKE_PASS ?? 'SmokeAdmin2026!';

async function signIn(page: Page): Promise<void> {
  await page.goto('/login');
  await page.getByLabel('Username').fill(SMOKE_USER);
  await page.getByLabel('Password').fill(SMOKE_PASS);
  await page.getByRole('button', { name: 'Login' }).click();
  await expect(page).toHaveURL(/\/$/);
}

async function searchClients(page: Page, term: string): Promise<void> {
  await page.locator('#clientsTable tbody tr').first().waitFor({ timeout: 10000 });
  await page.fill('#clientsSearch', term);
  await page.waitForTimeout(800); // debounce (250ms) + server-side draw
}

test('extension client renders canonical LAST, FIRST (EXT) MIDDLE in table and panel', async ({ page }) => {
  await signIn(page);

  await page.goto('/clients');
  const table = page.locator('#clientsTable tbody');

  await searchClients(page, 'TESTCLIENT 0014');
  await expect(table.locator('tr')).toHaveCount(1);

  // Canonical display: extension BEFORE middle, parenthesized. The stored v1
  // order "TESTCLIENT 0014, MARIA L JR" must NOT be what the user sees.
  await expect(table.locator('tr').first()).toContainText('TESTCLIENT 0014, MARIA (JR) L');
  await expect(table.locator('tr').first()).not.toContainText('MARIA L JR');

  // Details panel title uses the same canonical form.
  await table.locator('tr').first().locator('[data-view-client]').click();
  await expect(page.locator('#detailsPanelTitle')).toHaveText('TESTCLIENT 0014, MARIA (JR) L');

  // Panel action buttons are unchanged by C3-B.
  await expect(page.locator('#detailsActions')).toContainText('+ Add Transaction');
  await expect(page.locator('#detailsActions')).toContainText('Open Full Page');
  await expect(page.locator('#detailsActions')).toContainText('Edit');
  await expect(page.locator('#detailsActions')).toContainText('Delete');

  // C3-F added the QR identity card to the panel (replacing the C3-B
  // absence check) — the action grid remains the four buttons above. The
  // card's own text is static copy ("Client QR Code"), so the canonical
  // name stays verified via the panel title only.
  await expect(page.locator('#detailsPanel')).toContainText(/QR/i);
});

test('non-extension client renders canonical LAST, FIRST MIDDLE without parentheses', async ({ page }) => {
  await signIn(page);

  await page.goto('/clients');
  const table = page.locator('#clientsTable tbody');

  await searchClients(page, 'GIRONELLA');
  await expect(table.locator('tr')).toHaveCount(2);

  // Both rows carry the canonical shape; only the middle name differs.
  const rows = await table.locator('tr').allTextContents();
  expect(rows.some((r) => r.includes('GIRONELLA, JOSE SOLIVIO'))).toBe(true);
  expect(rows.some((r) => r.includes('GIRONELLA, JOSE') && !r.includes('SOLIVIO'))).toBe(true);

  // No stray empty parentheses from the blank extension.
  for (const row of rows) {
    expect(row).not.toContain('()');
  }
});

test('gironella pair is distinguishable and panel opens the matching record', async ({ page }) => {
  await signIn(page);

  await page.goto('/clients');
  const table = page.locator('#clientsTable tbody');

  await searchClients(page, 'GIRONELLA');
  await expect(table.locator('tr')).toHaveCount(2);

  // Open the detail panel from the row WITHOUT a middle name (id 1003) and
  // verify the panel shows exactly that row's display name.
  const rows = table.locator('tr');
  let target = rows.filter({ hasText: 'JOSE' }).filter({ hasNotText: 'SOLIVIO' });
  const targetCount = await target.count();
  if (targetCount !== 1) {
    // fall back to first row when the filter is ambiguous, then assert anyway
    target = rows.first();
  }
  await target.locator('[data-view-client]').click();
  await expect(page.locator('#detailsPanelTitle')).toHaveText('GIRONELLA, JOSE');
});