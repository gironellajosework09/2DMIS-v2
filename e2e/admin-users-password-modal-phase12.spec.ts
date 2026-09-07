import { test, expect, type Page } from '@playwright/test';

/**
 * Phase 12 — Admin/Users password reset modal (#passwordModal) Tailwind + Alpine migration.
 *
 * Behavior contract (parity with the previous Bootstrap modal in
 * resources/views/admin/users/index.blade.php):
 *  - #passwordModal is a server-side password-reset form dialog (#passwordForm):
 *    @csrf, PUT method, hidden #user_id, disabled #modal_username, and
 *    `password` / `password_confirmation` inputs (required, minlength 8).
 *  - The modal opens from a Reset button carrying data-id / data-username
 *    (server-rendered rows AND the DataTables AJAX `.reset-btn` rows both
 *    drive it via one delegated handler); form.action is set to
 *    /admin/users/{id}/password and the hidden user-id + username are
 *    populated from the trigger (explicit transfer — no Bootstrap relatedTarget).
 *  - Default Bootstrap close semantics preserved: backdrop click AND ESC close;
 *    the close (X) and Cancel buttons close.
 *  - Dialog semantics: role=dialog, aria-modal=true, labelled by the title,
 *    Tab trapped inside, body scroll locked while open, focus enters on open
 *    and is restored to the trigger on close.
 *  - The modal no longer carries any Bootstrap JS contract:
 *      * no data-bs-toggle / data-bs-target / data-bs-dismiss for it
 *      * no bootstrap.Modal / show.bs.modal / relatedTarget for it.
 *  - Imperative, framework-agnostic bridge: window.openPasswordResetModal(id, username).
 *
 * Environment limitations:
 *  - `smoke_superadmin` test user does not exist → signIn() fails, same known
 *    limitation as Phases 8-11. Opening via the bridge is therefore exercised
 *    with the Alpine component directly once an authenticated session exists.
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

test('Password modal renders Alpine dialog semantics & preserved form contract on the users page', async ({ page }) => {
  await signIn(page);
  await page.goto('/admin/users');

  const dialog = page.locator('#passwordForm');
  await expect(dialog).toHaveAttribute('role', 'dialog');
  await expect(dialog).toHaveAttribute('aria-modal', 'true');
  await expect(dialog).toHaveAttribute('aria-labelledby', 'passwordModalTitle');
  await expect(dialog).toHaveAttribute('aria-describedby', 'passwordModalBody');

  // Form contract preserved: method, hidden user-id, fields, labels.
  await expect(dialog).toHaveAttribute('method', 'POST');
  await expect(dialog.locator('input[name="user_id"]')).toHaveAttribute('type', 'hidden');
  await expect(dialog.locator('input[name="password"]')).toHaveAttribute('minlength', '8');
  await expect(dialog.locator('input[name="password_confirmation"]')).toHaveAttribute('type', 'password');
  await expect(dialog.locator('input[name="_token"]')).toBeAttached();
  await expect(dialog.getByLabel('Username')).toBeAttached();

  // Hidden by default (x-cloak / closed state)
  await expect(dialog).not.toBeVisible();
});

test('Password modal opens via bridge and transfers trigger data into the form', async ({ page }) => {
  await signIn(page);
  await page.goto('/admin/users');

  await page.evaluate(() => {
    (window as any).openPasswordResetModal(42, 'clerk');
  });
  await expect(page.locator('#passwordForm')).toBeVisible();
  await expect(page.locator('#passwordModalTitle')).toHaveText('Reset Password');
  await expect(page.locator('#user_id')).toHaveValue('42');
  await expect(page.locator('#modal_username')).toHaveValue('clerk');
  await expect(page.locator('#passwordForm')).toHaveAttribute('action', /\/admin\/users\/42\/password$/);
  // Focus enters the dialog on open (close button is first focusable, matching Bootstrap default)
  const activeId = await page.evaluate(() => document.activeElement?.getAttribute('aria-label'));
  expect(activeId).toBe('Close');
});

test('Password modal closes on the close (X), Cancel, ESC and backdrop', async ({ page }) => {
  await signIn(page);
  await page.goto('/admin/users');

  for (const closeMethod of ['x', 'cancel', 'escape', 'backdrop'] as const) {
    await page.evaluate(() => {
      (window as any).openPasswordResetModal(42, 'clerk');
    });
    await expect(page.locator('#passwordForm')).toBeVisible();

    if (closeMethod === 'x') {
      await page.locator('#passwordForm [aria-label="Close"]').click();
    } else if (closeMethod === 'cancel') {
      await page.getByRole('button', { name: 'Cancel' }).click();
    } else if (closeMethod === 'escape') {
      await page.keyboard.press('Escape');
    } else {
      await page.mouse.click(10, 10);
    }

    await expect(page.locator('#passwordForm')).not.toBeVisible();
  }
});

test('Password modal traps focus within the dialog (Tab)', async ({ page }) => {
  await signIn(page);
  await page.goto('/admin/users');

  await page.evaluate(() => {
    (window as any).openPasswordResetModal(42, 'clerk');
  });
  await expect(page.locator('#passwordForm')).toBeVisible();

  const dialog = page.locator('#passwordForm');
  const focusableCount = await dialog.locator('button:not([disabled]), input:not([type=hidden]):not([disabled])').count();
  expect(focusableCount).toBeGreaterThan(0);

  for (let i = 0; i < focusableCount + 2; i++) {
    await page.keyboard.press('Tab');
    const inside = await page.evaluate(() => {
      const el = document.activeElement;
      return el ? el.closest('#passwordForm') !== null : false;
    });
    expect(inside, `Focus escaped dialog on Tab press #${i + 1}`).toBe(true);
  }
});

test('Password modal no longer depends on Bootstrap JS', async ({ page }) => {
  await signIn(page);
  await page.goto('/admin/users');

  const html = await page.locator('body').innerHTML();
  // No data-bs-toggle/data-bs-target pointing at #passwordModal, no
  // data-bs-dismiss="modal", no bootstrap.Modal / show.bs.modal wiring for it.
  expect(html).not.toContain('data-bs-target="#passwordModal"');
  expect(html).not.toContain('bootstrap.Modal');
  expect(html).not.toContain('show.bs.modal');
  // Bootstrap modal structure classes are gone from the password surface too.
  await expect(page.locator('#passwordForm')).not.toHaveClass(/modal/);
});
