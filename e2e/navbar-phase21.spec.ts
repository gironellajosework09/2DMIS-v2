import { test, expect, type Page } from '@playwright/test';

/**
 * Phase 21 — Navbar hamburger (mobile navigation trigger) → Alpine.js.
 *
 * Page: `partials/navbar.blade.php` (included by `layouts/app.blade.php`).
 * The hamburger (<lg only, `lg:hidden`) toggles the shared sidebar drawer via
 * `$store.sidebar` (registered in `partials/sidebar.blade.php` under
 * `alpine:init`). Phase 18 flagged it as inert: every Alpine directive sat
 * outside any `x-data` component, so Alpine never bound it (proven live in
 * Chromium: `_x_dataStack:false`, `aria-expanded:null`, click did nothing).
 * Phase 21 adds the smallest correct scope — `x-data` on the button itself —
 * so the existing `$store.sidebar` toggle/bindings actually compile and run.
 *
 * There is NO Bootstrap Collapse involvement: the hamburger carries no
 * `data-bs-*` attributes (Bootstrap offcanvas wiring was replaced in a prior
 * phase) and the drawer is Tailwind transform-driven. Close paths preserved
 * from the existing drawer design: the drawer's own X button (header), backdrop
 * click, and ESC (the open drawer at z-100 overlays the topbar at z-50, so
 * re-clicking the hamburger while open is not part of the design).
 */
const SMOKE_USER = process.env.SMOKE_USER ?? 'smoke_superadmin';
const SMOKE_PASS = process.env.SMOKE_PASS ?? 'SmokeAdmin2026!';

test.setTimeout(90000);

// The smoke account enforces single-device login (session_token), so parallel
// workers would overwrite each other's token and the 2s session watchdog would
// redirect earlier workers back to /login mid-assertion. Run serially.
test.describe.configure({ mode: 'serial' });

async function signIn(page: Page): Promise<void> {
  await page.goto('/login');
  await page.getByLabel('Username').fill(SMOKE_USER);
  await page.getByLabel('Password').fill(SMOKE_PASS);
  await page.getByRole('button', { name: 'Login' }).click();
  await expect(page).toHaveURL(/\/$/);
}

const hamburger = (page: Page) => page.getByRole('button', { name: 'Toggle navigation' });
const sidebar = (page: Page) => page.locator('#appSidebar');
const OFF_CANVAS = /-translate-x-full/;
const OPEN = /translate-x-0/;

test('mobile: hamburger renders closed, opens the drawer, and reflects aria-expanded', async ({ page }) => {
  await page.setViewportSize({ width: 393, height: 851 });
  await signIn(page);

  const h = hamburger(page);
  const sb = sidebar(page);

  // Closed state: hamburger visible, drawer off-canvas, `aria-expanded=false`.
  await expect(h).toBeVisible();
  await expect(h).toHaveAttribute('aria-controls', 'appSidebar');
  await expect(h).toHaveAttribute('aria-expanded', 'false');
  await expect(sb).toHaveClass(OFF_CANVAS);

  // Alpine has actually bound the button (x-data component scope present).
  await expect
    .poll(() => page.evaluate(() => {
      const el = document.querySelector('button[aria-label="Toggle navigation"]') as unknown as { _x_dataStack?: unknown } | null;
      return Boolean(el?._x_dataStack);
    }))
    .toBe(true);

  // Open: click toggles the shared store; drawer slides in; aria-expanded flips.
  await h.click();
  await expect(sb).toHaveClass(OPEN, { timeout: 5000 });
  await expect(h).toHaveAttribute('aria-expanded', 'true');
});

test('mobile: drawer closes via X, backdrop, and ESC with aria-expanded sync', async ({ page }) => {
  await page.setViewportSize({ width: 393, height: 851 });
  await signIn(page);

  const h = hamburger(page);
  const sb = sidebar(page);

  // Cycle 1 — close via the drawer's own X button (header, aria-label="Close").
  await h.click();
  await expect(sb).toHaveClass(OPEN, { timeout: 5000 });
  await sb.getByRole('button', { name: 'Close' }).click();
  await expect(sb).toHaveClass(OFF_CANVAS, { timeout: 5000 });
  await expect(h).toHaveAttribute('aria-expanded', 'false');

  // Cycle 2 — close via the backdrop (outside the 280px drawer).
  await h.click();
  await expect(sb).toHaveClass(OPEN, { timeout: 5000 });
  await page.mouse.click(380, 400);
  await expect(sb).toHaveClass(OFF_CANVAS, { timeout: 5000 });
  await expect(h).toHaveAttribute('aria-expanded', 'false');

  // Cycle 3 — close via ESC.
  await h.click();
  await expect(sb).toHaveClass(OPEN, { timeout: 5000 });
  await page.keyboard.press('Escape');
  await expect(sb).toHaveClass(OFF_CANVAS, { timeout: 5000 });
  await expect(h).toHaveAttribute('aria-expanded', 'false');

  // Scroll lock released once closed again.
  expect(await page.evaluate(() => document.body.style.overflow)).toBe('');
});

test('mobile: repeated open/close cycles and navigation links stay usable', async ({ page }) => {
  await page.setViewportSize({ width: 393, height: 851 });
  await signIn(page);

  const h = hamburger(page);
  const sb = sidebar(page);

  for (let i = 0; i < 3; i++) {
    await h.click();
    await expect(sb).toHaveClass(OPEN, { timeout: 5000 });
    await expect(h).toHaveAttribute('aria-expanded', 'true');
    await page.keyboard.press('Escape');
    await expect(sb).toHaveClass(OFF_CANVAS, { timeout: 5000 });
    await expect(h).toHaveAttribute('aria-expanded', 'false');
  }

  // Navigation remains usable: open the drawer and follow a sidebar link.
  await h.click();
  await expect(sb).toHaveClass(OPEN, { timeout: 5000 });
  await sb.getByRole('link', { name: 'Dashboard' }).click();
  await expect(page).toHaveURL(/\/$/);
});

test('desktop: navbar unchanged, hamburger hidden, permanent sidebar intact', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await signIn(page);

  // Desktop: hamburger is `lg:hidden` (reserved for <lg)…
  await expect(hamburger(page)).toBeHidden();

  // …and the sidebar is permanently visible without any store interaction.
  const sb = sidebar(page);
  await expect(sb).toBeVisible();
  await expect(sb).toHaveClass(/lg:translate-x-0/);

  // Desktop chrome still renders (breadcrumb + user greeting in the dropdown).
  await expect(page.getByText('2DMIS', { exact: true })).toBeVisible();
  await expect(page.getByRole('button', { name: /Welcome, smoke_superadmin/ })).toBeVisible();
});

test('mobile: no Bootstrap Collapse dependency for the hamburger', async ({ page }) => {
  await page.setViewportSize({ width: 393, height: 851 });
  await signIn(page);

  // The trigger itself has zero Bootstrap attributes.
  const h = hamburger(page);
  await expect(h).not.toHaveAttribute('data-bs-toggle', expect.anything());
  await expect(h).not.toHaveAttribute('data-bs-target', expect.anything());

  // The compiled page source carries no live Bootstrap Collapse wiring.
  const source = await page.content();
  expect(source).not.toContain('bootstrap.Collapse');
  expect(source).not.toContain('new bootstrap.Collapse');
  expect(source).not.toContain('data-bs-toggle="collapse"');

  // Yet the drawer opens — Alpine owns the behavior.
  await h.click();
  await expect(sidebar(page)).toHaveClass(OPEN, { timeout: 5000 });
  await expect(h).toHaveAttribute('aria-expanded', 'true');
});