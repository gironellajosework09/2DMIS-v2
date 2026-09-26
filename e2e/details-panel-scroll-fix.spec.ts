import { test, expect } from '@playwright/test';
import fs from 'node:fs';
import { fileURLToPath } from 'node:url';

// Details Panel mobile/tablet scroll fix — focused regression.
//
// Scope: the ONLY production change is the shell's stacking order in
// resources/views/partials/details-panel.blade.php:
//   .details-panel .details-backdrop  -> z-index 1
//   .details-panel-inner              -> position: relative; z-index 2
// so the panel content paints ABOVE the dark backdrop at widths <1024px and
// #detailsBody (a valid overflow-y:auto scroller) receives user scrolling.
//
// This spec is intentionally AUTH-FREE: the clients screen requires login and
// the smoke_superadmin account is retired from local main_system (restoring it
// would be a prohibited write). Instead it builds a throwaway harness page from
// the REAL production stylesheet (extracted from resources/css/app.css, between
// the MARKER-BEGIN/END: details-panel delimiters) plus a faithful panel shell +
// QR card, then verifies the bug contract at mobile, tablet, and desktop widths
// with no server and no database involvement.
//
// Harness replication of unchanged JS behavior (DetailsPanel.js):
//   * open()           -> + .open on #detailsPanel, body overflow hidden
//   * close()          -> - .open, body overflow restored
//   * #detailsClose    -> close
//   * Escape key       -> close
//   * #detailsBackdrop -> close (only reachable where content doesn't cover it)

const cssPath = fileURLToPath(new URL('../resources/css/app.css', import.meta.url));

