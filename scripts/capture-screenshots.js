/**
 * capture-screenshots.js
 * -----------------------------------------------------------------------------
 * Captures REAL screenshots of the running WebScheduler app for the marketing
 * site. Output → marketing-site/assets/screenshots/*.png (retina @2x, light mode).
 *
 * Reuses the login/seed recipe from .claude/skills/verify/SKILL.md:
 *   - Dev server expected at BASE_URL (default http://localhost:8080).
 *   - Log in at /auth/login with an ADMIN so admin-only screens
 *     (Analytics, User management) render with data.
 *
 * Prerequisites (see the plan): seed sample data
 *   php spark db:seed SchedulingSampleDataSeeder
 * and have an admin account. A throwaway admin can be used and deleted after.
 *
 * Config via env (all optional):
 *   BASE_URL        default http://localhost:8080
 *   ADMIN_EMAIL     default shots-admin@sample.local
 *   ADMIN_PASSWORD  default ShotPass!23
 *   HEADED=1        run headed for debugging
 *
 * Usage: npm run site:shots
 * -----------------------------------------------------------------------------
 */

import { chromium } from 'playwright';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const projectRoot = path.resolve(__dirname, '..');
const outDir = path.join(projectRoot, 'marketing-site', 'assets', 'screenshots');

const BASE_URL = (process.env.BASE_URL || 'http://localhost:8080').replace(/\/$/, '');
const ADMIN_EMAIL = process.env.ADMIN_EMAIL || 'shots-admin@sample.local';
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD || 'ShotPass!23';
const HEADED = process.env.HEADED === '1';

/** Authenticated screens: [outputName, urlPath, readySelector|null]. */
const SHOTS = [
  ['dashboard.png',        '/dashboard',            'main'],
  ['user-management.png',  '/user-management',      'table, .xs-card'],
  ['appointments.png',     '/appointments',         '.fc, [data-scheduler], .xs-card'],
  ['services.png',         '/services',             'table, .xs-card'],
  ['customers.png',        '/customer-management',  'table, .xs-card'],
  ['analytics.png',        '/analytics',            'canvas, .xs-card'],
];

function fail(msg) {
  console.error('❌ ' + msg);
  process.exit(1);
}

async function settle(page, ms = 900) {
  try { await page.waitForLoadState('networkidle', { timeout: 8000 }); } catch { /* best effort */ }
  await page.waitForTimeout(ms);
}

/** Remove the CI4 dev debug toolbar (injected async) just before a shot. */
async function stripDebugBar(page) {
  await page.evaluate(() => {
    document.querySelectorAll('[id*="debug" i],[class*="debug-bar" i]').forEach((el) => el.remove());
  }).catch(() => {});
}

async function waitForReady(page, selector) {
  if (!selector) return;
  try { await page.waitForSelector(selector, { timeout: 12000, state: 'visible' }); }
  catch { console.warn('  ⚠ ready selector not found, capturing anyway: ' + selector); }
}

(async () => {
  fs.mkdirSync(outDir, { recursive: true });
  console.log(`📸 Capturing screenshots from ${BASE_URL} → marketing-site/assets/screenshots/`);

  const browser = await chromium.launch({ headless: !HEADED });
  const context = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    deviceScaleFactor: 2,
    colorScheme: 'light',
  });

  // Force light theme before any app script runs (defeats the FOUC bootstrap)
  // and hide the CI4 dev debug toolbar so it never appears in a screenshot.
  await context.addInitScript(() => {
    try { localStorage.setItem('xs-theme', 'light'); } catch (e) { /* ignore */ }
    const css = '#debugbar,#debugbar_loader,#debug-icon,[id^="debugbar"]{display:none !important;visibility:hidden !important;}';
    const inject = () => {
      const s = document.createElement('style');
      s.textContent = css;
      (document.head || document.documentElement).appendChild(s);
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', inject);
    else inject();
  });

  const page = await context.newPage();

  // 1) Login screen (pre-auth) — capture it first, before we have a session.
  await page.goto(`${BASE_URL}/auth/login`, { waitUntil: 'domcontentloaded' });
  await settle(page, 600);
  await stripDebugBar(page);
  await page.screenshot({ path: path.join(outDir, 'login.png') });
  console.log('  • login.png');

  // 2) Authenticate.
  await page.fill('[name="email"]', ADMIN_EMAIL);
  await page.fill('[name="password"]', ADMIN_PASSWORD);
  await Promise.all([
    page.waitForURL('**/dashboard', { timeout: 15000 }).catch(() => {}),
    page.click('button[type="submit"]'),
  ]);
  await settle(page, 800);
  if (/\/auth\/login/.test(page.url())) {
    await browser.close();
    fail(`Login failed for ${ADMIN_EMAIL}. Check credentials / that the admin exists and is active.`);
  }

  // 3) Authenticated screens.
  for (const [name, urlPath, ready] of SHOTS) {
    await page.goto(`${BASE_URL}${urlPath}`, { waitUntil: 'domcontentloaded' });
    await waitForReady(page, ready);
    await settle(page, name === 'analytics.png' || name === 'appointments.png' ? 1600 : 900);
    await stripDebugBar(page);
    await page.screenshot({ path: path.join(outDir, name) });
    console.log('  • ' + name);
  }

  await browser.close();
  console.log(`✅ Done. ${SHOTS.length + 1} screenshots in ${path.relative(projectRoot, outDir)}/`);
})().catch((err) => fail(err && err.stack ? err.stack : String(err)));
