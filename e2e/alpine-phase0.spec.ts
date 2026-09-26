import { test, expect } from '@playwright/test';
import type { Page } from 'playwright-core';

// Phase 0 (2026-09-03) — Alpine.js foundation smoke verification.
// Phase 27 (2026-09-07) — assertions flipped: the Bootstrap 5.3.2 CSS CDN
//   is REMOVED (was: still served); the project-owned ui.css parity layer
//   that replaces it (§4.8–4.10) must be present instead.
// M6.16 (2026-09-13) — ui.css RETIRED and DELETED; the styling now flows
//   entirely from the compiled app.css Vite bundle (canonical owner).
//
// Environment note: the local `main_system` is a byte-identical production copy
// with no `smoke_superadmin` seed user and the project deliberately does not seed
// test users into it, so authenticated shell routes cannot be driven here. These
// checks exercise the public login page to prove the app still boots, the Vite
// build serves cleanly with no broken/erroring requests, and Bootstrap CSS is
// no longer loaded. Alpine presence is verified at build-bundle level (see
// the Phase 0 report) and on the shell once an authenticated session is available.

async function collectErrors(page: Page): Promise<string[]> {
  const errors: string[] = [];
  page.on('pageerror', (err) => errors.push(`pageerror: ${err.message}`));
  page.on('console', (msg) => {
    if (msg.type() === 'error') errors.push(`console.error: ${msg.text()}`);
  });
  page.on('requestfailed', (req) =>
    errors.push(`requestfailed: ${req.url()} (${req.failure()?.errorText ?? 'unknown'})`),
  );
  return errors;
}

test('login page boots cleanly with Bootstrap CSS removed (Phase 27)', async ({ page }) => {
  const errors = await collectErrors(page);

  await page.goto('/login', { waitUntil: 'networkidle' });

  await expect(page.getByLabel('Username')).toBeVisible();
  await expect(page.getByLabel('Password')).toBeVisible();

  // Phase 27: Bootstrap CSS is no longer served.
  const bootstrapCss = page.locator('link[href*="bootstrap@5.3.2"]');
  await expect(bootstrapCss).toHaveCount(0);

  // M6.16: public/css/ui.css is RETIRED and DELETED — no ui.css stylesheet
  // link (or request) may remain.
  const uiCss = page.locator('link[href$="ui.css"]');
  await expect(uiCss).toHaveCount(0);

  // The retired parity layer is fully served by the compiled app.css bundle.
  const appCss = page.locator('link[rel="stylesheet"][href*="/build/assets/app-"]');
  await expect(appCss).toHaveCount(1);

  // The login page is intentionally NOT wired with the Alpine bundle (standalone
  // pages only load app.js when their interaction is actually migrated to Alpine).
  const appJsOnLogin = page.locator('script[src*="/build/assets/app-"]');
  await expect(appJsOnLogin).toHaveCount(0);

  // No page/console errors and no failed requests (build did not break asset serving).
  expect(errors, JSON.stringify(errors)).toEqual([]);
});
