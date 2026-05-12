import { test, expect } from '@playwright/test';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const SCREENSHOTS = path.join(__dirname, 'screenshots');

async function loginAsAdmin(page) {
    await page.goto('/auth/login');
    await page.fill('[name="email"]', process.env.ADMIN_EMAIL ?? '');
    await page.fill('[name="password"]', process.env.ADMIN_PASSWORD ?? '');
    await page.click('[type="submit"]');
    await page.waitForURL('**/dashboard**', { timeout: 15_000 });
}

test.describe('Appointments header controls', () => {
    test.beforeEach(async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto('/appointments');
        // Wait for the scheduler to initialise before screenshotting
        await page.waitForSelector('#scheduler-date-display', { timeout: 15_000 });
        await page.waitForTimeout(800); // allow JS to finish rendering
    });

    test('header — light mode', async ({ page }, testInfo) => {
        await expect(page.locator('#scheduler-date-display')).toBeVisible();
        await expect(page.locator('[data-calendar-action="today"]')).toBeVisible();
        await expect(page.locator('#advanced-filter-toggle')).toBeVisible();
        await expect(page.locator('#scheduler-stats-bar')).toBeVisible();
        const shot = await page.locator('.xs-header').screenshot();
        await testInfo.attach('header-light', { body: shot, contentType: 'image/png' });
        await page.screenshot({ path: `${SCREENSHOTS}/${testInfo.project.name}-header-light.png` });
    });

    test('header — dark mode', async ({ page }, testInfo) => {
        await page.evaluate(() => {
            document.documentElement.setAttribute('data-theme', 'dark');
            document.documentElement.classList.add('dark');
        });
        await page.waitForTimeout(300);
        const shot = await page.locator('.xs-header').screenshot();
        await testInfo.attach('header-dark', { body: shot, contentType: 'image/png' });
        await page.screenshot({ path: `${SCREENSHOTS}/${testInfo.project.name}-header-dark.png` });
    });

    test('filter panel opens', async ({ page }, testInfo) => {
        await page.click('#advanced-filter-toggle');
        await expect(page.locator('#advanced-filter-panel')).toBeVisible();
        await page.waitForTimeout(200);
        const shot = await page.locator('.xs-header').screenshot();
        await testInfo.attach('header-filters-open', { body: shot, contentType: 'image/png' });
        await page.screenshot({ path: `${SCREENSHOTS}/${testInfo.project.name}-header-filters-open.png` });
    });

    test('Week view active state', async ({ page }, testInfo) => {
        await page.click('[data-calendar-action="week"]');
        await page.waitForTimeout(500);
        const weekBtn = page.locator('[data-calendar-action="week"]');
        // Active button should have the primary background class added by JS
        await expect(weekBtn).toHaveClass(/bg-primary-600/);
        const shot = await page.locator('.xs-header').screenshot();
        await testInfo.attach('header-week-active', { body: shot, contentType: 'image/png' });
        await page.screenshot({ path: `${SCREENSHOTS}/${testInfo.project.name}-header-week-active.png` });
    });

    test('status pill filter click', async ({ page }, testInfo) => {
        const pendingPill = page.locator('[data-filter-status="pending"]').first();
        await expect(pendingPill).toBeVisible();
        await pendingPill.click();
        await page.waitForTimeout(400);
        await page.screenshot({ path: `${SCREENSHOTS}/${testInfo.project.name}-status-filter-pending.png` });
    });
});