function extractStyle(): string {
  // The DetailsPanel stylesheet lives in app.css, delimited by
  // MARKER-BEGIN: details-panel / MARKER-END: details-panel. The Blade
  // partial no longer carries an internal <style> block.
  const raw = fs.readFileSync(cssPath, 'utf8');
  const match = raw.match(/\/\* MARKER-BEGIN: details-panel \*\/([\s\S]*?)\/\* MARKER-END: details-panel \*\//);
  if (!match) throw new Error('DetailsPanel stylesheet markers not found in ' + cssPath);
  return match[1];
}

function panelShell(): string {
  // Faithful copy of the partial's DOM shell: backdrop first, then
  // .details-panel-inner (header -> close -> identity, actions, body).
  // No inline styles — the shell relies on the app.css details-panel rules.
  return [
    '<div id="detailsPanel" class="details-panel" role="dialog" aria-modal="true" aria-labelledby="detailsPanelTitle" aria-hidden="true">',
    '    <div class="details-backdrop" id="detailsBackdrop" tabindex="-1" aria-hidden="true"></div>',
    '    <div class="details-panel-inner">',
    '        <header class="details-header">',
    '            <button type="button" class="details-close" id="detailsClose" aria-label="Close details panel">',
    '                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">',
    '                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
    '                </svg>',
    '            </button>',
    '            <div class="details-identity">',
    '                <div class="details-avatar" id="detailsAvatar" aria-hidden="true"></div>',
    '                <div>',
    '                    <h2 id="detailsPanelTitle" class="text-base font-semibold text-white">JUAN, DELA CRUZ</h2>',
    '                    <div class="details-sub" id="detailsSub">#C-0001</div>',
    '                    <div class="details-meta" id="detailsMeta"></div>',
    '                </div>',
    '            </div>',
    '        </header>',
    '        <div class="details-actions" id="detailsActions">',
    '            <button class="btn">+ Add Transaction</button>',
    '        </div>',
    '        <div class="details-body" id="detailsBody">',
    '            <div id="bodyContent"></div>',
    '        </div>',
    '    </div>',
    '</div>',
  ].join('\n');
}

// Long content so #detailsBody must scroll; includes the real QR identity card
// markup (120px image, static text) unchanged from clients/_details.blade.php.
function longBodyContent(): string {
  const qrCard = [
    '<div class="details-section">',
    '    <h4 class="details-section-title">QR Identity Card</h4>',
    '    <div class="data-card p-[1.25rem]">',
    '        <div class="flex flex-wrap items-center gap-[16px]">',
    '            <img id="clientQrImage"',
    '                src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=ABC123ABC123AB12&format=png"',
    '                alt="Client QR code"',
    '                class="h-[120px] w-[120px] shrink-0 ring-1 ring-line"',
    '                width="120" height="120">',
    '            <div class="min-w-0">',
    '                <div id="clientQrLabel" class="text-dense font-semibold text-ink">Client QR Code</div>',
    '                <div id="clientQrHint" class="mt-[2px] text-dense text-ink-muted">For easy scan access</div>',
    '            </div>',
    '        </div>',
    '    </div>',
    '</div>',
  ].join('\n');

  const infoSection = [
    '<div class="details-section">',
    '    <h4 class="details-section-title">Personal Information</h4>',
    '    <div class="details-grid">',
    '        <div class="details-field"><label>First Name</label><span class="value">JUAN</span></div>',
    '        <div class="details-field"><label>Last Name</label><span class="value">DELA CRUZ</span></div>',
    '    </div>',
    '</div>',
  ].join('\n');

  const filler = '<div class="details-section"><h4 class="details-section-title">Additional Data</h4><p class="details-field-note">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor.</p></div>';
  return qrCard + infoSection + filler.repeat(18);
}

async function setupHarness(page: import('@playwright/test').Page): Promise<void> {
  const style = extractStyle();
  const harness = [
    '<!doctype html><html><head><meta charset="utf-8"><style>',
    style,
    '</style></head><body style="min-height:2000px;background:rgba(15,27,45,0.06)"><h2>App page</h2><div style="height:2500px">page scroll area</div>',
    panelShell(),
    `<script>
      const panel = document.getElementById('detailsPanel');
      document.getElementById('bodyContent').innerHTML = ${JSON.stringify(longBodyContent())};
      const close = () => {
        panel.classList.remove('open');
        panel.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
      };
      document.getElementById('detailsClose').addEventListener('click', close);
      document.getElementById('detailsBackdrop').addEventListener('click', close);
      document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && panel.classList.contains('open')) close(); });
      window.__openPanel = () => {
        panel.classList.add('open');
        panel.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
      };
    </script>`,
    '</body></html>',
  ].join('\n');
  await page.setContent(harness);
  await page.evaluate(() => (window as unknown as { __openPanel: () => void }).__openPanel());
  await page.waitForTimeout(400); // slide + fade transitions
}

async function topmostAt(page: import('@playwright/test').Page, x: number, y: number): Promise<string[]> {
  return page.evaluate(
    ([px, py]) =>
      document
        .elementsFromPoint(px, py)
        .slice(0, 4)
        .map((el) => el.id || String(el.className).split(' ')[0] || el.tagName),
    [x, y],
  );
}

// Content-layer tokens: any of these paints with/above the revealed content and
// proves the hit target is the content layer, not the backdrop.
const CONTENT_LAYER = ['details-section', 'bodyContent', 'details-body', 'detailsBody', 'detailsActions'];

// Scroll the drawer content using real pointer input. page.mouse.wheel works on
// every engine except mobile WebKit, which throws "Mouse wheel is not supported
// in mobile WebKit"; there we fall back to dispatching a wheel event at the same
// viewport point (untrusted wheel events do trigger default scrolling in WebKit).
async function scrollByWheel(page: import('@playwright/test').Page, x: number, y: number, deltaY: number): Promise<void> {
  try {
    await page.mouse.wheel(0, deltaY);
  } catch (err) {
    await page.evaluate(
      ([px, py, dy]) => {
        const el = document.elementFromPoint(px, py);
        if (!el) throw new Error('no element under pointer');
        el.dispatchEvent(new WheelEvent('wheel', { deltaY: dy, bubbles: true, cancelable: true }));
      },
      [x, y, deltaY],
    );
    return;
  }
  await page.waitForTimeout(50);
}

test('detail panel content sits above the backdrop and scrolls (mobile 390x844)', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await setupHarness(page);

  // 1. Panel can open.
  await expect(page.locator('#detailsPanel')).toHaveClass(/open/);

  // 2. #detailsBody exists.
  const body = page.locator('#detailsBody');
  await expect(body).toHaveCount(1);

  // 3. #detailsBody has overflow-y scrolling.
  await expect
    .poll(async () => body.evaluate((el) => getComputedStyle(el).overflowY))
    .toBe('auto');

  // 4+5. A point over visible details content resolves to the content layer, NOT the backdrop.
  const box = (await body.boundingBox())!;
  const stackAtContent = await topmostAt(page, box.x + box.width / 2, box.y + Math.min(120, box.height / 2));
  expect(stackAtContent[0]).not.toBe('detailsBackdrop');
  expect(stackAtContent.some((t) => CONTENT_LAYER.includes(t))).toBe(true);

  // 7. The QR card markup is intact and reachable (not covered by the backdrop).
  await expect(page.locator('#clientQrImage')).toHaveAttribute('width', '120');
  await expect(page.locator('#clientQrImage')).toHaveAttribute('height', '120');
  await expect(page.locator('#clientQrLabel')).toHaveText('Client QR Code');
  await expect(page.locator('#clientQrHint')).toHaveText('For easy scan access');
  const qrBox = (await page.locator('#clientQrImage').boundingBox())!;
  expect(qrBox.width).toBeGreaterThanOrEqual(100);
  expect(qrBox.width).toBeLessThanOrEqual(130);
  const qrStack = await topmostAt(page, qrBox.x + qrBox.width / 2, qrBox.y + qrBox.height / 2);
  expect(qrStack[0]).not.toBe('detailsBackdrop');

  // 6. User scrolling (wheel) increases #detailsBody.scrollTop.
  await body.evaluate((el) => (el.scrollTop = 0));
  const cx = box.x + box.width / 2;
  const cy = box.y + Math.min(200, box.height / 2);
  await page.mouse.move(cx, cy);
  await scrollByWheel(page, cx, cy, 600);
  await expect.poll(async () => body.evaluate((el) => el.scrollTop)).toBeGreaterThan(0);

  // 8. document.body stays overflow:hidden while the panel is open.
  await expect.poll(async () => page.evaluate(() => document.body.style.overflow)).toBe('hidden');

  // 10. Close via ESC.
  await page.keyboard.press('Escape');
  await expect(page.locator('#detailsPanel')).not.toHaveClass(/open/);

  // Re-open (same as the app calling DetailsPanel.open() again) and close via X.
  await page.evaluate(() => (window as unknown as { __openPanel: () => void }).__openPanel());
  await page.waitForTimeout(400);
  const closeBtn = page.locator('#detailsClose');
  const closeBox = (await closeBtn.boundingBox())!;
  const closeStack = await topmostAt(page, closeBox.x + closeBox.width / 2, closeBox.y + closeBox.height / 2);
  expect(closeStack[0]).not.toBe('detailsBackdrop'); // X button is reachable
  await closeBtn.click();
  await expect(page.locator('#detailsPanel')).not.toHaveClass(/open/);
});

