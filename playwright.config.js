// @ts-check
const { defineConfig, devices } = require('@playwright/test');

/**
 * CNDQ Playwright Configuration
 *
 * Run all tests:      npx playwright test
 * Run headed:         npx playwright test --headed
 * Run one spec:       npx playwright test health
 * View report:        npx playwright show-report tests/playwright-report
 *
 * Base URL is read from the BASE_URL environment variable, defaulting to the
 * PHP built-in server used in local development and CI.
 */
module.exports = defineConfig({
    testDir: './tests/playwright',
    testMatch: '**/*.spec.js',

    // Two-minute ceiling per test — game resets and trade cycles are slow.
    timeout: 120_000,
    expect: { timeout: 10_000 },

    // Tests share game state; they must not run concurrently.
    fullyParallel: false,
    workers: 1,
    retries: 0,

    reporter: [
        ['list'],
        ['html', { open: 'never', outputFolder: 'tests/playwright-report' }],
    ],

    // Auto-start the PHP built-in server before tests and stop it after.
    // Run from the parent directory so http://localhost:8000/CNDQ/ resolves correctly.
    // Set reuseExistingServer:true so a manually-started server is reused without error.
    webServer: {
        command: 'php -S localhost:8000 -t static',
        url: 'http://localhost:8000/api/session/status.php',
        reuseExistingServer: true,
        timeout: 15_000,
    },

    use: {
        baseURL: process.env.BASE_URL || 'http://localhost:8000/',
        headless: true,
        screenshot: 'only-on-failure',
        video: 'off',
    },

    projects: [
        { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
    ],
});
