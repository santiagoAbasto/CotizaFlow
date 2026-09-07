import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/Browser',
    testMatch: '*.spec.ts',
    workers: 1,
    use: { baseURL: 'http://127.0.0.1:8123', browserName: 'chromium', trace: 'retain-on-failure' },
    webServer: {
        command: 'php tests/Browser/prepare.php && php artisan serve --host=127.0.0.1 --port=8123 --no-reload',
        url: 'http://127.0.0.1:8123/login',
        reuseExistingServer: false,
        env: {
            APP_ENV: 'testing', DB_CONNECTION: 'mysql', DB_DATABASE: 'cotizaflow_testing', DB_URL: '',
            SESSION_DRIVER: 'file', CACHE_STORE: 'array', APP_DEBUG: 'false',
        },
    },
});