test('detail panel stacking + scrolling contract holds on tablet (900x800)', async ({ page }) => {
  await page.setViewportSize({ width: 900, height: 800 });
  await setupHarness(page);

  await expect(page.locator('#detailsPanel')).toHaveClass(/open/);
  const body = page.locator('#detailsBody');
  await expect(body).toHaveCount(1);

  const box = (await body.boundingBox())!;
  const stack = await topmostAt(page, box.x + box.width / 2, box.y + Math.min(120, box.height / 2));
  expect(stack[0]).not.toBe('detailsBackdrop');
  expect(stack.some((t) => CONTENT_LAYER.includes(t))).toBe(true);

  await body.evaluate((el) => (el.scrollTop = 0));
  const cx = box.x + box.width / 2;
  await page.mouse.move(cx, box.y + Math.min(200, box.height / 2));
  await scrollByWheel(page, cx, box.y + Math.min(200, box.height / 2), 600);
  await expect.poll(async () => body.evaluate((el) => el.scrollTop)).toBeGreaterThan(0);

  await expect.poll(async () => page.evaluate(() => document.body.style.overflow)).toBe('hidden');
});

test('desktop (>=1024px) behavior is unchanged: backdrop hidden, panel functional, body scrollable', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await setupHarness(page);

  await expect(page.locator('#detailsPanel')).toHaveClass(/open/);
  const backdrop = page.locator('#detailsBackdrop');
  await expect.poll(async () => backdrop.evaluate((el) => getComputedStyle(el).display)).toBe('none');

  const body = page.locator('#detailsBody');
  const box = (await body.boundingBox())!;
  const stack = await topmostAt(page, box.x + box.width / 2, box.y + Math.min(120, box.height / 2));
  expect(stack[0]).not.toBe('detailsBackdrop');
  expect(stack.some((t) => CONTENT_LAYER.includes(t))).toBe(true);

  await body.evaluate((el) => (el.scrollTop = 0));
  await page.mouse.move(box.x + box.width / 2, box.y + Math.min(200, box.height / 2));
  await scrollByWheel(page, box.x + box.width / 2, box.y + Math.min(200, box.height / 2), 600);
  await expect.poll(async () => body.evaluate((el) => el.scrollTop)).toBeGreaterThan(0);

  // X close still works on desktop.
  await page.locator('#detailsClose').click();
  await expect(page.locator('#detailsPanel')).not.toHaveClass(/open/);
});