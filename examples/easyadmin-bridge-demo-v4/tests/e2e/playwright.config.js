import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright config for the bridge demo's E2E suite.
 *
 * Tests assume the demo is already running on http://localhost:8081
 * with the seeded SQLite DB. Operators start it via `make demo-bridge`
 * from the repo root before invoking these tests — Playwright doesn't
 * boot the demo itself because the demo lives in a Docker container
 * with port mapping that's outside Playwright's scope.
 */
export default defineConfig({
    testDir: './specs',
    timeout: 30_000,
    expect: { timeout: 5_000 },
    fullyParallel: false, // session-state tests share the cookie jar
    retries: 0,
    workers: 1,
    reporter: 'list',
    use: {
        baseURL: 'http://localhost:8081',
        // Demo uses HTTP basic auth (admin/admin) configured in
        // config/packages/security.yaml.
        httpCredentials: { username: 'admin', password: 'admin' },
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        viewport: { width: 1280, height: 900 },
    },
    projects: [
        { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
    ],
});
