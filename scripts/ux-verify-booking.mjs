/**
 * UX Verification script — public booking page
 * Run: node scripts/ux-verify-booking.mjs
 */
import { chromium } from 'playwright';
import { writeFileSync } from 'fs';

const BASE = 'http://localhost:8080/booking';
const OUT  = '/tmp/ux-booking';

async function capture(page, name) {
  const path = `${OUT}-${name}.png`;
  await page.screenshot({ path, fullPage: false });
  return path;
}

async function run() {
  const browser = await chromium.launch();
  const results = [];

  // ── Mobile viewport (375 × 812) ──────────────────────────────────
  {
    const ctx  = await browser.newContext({ viewport: { width: 375, height: 812 } });
    const page = await ctx.newPage();

    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    page.on('console',   m => { if (m.type() === 'error') errors.push(m.text()); });

    await page.goto(BASE, { waitUntil: 'networkidle' });
    await page.waitForTimeout(800);

    const shot1 = await capture(page, 'mobile-initial');
    results.push({ label: 'Mobile — initial (empty state)', path: shot1, errors });

    // Check structural elements
    const providerSelect = await page.locator('select[name="provider_id"], select[data-select="provider"]').count();
    const serviceSelect  = await page.locator('select[name="service_id"],  select[data-select="service"]').count();
    const cardSection    = await page.locator('section').count();
    const headings       = await page.locator('h3').allInnerTexts();

    console.log('\n--- Mobile checks ---');
    console.log('Provider <select> found:', providerSelect > 0 ? 'YES' : 'NO');
    console.log('Service <select> found: ', serviceSelect > 0  ? 'YES' : 'NO');
    console.log('<section> count:        ', cardSection);
    console.log('<h3> headings:          ', headings);
    console.log('JS console errors:      ', errors.length ? errors : 'none');
    console.log('Screenshot:             ', shot1);

    await ctx.close();
  }

  // ── Desktop viewport (1280 × 900) ────────────────────────────────
  {
    const ctx  = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    const page = await ctx.newPage();

    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    page.on('console',   m => { if (m.type() === 'error') errors.push(m.text()); });

    await page.goto(BASE, { waitUntil: 'networkidle' });
    await page.waitForTimeout(800);

    const shot2 = await capture(page, 'desktop-initial');
    results.push({ label: 'Desktop — initial (empty state)', path: shot2, errors });

    console.log('\n--- Desktop checks ---');
    console.log('JS console errors:', errors.length ? errors : 'none');
    console.log('Screenshot:       ', shot2);

    // Select provider (first non-default option) if available
    const providerOpts = await page.locator('select[name="provider_id"] option, select[data-select="provider"] option').count();
    if (providerOpts > 1) {
      await page.locator('select[name="provider_id"], select[data-select="provider"]').first().selectOption({ index: 1 });
      await page.waitForTimeout(600);
      const shot3 = await capture(page, 'desktop-provider-selected');
      console.log('Provider selected — screenshot:', shot3);

      // Select service (first non-default option) if available
      const serviceOpts = await page.locator('select[name="service_id"] option, select[data-select="service"] option').count();
      if (serviceOpts > 1) {
        await page.locator('select[name="service_id"], select[data-select="service"]').first().selectOption({ index: 1 });
        await page.waitForTimeout(600);
        const shot4 = await capture(page, 'desktop-both-selected');
        console.log('Both selected — screenshot:', shot4);
      } else {
        console.log('No service options loaded (possibly no DB data).');
      }
    } else {
      console.log('No provider options loaded (possibly no DB data).');
    }

    await ctx.close();
  }

  await browser.close();
  console.log('\n✅ Verification complete.');
}

run().catch(e => { console.error('❌', e.message); process.exit(1); });
