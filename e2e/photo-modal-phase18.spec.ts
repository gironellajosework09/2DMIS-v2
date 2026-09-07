import { test, expect, type Page } from '@playwright/test';

/**
 * Phase 18 — Students Photo Modal (`#photoModal` → Tailwind + Alpine.js).
 *
 * Page: `unpaid`-style public student flow — `/student/verify/:id` (public
 * birthdate+mobile check) redirects to `/student/photo-upload`, the only page
 * that actually hosts `#photoModal` (the scholars/show "Photo" button targets
 * a modal that never existed on that page — dead trigger, attributes removed).
 *
 * Contract preserved:
 *  - Trigger "Take Photo" (same class/text/placement) now calls
 *    `Alpine.store('photoModal').openModal()` instead of `data-bs-toggle`.
 *  - The modal is static markup hidden via `x-cloak` + `x-show`; it opens with
 *    `role=dialog`, `aria-modal=true`, `aria-labelledby=photoModalTitle`.
 *  - Camera lifecycle (unchanged page-script logic) is driven by the Alpine
 *    open/close lifecycle exactly like the old `shown.bs.modal` /
 *    `hidden.bs.modal` handlers: open → camera starts only once the modal is
 *    visible; close (ESC / backdrop — the ONLY closers the old modal had; it
 *    had no X and no Cancel button) → every MediaStreamTrack stops. Reopen
 *    starts a fresh live stream. No persistent stream, no duplicate handlers.
 *  - Focus enters on `#cameraSelect`, Tab/Shift+Tab are trapped, focus +
 *    body scroll are restored on close.
 *  - Capture/preview/retake JS (canvas → dataURL → `#cameraImage`) unchanged.
 *
 * Environment: real camera is faked for this suite via Chromium's
 * `--use-fake-ui-for-media-stream` + `--use-fake-device-for-media-stream`
 * launch flags (per-test only — no global permission/server change). The
 * student verify step uses a real client row of the local production-copy DB
 * (read via the public verify route; it only sets a session marker and writes
 * nothing). Override with E2E_CLIENT_ID / E2E_BIRTHDATE / E2E_MOBILE. The
 * de-facto sample is client id=1 (birthdate 2006-07-14, mobile 09123456789).
 */
test.use({
  launchOptions: {
    args: [
      '--use-fake-ui-for-media-stream',
      '--use-fake-device-for-media-stream',
    ],
  },
});

const CLIENT_ID = process.env.E2E_CLIENT_ID ?? '1';
const BIRTHDATE = process.env.E2E_BIRTHDATE ?? '2006-07-14';
const MOBILE = process.env.E2E_MOBILE ?? '09123456789';

async function reachPhotoUpload(page: Page): Promise<void> {
  await page.goto(`/student/verify/${CLIENT_ID}`);
  await page.locator('#birthdate').fill(BIRTHDATE);
  await page.locator('#mobile').fill(MOBILE);
  await page.getByRole('button', { name: 'Verify' }).click();
  await expect(page).toHaveURL(/\/student\/photo-upload$/);
}

async function openModal(page: Page): Promise<void> {
  await page.getByRole('button', { name: 'Take Photo' }).click();
  const dialog = page.getByRole('dialog', { name: 'Capture Photo' });
  await expect(dialog).toBeVisible();
}

const liveTracks = (page: Page) =>
  page.evaluate(() => {
    const v = document.getElementById('video') as HTMLVideoElement | null;
    const s = v && v.srcObject ? (v.srcObject as MediaStream) : null;
    if (!s) return { present: false, live: 0, ended: 0 };
    return {
      present: true,
      live: s.getTracks().filter((t) => t.readyState === 'live').length,
      ended: s.getTracks().filter((t) => t.readyState === 'ended').length,
    };
  });

test('student verify flow reaches photo-upload; modal hidden, dialog semantics, no Bootstrap-Modal dependency', async ({ page }) => {
  await reachPhotoUpload(page);

  await expect(page.getByRole('heading', { name: 'Update Profile Photo' })).toBeVisible();
  const trigger = page.getByRole('button', { name: 'Take Photo' });
  await expect(trigger).toBeVisible();

  // Hidden until opened (x-cloak + x-show; no persistent visible markup).
  await expect(page.getByRole('dialog', { name: 'Capture Photo' })).toHaveCount(0);

  await openModal(page);

  const dialog = page.getByRole('dialog', { name: 'Capture Photo' });
  await expect(dialog).toHaveAttribute('aria-modal', 'true');
  await expect(dialog).toHaveAttribute('aria-labelledby', 'photoModalTitle');

  // All capture controls preserved.
  await expect(page.locator('#cameraSelect')).toBeVisible();
  await expect(page.locator('#video')).toBeVisible();
  await expect(page.locator('#capturedPreview')).toBeHidden();
  await expect(page.locator('#captureBtn')).toBeVisible();

  // The media store form is a zero-height POST form (camera_image input) —
  // assert its presence and contract, not visibility.
  await expect(page.locator('#photoForm')).toHaveCount(1);
  await expect(page.locator('#photoForm')).toHaveAttribute('method', 'POST');
  await expect(page.locator('#photoForm input[name="camera_image"]')).toHaveCount(1);

  // No Bootstrap Modal dependency in the page's own scripts.
  const scripts = await page.locator('script').allTextContents();
  const combined = scripts.join('\n');
  expect(combined).not.toContain('bootstrap.Modal(');
  expect(combined).not.toContain('getOrCreateInstance');
  expect(combined).not.toContain('data-bs-toggle');
  expect(combined).not.toContain('data-bs-target');
  expect(combined).not.toContain("addEventListener('shown.bs.modal'");
  expect(combined).not.toContain("addEventListener('hidden.bs.modal'");
  expect(combined).toContain(`Alpine.store('photoModal'`);
  expect(combined).toContain('openModal: function');
  expect(combined).toContain('window.photoModalComponent');
});

