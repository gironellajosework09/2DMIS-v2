import { test, expect, type Page } from '@playwright/test';

// C3-C milestone smoke — persistent qr_token identity (additive DB column).
// C3-C adds NO UI: the P4 scanner engine, the public QR viewer, the details
// panel and the C3-B display formatter are all unchanged. These tests pin
// that unchanged contract end-to-end against the byte-identical production copy:
//   * /clients loads with the canonical C3-B display name; the details panel
//     opens the matching record with no QR card (unchanged)
//   * public /qr-viewer (view_qrcode.php parity) still loads, populates the
//     municipality list, and suggests comma-form full_name results for the QR
//   * C3-E: after verify the QR image data param contains the client's
//     qr_token (not full_name); the human-facing display label is unchanged
//   * the /scanners hub + the CEAP scanner engine render unchanged

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

test('clients list and details panel render the C3-B display name with no QR additions', async ({ page }) => {
  await signIn(page);

  await page.goto('/clients');
  const table = page.locator('#clientsTable tbody');

  await searchClients(page, 'TESTCLIENT 0014');
  await expect(table.locator('tr')).toHaveCount(1);
  await expect(table.locator('tr').first()).toContainText('TESTCLIENT 0014, MARIA (JR) L');
  await expect(table.locator('tr').first()).not.toContainText('MARIA L JR');

  await table.locator('tr').first().locator('[data-view-client]').click();
  await expect(page.locator('#detailsPanelTitle')).toHaveText('TESTCLIENT 0014, MARIA (JR) L');

  // No QR material appears in the panel (C3-C adds none; the panel is unchanged).
  await expect(page.locator('#detailsPanel')).not.toContainText(/QR/i);
});

test('public QR viewer loads, populates municipalities, and suggests comma-form full names', async ({ page }) => {
  await page.goto('/qr-viewer');

  // Static view renders (view_qrcode.php parity).
  await expect(page.locator('#nameInput')).toBeVisible();
  await expect(page.locator('#verifyBtn')).toBeDisabled();
  await expect(page.locator('#verifyBtn')).toHaveText('Verify & Load My QR Code');

  // Municipalities populate from the public grantee-search endpoint.
  await expect(page.locator('#municipalitySelect option')).not.toHaveCount(1);

  // Name search still returns the persisted comma-form full_name for the
  // human-facing suggestion (unchanged). C3-E: the QR data payload itself
  // is now the client's opaque qr_token — verified end-to-end below.
  await page.fill('#nameInput', 'TESTCLIENT 0013');
  await page.waitForSelector('#suggestList button');
  const suggestion = await page.locator('#suggestList button').first().textContent();
  expect(suggestion).toBe('TESTCLIENT 0013, GLORIA N');
  expect(suggestion).not.toMatch(/[0-9A-Za-z]{16}/);
  await page.locator('#suggestList button').first().click();

  // Selecting a suggestion arms the verify flow as before.
  await expect(page.locator('#verifyBtn')).toBeEnabled();
  expect(page.url()).toContain('/qr-viewer');
});

test('scanner hub and the CEAP scanner engine still render', async ({ page }) => {
  await signIn(page);

  await page.goto('/scanners');
  await expect(page.locator('#scannerSelect')).toBeVisible();
  await expect(page.locator('#scannerOpen')).toBeVisible();
  await expect(page.locator('#scannerOpen')).toHaveAttribute('href', /\/scanners\/ceap/);

  await page.goto('/scanners/ceap');
  await expect(page.locator('#scanner-screen')).toBeVisible();
  await expect(page.locator('#reader')).toBeVisible();
  await expect(page.locator('#scanResultArea')).toBeHidden();
});

test('C3-E QR viewer: verify returns qr_token and QR img encodes the token', async ({ page }) => {
  // Navigate to the public QR viewer (no auth required).
  await page.goto('/qr-viewer');
  await expect(page.locator('#nameInput')).toBeVisible();

  // Intercept the verify POST to capture the full server response (including
  // qr_token) — the page's JS builds the QR client-side from this payload.
  let verifyPayload: Record<string, unknown> | null = null;
  page.on('response', async (response) => {
    if (response.url().includes('grantee-search') && response.request().method() === 'POST') {
      try {
        verifyPayload = await response.json() as Record<string, unknown>;
      } catch { /* not JSON */ }
    }
  });

  // A known fixture client with a qualifying CEAP-family transaction.
  // Resolve its record (and its exact municipality) from the public
  // grantee-search endpoint so the verify step passes.
  const searchResp = await page.request.get('/grantee-search/grantee?q=TESTCLIENT%200013');
  const searchJson = await searchResp.json() as { success: boolean; results: Array<{ id: number; full_name: string; municipality: string }> };
  expect(searchJson.success).toBe(true);
  expect(searchJson.results.length).toBeGreaterThan(0);
  const expectedMuni = searchJson.results[0].municipality;
  expect(expectedMuni.length).toBeGreaterThan(0);

  // Search the client on the page and select the first suggestion.
  await page.fill('#nameInput', 'TESTCLIENT 0013');
  await page.waitForSelector('#suggestList button');
  await page.locator('#suggestList button').first().click();

  // Select the client's own municipality (matches the verify contract).
  const muniSelect = page.locator('#municipalitySelect');
  await muniSelect.waitFor({ state: 'attached' });
  await expect(muniSelect.locator('option')).not.toHaveCount(1);
  await muniSelect.selectOption({ label: expectedMuni });

  // Click verify — this is a read-only fetch; no DB writes.
  await page.click('#verifyBtn');
  await page.waitForSelector('#qrContainer:not(.hidden)', { timeout: 10000 });

  // The QR image must now exist.
  const img = page.locator('#qrImage img');
  await expect(img).toBeVisible();
  const imgSrc = await img.getAttribute('src');

  // Extract the data query parameter (the QR payload the provider renders).
  const qrData = new URL(imgSrc!).searchParams.get('data');

  // C3-E: payload must be the client's opaque qr_token, not a name.
  expect(qrData).toBeTruthy();
  expect(qrData).toMatch(/^[0-9A-Za-z]{16}$/);

  // Cross-check: the intercepted verify response's qr_token matches the
  // img data param exactly — proves the page encodes the server token.
  const serverToken = (verifyPayload as any)?.client?.qr_token;
  expect(serverToken).toBeTruthy();
  expect(qrData).toBe(serverToken);

  // The human-facing display label is still the persisted full_name.
  const displayLabel = await page.locator('#qrName').textContent();
  expect(displayLabel).toContain('TESTCLIENT 0013');
  expect(displayLabel).not.toMatch(/^[0-9A-Za-z]{16}$/); // never a raw token
});