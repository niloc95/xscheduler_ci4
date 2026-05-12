import { defineConfig, devices } from '@playwright/test';
// No dotenv import needed — Playwright 1.45+ loads .env natively.
// Add ADMIN_EMAIL, ADMIN_PASSWORD, APP_BASEURL to your local .env file.

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: false,
    retries: 0,
    workers: 1,
    reporter: [['html', { outputFolder: 'tests/e2e/reports', open: 'never' }]],
    use: {
        baseURL: process.env.APP_BASEURL ?? 'http://localhost:8080',
        screenshot: 'on',
        trace: 'off',
    },
    projects: [
        {
            name: 'desktop',
            use: { ...devices['Desktop Chrome'], viewport: { width: 1280, height: 900 } },
        },
        {
            name: 'tablet',
            use: { ...devices['Desktop Chrome'], viewport: { width: 768, height: 1024 } },
        },
        {
            name: 'mobile',
            use: { ...devices['iPhone 14'] },
        },
    ],
});
