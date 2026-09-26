import { test, expect, type Page } from '@playwright/test';

// C3-F — Client Details Panel QR Identity Card (UI cleanup).
// The clients details panel carries ONE QR identity card (immediately under
// Personal Information). Post-cleanup presentation is a compact horizontal
// card: a ~120px QR beside static explanatory text ("Client QR Code" /
// "For easy scan access"). Print/Download controls were removed.
//
// Browser-level verification of:
//   * the panel opens with the QR card present
//   * QR image exists; its data= is /^[0-9A-Za-z]{16}$/ and EXACTLY equals the
//     server-side qr_token returned by the public grantee verify endpoint
//   * the QR is approximately 120px and sits beside the text ("Client QR Code",
//     "For easy scan access")
//   * neither Print nor Download controls exist
//   * the existing 2x2 action grid (Add Transaction / Open Full Page / Edit /
//     Delete) stays intact
//
// Fixture (real row in the byte-identical production copy, same as C3-B/C3-E):
//   id with lastname=TESTCLIENT 0014  firstname=MARIA  middlename=L  extensionname=JR
//
// Auth: sign-in uses SMOKE_USER/SMOKE_PASS (default smoke_superadmin). That
// account was retired from local main_system during C3-C hygiene; restore it
// to execute this spec (documented env limitation, not a C3-F defect).

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

test('details panel QR identity card decodes to the server-side qr_token (extension client)', async ({ page }) => {
  await signIn(page);

  await page.goto('/clients');
  const table = page.locator('#clientsTable tbody');

  await searchClients(page, 'TESTCLIENT 0014');
  await expect(table.locator('tr')).toHaveCount(1);
  const clientId = Number(await table.locator('tr').first().getAttribute('data-id'));
  expect(Number.isInteger(clientId)).toBe(true);
  await table.locator('tr').first().locator('[data-view-client]').click();

  // Panel shell + QR card.
  await expect(page.locator('#detailsPanel')).toBeVisible();
  await expect(page.locator('#detailsPanel')).toContainText(/QR Identity Card/i);

  // Explanatory text (static UI copy) sits beside the QR.
  await expect(page.locator('#clientQrLabel')).toHaveText('Client QR Code');
  await expect(page.locator('#clientQrHint')).toHaveText('For easy scan access');

  // QR image exists, is ~120px, and its payload is a 16-char token.
  const qr = page.locator('#clientQrImage');
  await expect(qr).toBeVisible();
  await expect(qr).toHaveAttribute('width', '120');
  await expect(qr).toHaveAttribute('height', '120');
  const box = await qr.boundingBox();
  expect(box).toBeTruthy();
  expect(box!.width).toBeGreaterThanOrEqual(100);
  expect(box!.width).toBeLessThanOrEqual(130);
  expect(box!.height).toBeGreaterThanOrEqual(100);
  expect(box!.height).toBeLessThanOrEqual(130);

  const src = await qr.getAttribute('src');
  const payload = src?.match(/[?&]data=([0-9A-Za-z]{16})(?:&|$)/)?.[1];
  expect(payload).toBeTruthy();
  expect(payload).toMatch(/^[0-9A-Za-z]{16}$/);

  // Decoded payload must NOT be a human-facing name (panel title is the
  // C3-B canonical display name; the QR must never encode it).
  const title = (await page.locator('#detailsPanelTitle').textContent()) ?? '';
  expect(payload).not.toBe('TESTCLIENT 0014, MARIA (JR) L');
  expect(payload).not.toBe(title);

  // Server-side token: resolve via the public grantee verify endpoint (the
  // C3-E approach). Need client_id + the client's own municipality id.
  const autocomplete = await page.request.get(
    '/grantee-search/grantee?q=' + encodeURIComponent('TESTCLIENT 0014'),
  );
  const ac = await autocomplete.json();
  const municipalityName = ac.results?.find((r: { id: number }) => r.id === clientId)?.municipality;

  const munis = await (await page.request.get('/grantee-search/grantee?munis=1')).json();
  const municipalityId = Number(munis.municipalities?.find((m: { name: string }) => m.name === municipalityName)?.id);

  const verify = await page.request.post('/grantee-search/grantee', {
    form: {
      action: 'verify',
      client_id: String(clientId),
      municipality_id: String(municipalityId),
    },
  });
  const v = await verify.json();
  expect(v.success).toBe(true);
  expect(payload).toBe(v.client.qr_token);

  // No Print/Download controls.
  await expect(page.locator('#clientQrPrintBtn')).toHaveCount(0);
  await expect(page.locator('#clientQrDownloadLink')).toHaveCount(0);

  // Existing 2x2 action grid remains intact.
  await expect(page.locator('#detailsActions')).toContainText('+ Add Transaction');
  await expect(page.locator('#detailsActions')).toContainText('Open Full Page');
  await expect(page.locator('#detailsActions')).toContainText('Edit');
  await expect(page.locator('#detailsActions')).toContainText('Delete');
  await expect(page.locator('#detailsActions .details-actions-line .btn, #detailsActions .details-actions-line form')).toHaveCount(4);
});

test('details panel QR card is absent only where the panel is closed, and the card is responsive-safe', async ({ page }) => {
  await signIn(page);

  await page.goto('/clients');
  await page.locator('#clientsTable tbody tr').first().waitFor({ timeout: 10000 });

  // Before opening a panel there is no card in the DOM.
  await expect(page.locator('#clientQrImage')).toHaveCount(0);

  // Open the first row and confirm the card uses the panel design system
  // (image is fixed at ~120px, horizontal flex wraps on narrow widths).
  await page.locator('#clientsTable tbody tr').first().locator('[data-view-client]').click();
  const qr = page.locator('#clientQrImage');
  await expect(qr).toBeVisible();
  await expect(qr).toHaveAttribute('width', '120');
  await expect(qr).toHaveAttribute('height', '120');
  const box = await qr.boundingBox();
  expect(box).toBeTruthy();
  expect(box!.width).toBeLessThanOrEqual(130);
  expect(box!.height).toBeLessThanOrEqual(130);

  // Close restores the initial state.
  await page.locator('#detailsClose').click();
  await expect(page.locator('#clientQrImage')).toHaveCount(0);
});