import { test, expect, type Page } from '@playwright/test';

/**
 * Phase 10 — Client photo modal (#photoModal) Tailwind + Alpine migration.
 *
 * Behavior contract (parity with the previous Bootstrap modal):
 *  - #photoModal is an upload/camera surface (Use Camera / Capture / Retake /
 *    file picker / Save Photo) rendering in the client details view
 *    (full-page show + details panel).
 *  - Same modal ID, image/field IDs (#cameraImage, #video, #canvas,
 *    #capturedPreview, #photoFile, #startCameraBtn, #captureBtn, #retakeBtn),
 *    same endpoint (clients.photo.store), same CSRF.
 *  - Default Bootstrap close semantics: backdrop click AND ESC close; the
 *    close (X) and Cancel buttons close.
 *  - Dialog semantics: role=dialog, aria-modal=true, labelled by the title,
 *    Tab trapped inside, body scroll locked while open.
 *  - The modal no longer carries any Bootstrap JS contract:
 *      * no data-bs-toggle / data-bs-target / data-bs-dismiss
 *      * no bootstrap.Modal / shown.bs.modal / hidden.bs.modal for it.
 *  - Imperative, framework-agnostic bridge: window.openClientPhotoModal().
 *
 * Environment limitations:
 *  - `smoke_superadmin` test user does not exist → signIn() fails, same known
 *    limitation as Phases 8/9. The modal is dormant in the clients module
 *    (no standalone trigger), so opening via the bridge is exercised with the
 *    Alpine component directly after an authenticated session exists.
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

test('Photo modal renders Alpine dialog semantics on the client details page', async ({ page }) => {
  await signIn(page);
  await page.goto('/clients');

  const dialog = page.locator('#photoModal');
  await expect(dialog).toHaveAttribute('role', 'dialog');
  await expect(dialog).toHaveAttribute('aria-modal', 'true');
  await expect(dialog).toHaveAttribute('aria-labelledby', 'photoModalTitle');

  // Field/control IDs preserved
  await expect(page.locator('#cameraImage')).toBeAttached();
  await expect(page.locator('#video')).toBeAttached();
  await expect(page.locator('#canvas')).toBeAttached();
  await expect(page.locator('#capturedPreview')).toBeAttached();
  await expect(page.locator('#photoFile')).toBeAttached();
  await expect(page.locator('#startCameraBtn')).toBeAttached();
  await expect(page.locator('#captureBtn')).toBeAttached();
  await expect(page.locator('#retakeBtn')).toBeAttached();

  // Hidden by default (x-cloak / closed state)
  await expect(dialog).not.toBeVisible();
});

test('Photo modal opens via window.openClientPhotoModal bridge', async ({ page }) => {
  await signIn(page);
  await page.goto('/clients');

  await page.evaluate(() => {
    (window as any).openClientPhotoModal();
  });
  await expect(page.locator('#photoModal')).toBeVisible();
  await expect(page.locator('#photoModalTitle')).toHaveText('Client Profile Photo');
  // Use Camera is the initial camera control visible (file picker revealed)
  await expect(page.locator('#startCameraBtn')).toBeVisible();
  await expect(page.locator('#captureBtn')).not.toBeVisible();
  await expect(page.locator('#retakeBtn')).not.toBeVisible();
});

test('Photo modal closes on the close (X), Cancel, ESC and backdrop', async ({ page }) => {
  await signIn(page);
  await page.goto('/clients');

  for (const closeMethod of ['x', 'cancel', 'escape', 'backdrop']) {
    await page.evaluate(() => {
      (window as any).openClientPhotoModal();
    });
    await expect(page.locator('#photoModal')).toBeVisible();

    if (closeMethod === 'x') {
      await page.locator('#photoModal [aria-label="Close"]').click();
    } else if (closeMethod === 'cancel') {
      await page.getByRole('button', { name: 'Cancel' }).last().click();
    } else if (closeMethod === 'escape') {
      await page.keyboard.press('Escape');
    } else {
      await page.mouse.click(10, 10);
    }

    await expect(page.locator('#photoModal')).not.toBeVisible();
  }
});

test('Photo modal traps focus within the dialog (Tab)', async ({ page }) => {
  await signIn(page);
  await page.goto('/clients');

  await page.evaluate(() => {
    (window as any).openClientPhotoModal();
  });
  await expect(page.locator('#photoModal')).toBeVisible();

  const dialog = page.locator('#photoModal');
  const focusableCount = await dialog.locator('button, input:not([type=hidden])').count();
  expect(focusableCount).toBeGreaterThan(0);

  for (let i = 0; i < focusableCount + 2; i++) {
    await page.keyboard.press('Tab');
    const inside = await page.evaluate(() => {
      const el = document.activeElement;
      return el ? el.closest('#photoModal') !== null : false;
    });
    expect(inside, `Focus escaped dialog on Tab press #${i + 1}`).toBe(true);
  }
});

test('Photo modal no longer depends on Bootstrap JS', async ({ page }) => {
  await signIn(page);
  await page.goto('/clients');

  const html = await page.locator('body').innerHTML();
  // The photo modal must not carry Bootstrap show/dismiss/toggle attributes,
  // and no bootstrap.Modal wiring remains for it in the clients markup.
  expect(html).not.toContain('data-bs-dismiss="modal"');
  expect(html).not.toContain('data-bs-target="#photoModal"');
  // Bootstrap modal structure classes are gone from the photo surface too.
  expect(html).not.toContain('id="photoModal" class="modal');
});