test('open: focus enters cameraSelect, body scroll locked; capture + retake JS unchanged', async ({ page }) => {
  await reachPhotoUpload(page);
  await openModal(page);

  // Focus enters the first interactive control (the old modal had no X/close
  // button, so the camera selector is the entry point — same as Tab start).
  await expect(page.locator('#cameraSelect')).toBeFocused();

  // Body scroll locked while open.
  await expect
    .poll(() => page.evaluate(() => document.body.style.overflow))
    .toBe('hidden');

  // Capture → canvas dataURL lands in the hidden input; preview/buttons flip.
  await page.locator('#captureBtn').click();
  await expect(page.locator('#capturedPreview')).toBeVisible();
  await expect(page.locator('#video')).toBeHidden();
  await expect(page.locator('#cameraButtons')).toBeHidden();
  await expect(page.locator('#previewButtons')).toBeVisible();
  await expect.poll(() => page.locator('#cameraImage').inputValue()).toContain('data:image/jpeg');

  // Retake restores the live preview UI (cameraImage stays set, as before).
  await page.locator('#retakeBtn').click();
  await expect(page.locator('#capturedPreview')).toBeHidden();
  await expect(page.locator('#video')).toBeVisible();
  await expect(page.locator('#cameraButtons')).toBeVisible();
  await expect(page.locator('#previewButtons')).toBeHidden();
});

test('camera lifecycle: open→live stream, ESC→stopped, reopen→fresh live stream, backdrop→stopped', async ({ page }) => {
  await reachPhotoUpload(page);

  // 1st open → camera started (getUserMedia resolves with the fake device).
  await openModal(page);
  await expect.poll(() => liveTracks(page)).toEqual({ present: true, live: 1, ended: 0 });

  // ESC closes (old default keyboard:true) → every track stopped.
  await page.keyboard.press('Escape');
  await expect(page.getByRole('dialog', { name: 'Capture Photo' })).toHaveCount(0);
  await expect.poll(() => liveTracks(page)).toEqual({ present: true, live: 0, ended: 1 });
  // Focus + scroll restored to the trigger.
  await expect(page.getByRole('button', { name: 'Take Photo' })).toBeFocused();
  await expect
    .poll(() => page.evaluate(() => document.body.style.overflow))
    .toBe('');

  // 2nd open → fresh live stream (no stale/ended stream reused).
  await openModal(page);
  await expect.poll(() => liveTracks(page)).toEqual({ present: true, live: 1, ended: 0 });

  // Backdrop click closes (old default backdrop:true) → stopped again.
  await page.mouse.click(12, 12);
  await expect(page.getByRole('dialog', { name: 'Capture Photo' })).toHaveCount(0);
  await expect.poll(() => liveTracks(page)).toEqual({ present: true, live: 0, ended: 1 });

  // Reopen a 3rd time → single video element, fresh live stream again.
  await openModal(page);
  await expect(page.locator('#video')).toHaveCount(1);
  await expect.poll(() => liveTracks(page)).toEqual({ present: true, live: 1, ended: 0 });
  await page.keyboard.press('Escape');
  await expect.poll(() => liveTracks(page)).toEqual({ present: true, live: 0, ended: 1 });

  // Camera never kept alive with the modal closed.
  await expect(page.getByRole('dialog', { name: 'Capture Photo' })).toHaveCount(0);
});

test('Tab / Shift+Tab are trapped inside the dialog', async ({ page }) => {
  await reachPhotoUpload(page);
  await openModal(page);

  const dialog = page.getByRole('dialog', { name: 'Capture Photo' });
  const focusables = dialog.locator(
    'select:not([disabled]), button:not([disabled])',
  );
  const count = await focusables.count();
  expect(count).toBeGreaterThan(1);

  const ids: string[] = [];
  for (let i = 0; i < count + 2; i++) {
    await page.keyboard.press('Tab');
    ids.push(await page.evaluate(() => (document.activeElement as HTMLElement).id));
  }
  // Every focus step stays inside the dialog (cycled through its controls).
  for (const id of ids) {
    expect(dialog.locator(`#${id}`)).toHaveCount(1);
  }

  for (let i = 0; i < count + 2; i++) {
    await page.keyboard.press('Shift+Tab');
    const id = await page.evaluate(() => (document.activeElement as HTMLElement).id);
    expect(dialog.locator(`#${id}`)).toHaveCount(1);
  }

  await page.keyboard.press('Escape');
});